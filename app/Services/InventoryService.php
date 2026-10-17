<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\StockMovement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The only place that changes stock. Every call locks the item row, writes
 * a StockMovement with the resulting balances, and keeps the marketplace
 * side consistent with physical stock:
 *
 *  - marketplace_quantity never exceeds stock that isn't reserved for open
 *    orders, so an offline sale also shrinks what customers can order;
 *  - marketplace_status flips to out_of_stock when nothing is left to sell
 *    online, and back to active when the seller exposes stock again.
 *
 * All methods accept either a model or an id, and may be called inside an
 * outer transaction (e.g. a POS checkout covering several lines).
 */
class InventoryService
{
    /**
     * Stock received (purchase, stock receipt, import, manual stock-in).
     * Marketplace quantity only grows when $exposeToMarketplace is given.
     *
     * @param  array{reference?:?string, notes?:?string, reason?:?string, recorded_by?:?int, movement_date?:string}  $meta
     */
    public function stockIn(InventoryItem|int $item, float $quantity, array $meta = [], float $exposeToMarketplace = 0): StockMovement
    {
        $this->assertPositive($quantity);

        return $this->apply($item, StockMovement::TYPE_IN, $quantity, $meta, function (InventoryItem $locked) use ($quantity, $exposeToMarketplace) {
            $locked->quantity_on_hand = (float) $locked->quantity_on_hand + $quantity;

            if ($exposeToMarketplace > 0) {
                $locked->marketplace_quantity = (float) $locked->marketplace_quantity + min($exposeToMarketplace, $quantity);
            }
        });
    }

    /**
     * Stock leaving the store: an offline/POS sale, an invoice, a manual
     * stock-out or damage. Refuses to sell stock that is reserved for open
     * marketplace orders.
     *
     * @param  array{reference?:?string, notes?:?string, reason?:?string, recorded_by?:?int, movement_date?:string}  $meta
     */
    public function stockOut(InventoryItem|int $item, float $quantity, string $type = StockMovement::TYPE_OUT, array $meta = []): StockMovement
    {
        $this->assertPositive($quantity);

        return $this->apply($item, $type, $quantity, $meta, function (InventoryItem $locked) use ($quantity) {
            if ($quantity > $locked->availableQuantity() + 0.0001) {
                $reserved = (float) $locked->reserved_quantity > 0
                    ? ' ('.$this->format($locked->reserved_quantity).' reserved for marketplace orders)'
                    : '';

                throw ValidationException::withMessages([
                    'quantity' => "Not enough stock for {$locked->name}: ".$this->format($locked->availableQuantity())." {$locked->unit} available{$reserved}.",
                ]);
            }

            $locked->quantity_on_hand = (float) $locked->quantity_on_hand - $quantity;
        });
    }

