<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An allowed value of a list attribute, e.g. form = "pellets" / "Vidonge". */
#[Fillable(['product_attribute_id', 'code', 'label', 'label_sw', 'sort_order', 'is_active'])]
class ProductAttributeValue extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(ProductAttribute::class, 'product_attribute_id');
    }
}
