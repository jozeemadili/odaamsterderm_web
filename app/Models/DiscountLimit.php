<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['merchant_id', 'role', 'max_percent'])]
class DiscountLimit extends Model
{
    /**
     * Fallback limits used when a merchant hasn't configured its own yet.
     */
    public const DEFAULTS = [
        'merchant_sales' => 5,
        'merchant_supervisor' => 15,
        'merchant_manager' => 30,
        'merchant_accountant' => 0,
        'merchant_owner' => 100,
        'merchant_staff' => 5,
    ];

    protected function casts(): array
    {
        return [
            'max_percent' => 'decimal:2',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public static function maxPercentFor(int $merchantId, string $role): float
    {
        $configured = static::where('merchant_id', $merchantId)->where('role', $role)->value('max_percent');

        return $configured !== null ? (float) $configured : (float) (self::DEFAULTS[$role] ?? 0);
    }
}
