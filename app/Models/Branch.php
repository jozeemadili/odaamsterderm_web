<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['merchant_id', 'name', 'address', 'phone', 'is_primary'])]
class Branch extends Model
{
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }


    /**
     * @return array{stockValue: float, sellingValue: float, expectedProfit: float}
     */
    public function stockSummary(): array
    {
        $items = $this->inventoryItems()->get(['quantity_on_hand', 'unit_cost', 'unit_price']);

        $stockValue = (float) $items->sum(fn ($i) => $i->quantity_on_hand * ($i->unit_cost ?? 0));
        $sellingValue = (float) $items->sum(fn ($i) => $i->quantity_on_hand * ($i->unit_price ?? 0));

        return [
            'stockValue' => $stockValue,
            'sellingValue' => $sellingValue,
            'expectedProfit' => $sellingValue - $stockValue,
        ];
    }
}
