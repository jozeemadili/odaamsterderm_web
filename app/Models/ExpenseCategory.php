<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['merchant_id', 'name'])]
class ExpenseCategory extends Model
{
    public const DEFAULTS = ['Rent', 'Payroll', 'Utilities', 'Transport', 'Supplier', 'Other'];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public static function ensureDefaultsFor(int $merchantId): void
    {
        if (static::where('merchant_id', $merchantId)->exists()) {
            return;
        }

        foreach (self::DEFAULTS as $name) {
            static::create(['merchant_id' => $merchantId, 'name' => $name]);
        }
    }
}
