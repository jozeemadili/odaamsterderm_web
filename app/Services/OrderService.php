<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\SaleItem;
use App\Models\SalesRecord;
use App\Models\User;
use App\Notifications\OrderPlaced;
use App\Notifications\OrderStatusChanged;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Cart → checkout → pickup. Stock policy (agreed in the plan): checkout
 * reserves the units (they leave the marketplace quantity); completion at
 * pickup deducts physical stock and records an online sale; rejection or
 * cancellation returns the units to the marketplace.
 */
class OrderService
{
    public function __construct(private readonly InventoryService $inventory) {}

    /** Adds to (or sets) the quantity of an offer in the customer's cart. */
    public function setCartQuantity(User $customer, int $offerId, float $quantity): ?CartItem
    {
        $cart = Cart::for($customer);

        if ($quantity <= 0) {
            $cart->items()->where('inventory_item_id', $offerId)->delete();

            return null;
        }

        $offer = $this->orderableOffer($offerId);

        if ($quantity > (float) $offer->marketplace_quantity) {
            throw ValidationException::withMessages([
                'quantity' => "Only {$this->qty($offer->marketplace_quantity)} {$offer->unit} available from {$offer->branch->name}.",
            ]);
        }

        return $cart->items()->updateOrCreate(['inventory_item_id' => $offer->id], ['quantity' => $quantity]);
    }

    public function addToCart(User $customer, int $offerId, float $quantity = 1): CartItem
    {
        $existing = (float) (Cart::for($customer)->items()->where('inventory_item_id', $offerId)->value('quantity') ?? 0);

        return $this->setCartQuantity($customer, $offerId, $existing + $quantity);
    }

    /**
     * Cart lines grouped by pickup store, each line with its offer and
     * whether it can still be ordered as-is.
     *
     * @return array<int, array{store: Branch, lines: array, total: float}>
     */
    public function cartByStore(User $customer): array
    {
        $items = Cart::for($customer)->items()
            ->with(['inventoryItem.branch', 'inventoryItem.merchant', 'inventoryItem.product'])
            ->get();

        $groups = [];

        foreach ($items as $line) {
            $offer = $line->inventoryItem;
            $orderable = $this->isOrderable($offer);
            $problem = match (true) {
                ! $orderable => 'No longer available from this store.',
                (float) $line->quantity > (float) $offer->marketplace_quantity => "Only {$this->qty($offer->marketplace_quantity)} {$offer->unit} available.",
                default => null,
            };

            $groups[$offer->branch_id] ??= ['store' => $offer->branch, 'lines' => [], 'total' => 0.0];
            $groups[$offer->branch_id]['lines'][] = [
                'cart_item' => $line,
                'offer' => $offer,
                'line_total' => (float) $line->quantity * (float) $offer->unit_price,
                'problem' => $problem,
            ];
            $groups[$offer->branch_id]['total'] += (float) $line->quantity * (float) $offer->unit_price;
        }

        return array_values($groups);
    }

    public function cartCount(User $customer): int
    {
        return (int) Cart::for($customer)->items()->count();
    }

    /**
     * Places one order per pickup store. Re-checks every line and reserves
     * its units in one transaction: either every order is placed, or none.
     *
     * @return Collection<int, Order>
     */
    public function checkout(User $customer, ?string $note = null, ?string $phone = null): Collection
    {
        $orders = DB::transaction(function () use ($customer, $note, $phone) {
            $lines = Cart::for($customer)->items()->with(['inventoryItem.branch', 'inventoryItem.product'])->lockForUpdate()->get();

            if ($lines->isEmpty()) {
                throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
            }

            $orders = new Collection;

            foreach ($lines->groupBy(fn (CartItem $l) => $l->inventoryItem->branch_id) as $storeLines) {
                $first = $storeLines->first()->inventoryItem;

                $order = Order::create([
                    'reference' => Order::newReference(),
                    'user_id' => $customer->id,
                    'merchant_id' => $first->merchant_id,
                    'branch_id' => $first->branch_id,
                    'status' => Order::PENDING,
                    'customer_name' => $customer->name,
                    'customer_phone' => $phone ?: $customer->phone,
                    'customer_note' => $note ?: null,
                ]);

                $total = 0.0;

                foreach ($storeLines as $line) {
                    $offer = $line->inventoryItem;
                    $quantity = (float) $line->quantity;

                    try {
                        $this->inventory->reserve($offer, $quantity, [
                            'reference' => 'marketplace_order',
                            'notes' => "Order {$order->reference}",
                            'recorded_by' => $customer->id,
                        ]);
                    } catch (ValidationException $e) {
                        throw ValidationException::withMessages(['cart' => collect($e->errors())->flatten()->first()]);
                    }

                    $lineTotal = $quantity * (float) $offer->unit_price;
                    $total += $lineTotal;

                    $order->items()->create([
                        'inventory_item_id' => $offer->id,
                        'product_id' => $offer->product_id,
                        'name' => $offer->offerName(),
                        'unit' => $offer->sellingUnit(),
                        'unit_price' => (float) $offer->unit_price,
                        'quantity' => $quantity,
                        'line_total' => $lineTotal,
                    ]);
                }

                $order->update(['total' => $total, 'items_count' => $storeLines->count()]);
                $order->events()->create(['status' => Order::PENDING, 'note' => 'Order placed', 'user_id' => $customer->id, 'created_at' => now()]);
                $orders->push($order);
            }

            Cart::for($customer)->items()->delete();

            return $orders;
        });

        foreach ($orders as $order) {
            $order->load('branch');
            Notification::send($this->sellerStaff($order), new OrderPlaced($order));
        }

        return $orders;
    }