    /**
     * Stock count correction: sets physical stock to what was counted and
     * records the signed difference. A reason is mandatory.
     *
     * @param  array{reference?:?string, notes?:?string, recorded_by?:?int, movement_date?:string}  $meta
     */
    public function adjustTo(InventoryItem|int $item, float $countedQuantity, string $reason, array $meta = []): StockMovement
    {
        if ($countedQuantity < 0) {
            throw ValidationException::withMessages(['quantity' => 'Counted quantity cannot be negative.']);
        }

        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'Give a reason for the stock adjustment.']);
        }

        $delta = 0.0;

        return $this->apply($item, StockMovement::TYPE_ADJUSTMENT, 0, ['reason' => $reason] + $meta, function (InventoryItem $locked) use ($countedQuantity, &$delta) {
            if ($countedQuantity + 0.0001 < (float) $locked->reserved_quantity) {
                throw ValidationException::withMessages([
                    'quantity' => 'Counted quantity is below the '.$this->format($locked->reserved_quantity).' units reserved for open marketplace orders.',
                ]);
            }

            $delta = $countedQuantity - (float) $locked->quantity_on_hand;
            $locked->quantity_on_hand = $countedQuantity;
        }, function () use (&$delta) {
            return $delta;
        });
    }

    /**
     * Seller's marketplace controls for one item. Quantity is capped at
     * stock that isn't already reserved; turning visibility on requires the
     * item to pass InventoryItem::marketplaceBlockers().
     *
     * @param  array{recorded_by?:?int}  $meta
     */
    public function updateMarketplace(InventoryItem|int $item, bool $visible, float $marketplaceQuantity, bool $active = true, array $meta = []): InventoryItem
    {
        if ($marketplaceQuantity < 0) {
            throw ValidationException::withMessages(['marketplace_quantity' => 'Marketplace quantity cannot be negative.']);
        }

        $locked = null;

        $this->apply($item, StockMovement::TYPE_MARKETPLACE, 0, ['reference' => 'marketplace_settings'] + $meta, function (InventoryItem $row) use ($visible, $marketplaceQuantity, $active, &$locked) {
            if ($visible && ($blockers = $row->marketplaceBlockers())) {
                throw ValidationException::withMessages(['is_marketplace_visible' => $blockers[0]]);
            }

            if ($marketplaceQuantity > $row->availableQuantity() + 0.0001) {
                throw ValidationException::withMessages([
                    'marketplace_quantity' => 'Marketplace quantity cannot exceed the '.$this->format($row->availableQuantity())." {$row->unit} in stock.",
                ]);
            }

            $row->is_marketplace_visible = $visible;
            $row->marketplace_quantity = $marketplaceQuantity;
            $row->marketplace_status = $active ? InventoryItem::MARKET_ACTIVE : InventoryItem::MARKET_INACTIVE;
            $locked = $row;
        });

        return $locked;
    }

    /**
     * Holds units for a new marketplace order. Takes them from the
     * marketplace quantity (what customers could order) into reserved;
     * physical stock stays until pickup. Refuses if the listing is no
     * longer orderable or has too few units — the checkout re-check.
     *
     * @param  array{reference?:?string, notes?:?string, recorded_by?:?int}  $meta
     */
    public function reserve(InventoryItem|int $item, float $quantity, array $meta = []): StockMovement
    {
        $this->assertPositive($quantity);

        return $this->apply($item, StockMovement::TYPE_RESERVE, $quantity, $meta, function (InventoryItem $locked) use ($quantity) {
            $orderable = $locked->is_marketplace_visible
                && $locked->marketplace_status === InventoryItem::MARKET_ACTIVE
                && $locked->marketplaceBlockers() === [];

            if (! $orderable || $quantity > (float) $locked->marketplace_quantity + 0.0001) {
                $left = $orderable ? $this->format($locked->marketplace_quantity) : '0';

                throw ValidationException::withMessages([
                    'quantity' => "Only {$left} {$locked->unit} of {$locked->name} can be ordered from this store right now.",
                ]);
            }

            $locked->marketplace_quantity = (float) $locked->marketplace_quantity - $quantity;
            $locked->reserved_quantity = (float) $locked->reserved_quantity + $quantity;
        });
    }

    /**
     * Returns reserved units to the marketplace when an order is rejected
     * or cancelled.
     *
     * @param  array{reference?:?string, notes?:?string, reason?:?string, recorded_by?:?int}  $meta
     */
    public function release(InventoryItem|int $item, float $quantity, array $meta = []): StockMovement
    {
        $this->assertPositive($quantity);

        return $this->apply($item, StockMovement::TYPE_RELEASE, $quantity, $meta, function (InventoryItem $locked) use ($quantity) {
            $released = min($quantity, (float) $locked->reserved_quantity);
            $locked->reserved_quantity = (float) $locked->reserved_quantity - $released;
            // Back on offer — syncMarketplace() still caps it at unreserved stock.
            $locked->marketplace_quantity = (float) $locked->marketplace_quantity + $released;

            if ($locked->marketplace_status === InventoryItem::MARKET_OUT_OF_STOCK) {
                $locked->marketplace_status = InventoryItem::MARKET_ACTIVE;
            }
        });
    }

    /**
     * The customer collected the order: reserved units leave physical stock.
     *
     * @param  array{reference?:?string, notes?:?string, recorded_by?:?int}  $meta
     */
    public function fulfilReserved(InventoryItem|int $item, float $quantity, array $meta = []): StockMovement
    {
        $this->assertPositive($quantity);

        return $this->apply($item, StockMovement::TYPE_ONLINE_SALE, $quantity, $meta, function (InventoryItem $locked) use ($quantity) {
            if ($quantity > (float) $locked->reserved_quantity + 0.0001 || $quantity > (float) $locked->quantity_on_hand + 0.0001) {
                throw ValidationException::withMessages([
                    'quantity' => "{$locked->name} no longer has {$this->format($quantity)} {$locked->unit} reserved — check the stock count before completing.",
                ]);
            }

            $locked->reserved_quantity = (float) $locked->reserved_quantity - $quantity;
            $locked->quantity_on_hand = (float) $locked->quantity_on_hand - $quantity;
        });
    }

    /**
     * @param  callable(InventoryItem): void  $mutate  changes quantity_on_hand / marketplace fields on the locked row
     * @param  (callable(): float)|null  $loggedQuantity  quantity to record when only known after $mutate (adjustments)
     */
    protected function apply(InventoryItem|int $item, string $type, float $quantity, array $meta, callable $mutate, ?callable $loggedQuantity = null): StockMovement
    {
        $id = $item instanceof InventoryItem ? $item->id : $item;

        $movement = DB::transaction(function () use ($id, $type, $quantity, $meta, $mutate, $loggedQuantity) {
            /** @var InventoryItem $locked */
            $locked = InventoryItem::with(['merchant', 'product', 'branch'])->lockForUpdate()->findOrFail($id);
            $mutate($locked);
            $this->syncMarketplace($locked);
            $locked->save();

            return StockMovement::create([
                'merchant_id' => $locked->merchant_id,
                'inventory_item_id' => $locked->id,
                'type' => $type,
                'quantity' => $loggedQuantity ? $loggedQuantity() : $quantity,
                'balance_after' => $locked->quantity_on_hand,
                'marketplace_quantity_after' => $locked->marketplace_quantity,
                'reference' => $meta['reference'] ?? null,
                'reason' => $meta['reason'] ?? null,
                'notes' => $meta['notes'] ?? null,
                'movement_date' => $meta['movement_date'] ?? now()->toDateString(),
                'recorded_by' => array_key_exists('recorded_by', $meta) ? $meta['recorded_by'] : Auth::id(),
            ]);
        });

        if ($item instanceof InventoryItem) {
            $item->refresh();
        }

        return $movement;
    }

    /**
     * Applies the synchronization rule after any change: cap the
     * marketplace quantity at unreserved stock and derive the status.
     * An inactive item stays inactive until the seller reactivates it.
     */
    protected function syncMarketplace(InventoryItem $item): void
    {
        $item->marketplace_quantity = round(min((float) $item->marketplace_quantity, $item->availableQuantity()), 2);

        if ($item->marketplace_status === InventoryItem::MARKET_INACTIVE) {
            return;
        }

        $item->marketplace_status = (float) $item->marketplace_quantity > 0
            ? InventoryItem::MARKET_ACTIVE
            : InventoryItem::MARKET_OUT_OF_STOCK;
    }

    protected function assertPositive(float $quantity): void
    {
        if ($quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => 'Quantity must be greater than zero.']);
        }
    }

    protected function format(float|string $quantity): string
    {
        return rtrim(rtrim(number_format((float) $quantity, 2, '.', ''), '0'), '.');
    }
}
