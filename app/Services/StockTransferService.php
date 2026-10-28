<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\User;
use App\Support\StoreAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Moves stock between two stores of one seller:
 *  - send:    stock leaves the sending store at once;
 *  - receive: it arrives in the receiving store, creating the product
 *             there (same product, prices and photo) the first time;
 *  - cancel:  before it is received, the stock goes back.
 */
class StockTransferService
{
    public function __construct(private InventoryService $inventory) {}

    /**
     * @param  array<int, array{inventory_item_id:int|string, quantity:float|int|string}>  $lines
     */
    public function send(User $user, int $fromBranchId, int $toBranchId, array $lines, ?string $notes = null): StockTransfer
    {
        if (! StockTransfer::canSend($user)) {
            throw ValidationException::withMessages(['from_branch_id' => 'Only owners and managers can send stock to another store.']);
        }

        $from = Branch::where('merchant_id', $user->merchant_id)->find($fromBranchId);
        $to = Branch::where('merchant_id', $user->merchant_id)->find($toBranchId);

        if (! $from || ! StoreAccess::allows($from->id, $user)) {
            throw ValidationException::withMessages(['from_branch_id' => 'Choose a store you work in to send from.']);
        }
        if (! $to) {
            throw ValidationException::withMessages(['to_branch_id' => 'Choose one of your other stores to send to.']);
        }
        if ($from->id === $to->id) {
            throw ValidationException::withMessages(['to_branch_id' => 'Send to a different store.']);
        }

        $lines = collect($lines)
            ->filter(fn ($l) => ! empty($l['inventory_item_id']) && (float) ($l['quantity'] ?? 0) > 0)
            ->groupBy(fn ($l) => (int) $l['inventory_item_id'])
            ->map(fn ($group, $id) => ['inventory_item_id' => (int) $id, 'quantity' => (float) $group->sum(fn ($l) => (float) $l['quantity'])])
            ->values();

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages(['lines' => 'Add at least one product with a quantity.']);
        }

