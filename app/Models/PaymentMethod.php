<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['merchant_id', 'name', 'slug', 'type', 'is_active', 'sort_order'])]
class PaymentMethod extends Model
{
    public const TYPE_CASH = 'cash';

    public const TYPE_MOBILE_MONEY = 'mobile_money';

    public const TYPE_BANK = 'bank';

    public const TYPE_CARD = 'card';

    public const TYPE_OTHER = 'other';

    /**
     * Seeded for every new merchant. (name, slug, type)
     */
    public const DEFAULTS = [
        ['Cash', 'cash', self::TYPE_CASH],
        ['M-Pesa', 'mpesa', self::TYPE_MOBILE_MONEY],
        ['Mixx by Yas', 'mixx_yas', self::TYPE_MOBILE_MONEY],
        ['HaloPesa', 'halopesa', self::TYPE_MOBILE_MONEY],
        ['T-Pesa', 'tpesa', self::TYPE_MOBILE_MONEY],
        ['Bank Transfer', 'bank', self::TYPE_BANK],
        ['Bank Card', 'card', self::TYPE_CARD],
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public static function seedDefaultsFor(int $merchantId): void
    {
        foreach (self::DEFAULTS as $index => [$name, $slug, $type]) {
            self::firstOrCreate(
                ['merchant_id' => $merchantId, 'name' => $name],
                ['slug' => $slug, 'type' => $type, 'sort_order' => $index]
            );
        }
    }
}
