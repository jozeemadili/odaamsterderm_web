<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['stock_transfer_id', 'from_item_id', 'to_item_id', 'item_name', 'unit', 'quantity'])]
class StockTransferItem extends Model
{
    protected function casts(): array
    {
        return ['quantity' => 'decimal:3'];
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
    }

    public function fromItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'from_item_id');
    }

    public function toItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'to_item_id');
    }
}