    /** Seller moves an order on (accept, prepare, ready, complete, reject). */
    public function advance(Order $order, string $to, User $by, ?string $reason = null): Order
    {
        if ($to === Order::REJECTED && trim((string) $reason) === '') {
            throw ValidationException::withMessages(['reason' => 'Tell the customer why the order is rejected.']);
        }

        $order = DB::transaction(function () use ($order, $to, $by, $reason) {
            /** @var Order $locked */
            $locked = Order::lockForUpdate()->with('items')->findOrFail($order->id);

            if (! in_array($to, $locked->nextSellerStatuses(), true)) {
                throw ValidationException::withMessages([
                    'status' => "An order that is {$locked->statusLabel()} can't be moved to ".(Order::LABELS[$to] ?? $to).'.',
                ]);
            }

            $meta = ['reference' => 'marketplace_order', 'notes' => "Order {$locked->reference}", 'recorded_by' => $by->id];

            match ($to) {
                Order::COMPLETED => $this->complete($locked, $by, $meta),
                Order::REJECTED => $locked->items->each(fn ($item) => $item->inventory_item_id && $this->inventory->release($item->inventory_item_id, (float) $item->quantity, $meta + ['reason' => $reason])),
                default => null,
            };

            $locked->update(array_filter([
                'status' => $to,
                'accepted_at' => $to === Order::ACCEPTED ? now() : null,
                'ready_at' => $to === Order::READY ? now() : null,
                'completed_at' => $to === Order::COMPLETED ? now() : null,
                'closed_at' => $to === Order::REJECTED ? now() : null,
                'status_reason' => $to === Order::REJECTED ? $reason : null,
            ], fn ($v) => $v !== null));

            $locked->events()->create(['status' => $to, 'note' => $reason, 'user_id' => $by->id, 'created_at' => now()]);

            return $locked;
        });

        $order->load('branch', 'customer');
        $order->customer->notify(new OrderStatusChanged($order));

        return $order;
    }

    public function cancelByCustomer(Order $order, User $customer, ?string $reason = null): Order
    {
        abort_unless($order->user_id === $customer->id, 403);

        $order = DB::transaction(function () use ($order, $customer, $reason) {
            $locked = Order::lockForUpdate()->with('items')->findOrFail($order->id);

            if (! $locked->canBeCancelledByCustomer()) {
                throw ValidationException::withMessages([
                    'status' => 'This order is already being prepared — contact the store to change it.',
                ]);
            }

            $meta = ['reference' => 'marketplace_order', 'notes' => "Order {$locked->reference} cancelled", 'recorded_by' => $customer->id];
            $locked->items->each(fn ($item) => $item->inventory_item_id && $this->inventory->release($item->inventory_item_id, (float) $item->quantity, $meta));

            $locked->update(['status' => Order::CANCELLED, 'closed_at' => now(), 'status_reason' => $reason ?: null]);
            $locked->events()->create(['status' => Order::CANCELLED, 'note' => $reason, 'user_id' => $customer->id, 'created_at' => now()]);

            return $locked;
        });

        $order->load('branch');
        Notification::send($this->sellerStaff($order), new OrderStatusChanged($order));

        return $order;
    }

    /** Pickup: deduct the reserved stock and record the sale. */
    protected function complete(Order $order, User $by, array $meta): void
    {
        $sale = SalesRecord::create([
            'merchant_id' => $order->merchant_id,
            'source' => SalesRecord::SOURCE_ONLINE,
            'order_id' => $order->id,
            'branch_id' => $order->branch_id,
            'amount' => $order->total,
            'subtotal' => $order->total,
            'discount_amount' => 0,
            'payment_method' => 'Paid at pickup',
            'items_count' => $order->items_count,
            'description' => "Marketplace order {$order->reference}",
            'sale_date' => now()->toDateString(),
            'recorded_by' => $by->id,
        ]);

        foreach ($order->items as $item) {
            if ($item->inventory_item_id) {
                $this->inventory->fulfilReserved($item->inventory_item_id, (float) $item->quantity, $meta);
            }

            SaleItem::create([
                'sale_id' => $sale->id,
                'inventory_item_id' => $item->inventory_item_id,
                'item_name' => $item->name,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'unit_cost' => InventoryItem::find($item->inventory_item_id)?->unit_cost,
                'gross_amount' => $item->line_total,
                'discount_amount' => 0,
                'subtotal' => $item->line_total,
            ]);
        }
    }

    protected function orderableOffer(int $offerId): InventoryItem
    {
        $offer = InventoryItem::with(['branch', 'merchant', 'product'])->find($offerId);

        if (! $offer || ! $this->isOrderable($offer)) {
            throw ValidationException::withMessages(['offer' => 'This product is not available from that store right now.']);
        }

        return $offer;
    }

    protected function isOrderable(?InventoryItem $offer): bool
    {
        return $offer !== null
            && $offer->is_marketplace_visible
            && $offer->marketplace_status === InventoryItem::MARKET_ACTIVE
            && (float) $offer->marketplace_quantity > 0
            && $offer->marketplaceBlockers() === [];
    }

    /** @return \Illuminate\Support\Collection<int, User> */
    protected function sellerStaff(Order $order)
    {
        return User::where('merchant_id', $order->merchant_id)->where('user_type', User::TYPE_MERCHANT)->get();
    }

    protected function qty(float|string $v): string
    {
        return rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
    }
}
