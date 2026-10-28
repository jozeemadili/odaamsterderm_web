<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One pack size of a catalog product ("25 kg bag"), with the workbook SKU.
 * Price and stock stay on the store's InventoryItem; reference_price is
 * only a suggestion.
 */
#[Fillable([
    'product_id', 'sku', 'name', 'pack_size_value', 'pack_size_unit', 'selling_unit', 'net_weight_kg',
    'gtin', 'reference_price', 'min_order_qty', 'sort_order', 'is_active',
])]
class ProductVariant extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'pack_size_value' => 'decimal:3',
            'net_weight_kg' => 'decimal:3',
            'reference_price' => 'decimal:2',
            'min_order_qty' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['sku', 'name', 'selling_unit', 'reference_price', 'min_order_qty', 'is_active'])
            ->logOnlyDirty()
            ->useLogName('catalog');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    /** "Broiler Starter Crumbs - 25 kg bag" */
    public function displayName(): string
    {
        return $this->product->name.' - '.$this->name;
    }

    /** For APIs: {id, sku, name, selling_unit, min_order_qty}. */
    public function summary(): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'selling_unit' => $this->selling_unit,
            'min_order_qty' => $this->min_order_qty,
        ];
    }

    /** Pack text reduced to letters and digits ("25 kg" and "25KG" both give "25kg"), for matching. */
    public static function packKey(?string $text): string
    {
        return preg_replace('/[^a-z0-9.]/', '', mb_strtolower((string) $text));
    }
}
