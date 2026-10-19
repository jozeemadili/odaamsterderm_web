<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['merchant_id', 'inventory_item_id', 'type', 'quantity', 'balance_after', 'marketplace_quantity_after', 'reference', 'reason', 'notes', 'movement_date', 'recorded_by'])]
class StockMovement extends Model
{
    public const TYPE_IN = 'in';

    public const TYPE_OUT = 'out';

    public const TYPE_ADJUSTMENT = 'adjustment';

    public const TYPE_DAMAGE = 'damage';

    /** Change to the marketplace quantity only — physical stock is untouched. */
    public const TYPE_MARKETPLACE = 'marketplace';

    /** Units held for an open marketplace order (physical stock unchanged). */
    public const TYPE_RESERVE = 'reserve';

    /** Reservation returned to the marketplace (order rejected/cancelled). */
    public const TYPE_RELEASE = 'release';

    /** Reserved units handed to the customer — physical stock leaves. */
    public const TYPE_ONLINE_SALE = 'online_sale';

    /** Stock sent to another store of the same seller. */
    public const TYPE_TRANSFER_OUT = 'transfer_out';

    protected function casts(): array
    {
        return [
            'movement_date' => 'date',
            'quantity' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'marketplace_quantity_after' => 'decimal:2',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