        return DB::transaction(function () use ($user, $from, $to, $lines, $notes) {
            $count = StockTransfer::where('merchant_id', $user->merchant_id)->lockForUpdate()->count();

            $transfer = StockTransfer::create([
                'merchant_id' => $user->merchant_id,
                'number' => 'TRF-'.str_pad((string) ($count + 1), 5, '0', STR_PAD_LEFT),
                'from_branch_id' => $from->id,
                'to_branch_id' => $to->id,
                'status' => StockTransfer::STATUS_SENT,
                'notes' => $notes ?: null,
                'sent_by' => $user->id,
                'sent_at' => now(),
            ]);

            foreach ($lines as $line) {
                $item = InventoryItem::where('merchant_id', $user->merchant_id)
                    ->where('branch_id', $from->id)
                    ->find($line['inventory_item_id']);

                if (! $item) {
                    throw ValidationException::withMessages(['lines' => "That product is not in {$from->name}."]);
                }

                $this->inventory->stockOut($item, $line['quantity'], StockMovement::TYPE_TRANSFER_OUT, [
                    'reference' => 'stock_transfer',
                    'notes' => "Transfer {$transfer->number} to {$to->name}",
                    'recorded_by' => $user->id,
                ]);

                $transfer->items()->create([
                    'from_item_id' => $item->id,
                    'item_name' => $item->name,
                    'unit' => $item->unit,
                    'quantity' => $line['quantity'],
                ]);
            }

            return $transfer->load('items');
        });
    }

    public function receive(StockTransfer $transfer, User $user): StockTransfer
    {
        if (! $transfer->canReceive($user)) {
            throw ValidationException::withMessages(['transfer' => 'Only staff of the receiving store can receive this transfer.']);
        }

        return DB::transaction(function () use ($transfer, $user) {
            $transfer = StockTransfer::lockForUpdate()->findOrFail($transfer->id);

            if (! $transfer->isSent()) {
                throw ValidationException::withMessages(['transfer' => 'This transfer was already '.$transfer->status.'.']);
            }

            $fromName = $transfer->fromBranch?->name;

            foreach ($transfer->items as $line) {
                $target = $this->destinationItem($transfer, $line->fromItem, $line->item_name, $line->unit);

                $this->inventory->stockIn($target, (float) $line->quantity, [
                    'reference' => 'stock_transfer',
                    'notes' => "Transfer {$transfer->number} from {$fromName}",
                    'recorded_by' => $user->id,
                ]);

                $line->update(['to_item_id' => $target->id]);
            }

            $transfer->update([
                'status' => StockTransfer::STATUS_RECEIVED,
                'received_by' => $user->id,
                'received_at' => now(),
            ]);

            return $transfer->fresh('items');
        });
    }

    public function cancel(StockTransfer $transfer, User $user): StockTransfer
    {
        if (! $transfer->canCancel($user)) {
            throw ValidationException::withMessages(['transfer' => 'Only an owner or manager of the sending store can cancel this transfer.']);
        }

        return DB::transaction(function () use ($transfer, $user) {
            $transfer = StockTransfer::lockForUpdate()->findOrFail($transfer->id);

            if (! $transfer->isSent()) {
                throw ValidationException::withMessages(['transfer' => 'This transfer was already '.$transfer->status.'.']);
            }

            foreach ($transfer->items as $line) {
                $source = $line->fromItem ?? $this->recreateSource($transfer, $line->item_name, $line->unit);

                $this->inventory->stockIn($source, (float) $line->quantity, [
                    'reference' => 'stock_transfer',
                    'notes' => "Transfer {$transfer->number} cancelled",
                    'recorded_by' => $user->id,
                ]);
            }

            $transfer->update([
                'status' => StockTransfer::STATUS_CANCELLED,
                'cancelled_by' => $user->id,
                'cancelled_at' => now(),
            ]);

            return $transfer->fresh('items');
        });
    }

    /** The same product in the receiving store, added there the first time. */
    private function destinationItem(StockTransfer $transfer, ?InventoryItem $source, string $name, ?string $unit): InventoryItem
    {
        $query = InventoryItem::where('merchant_id', $transfer->merchant_id)->where('branch_id', $transfer->to_branch_id);

        $existing = match (true) {
            $source?->product_id !== null => (clone $query)->where('product_id', $source->product_id)->where('name', $source->name)->first()
                ?? (clone $query)->where('product_id', $source->product_id)->first(),
            filled($source?->sku) => (clone $query)->where('sku', $source->sku)->first(),
            default => null,
        } ?? (clone $query)->where('name', $source?->name ?? $name)->first();

        if ($existing) {
            return $existing;
        }

        if (! $source) {
            return InventoryItem::create([
                'merchant_id' => $transfer->merchant_id,
                'branch_id' => $transfer->to_branch_id,
                'name' => $name,
                'unit' => $unit,
                'quantity_on_hand' => 0,
                'reorder_level' => 0,
            ]);
        }

        $copy = InventoryItem::create([
            ...$source->only(['category_id', 'product_id', 'name', 'sku', 'barcode', 'unit', 'reorder_level', 'unit_cost', 'unit_price', 'expiry_date']),
            'merchant_id' => $transfer->merchant_id,
            'branch_id' => $transfer->to_branch_id,
            'quantity_on_hand' => 0,
        ]);

        try {
            foreach (['image', InventoryItem::BACK_PHOTO] as $collection) {
                foreach ($source->getMedia($collection) as $media) {
                    $media->copy($copy, $collection);
                }
            }
        } catch (Throwable) {
            // A missing photo file must not block receiving stock.
        }

        return $copy;
    }

    private function recreateSource(StockTransfer $transfer, string $name, ?string $unit): InventoryItem
    {
        return InventoryItem::firstOrCreate(
            ['merchant_id' => $transfer->merchant_id, 'branch_id' => $transfer->from_branch_id, 'name' => $name],
            ['unit' => $unit, 'quantity_on_hand' => 0, 'reorder_level' => 0],
        );
    }
}
