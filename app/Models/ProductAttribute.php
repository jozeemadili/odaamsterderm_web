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

    /**
     * What each product is used for / treats, as tags a buyer can read:
     * [{code, label, label_sw}] in dictionary order. Unknown codes are kept
     * with the code as the label.
     *
     * @param  list<string>|null  $codes
     * @return list<array{code:string, label:string, label_sw:?string}>
     */
    public static function useTags(?array $codes): array
    {
        if (! $codes) {
            return [];
        }
        $dictionary = once(fn () => ProductAttributeValue::query()
            ->whereRelation('attribute', 'key', 'use_cases')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['code', 'label', 'label_sw'])
            ->toBase()
            ->keyBy('code'));

        $known = $dictionary->only($codes)->values()->map(fn ($v) => [
            'code' => $v->code, 'label' => $v->label, 'label_sw' => $v->label_sw,
        ]);
        $unknown = collect($codes)->diff($dictionary->keys())->map(fn ($c) => [
            'code' => $c, 'label' => $c, 'label_sw' => null,
        ]);

        return $known->concat($unknown)->values()->all();
    }
}
