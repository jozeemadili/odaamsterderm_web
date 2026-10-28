<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One field in the catalogue dictionary (form, regulatory_class,
 * withdrawal_meat_days…): its label in English and Kiswahili, its type,
 * and which categories require it.
 */
#[Fillable([
    'key', 'label', 'label_sw', 'level', 'data_type', 'is_multi_select',
    'required_for', 'values_source', 'notes', 'sort_order',
])]
class ProductAttribute extends Model
{
    protected function casts(): array
    {
        return [
            'is_multi_select' => 'boolean',
        ];
    }

    public function values(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class)->orderBy('sort_order');
    }

    /**
     * Allowed values for a dropdown, code => label.
     *
     * @return array<string, string>
     */
    public static function options(string $key): array
    {
        return ProductAttributeValue::query()
            ->whereRelation('attribute', 'key', $key)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('label', 'code')
            ->all();
    }
}
