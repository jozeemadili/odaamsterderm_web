<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'merchant_id', 'name', 'address', 'phone', 'email', 'is_primary', 'region_id', 'city',
    'latitude', 'longitude', 'pickup_instructions', 'opening_hours', 'is_marketplace_enabled', 'status',
])]
class Branch extends Model
{
    use LogsActivity;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    /** Mirrors the column defaults so new models are complete before a refresh. */
    protected $attributes = [
        'is_marketplace_enabled' => true,
        'status' => self::STATUS_ACTIVE,
    ];

    public const DAYS = ['mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday', 'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday'];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'is_marketplace_enabled' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
            'opening_hours' => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'address', 'latitude', 'longitude', 'is_marketplace_enabled', 'status'])
            ->logOnlyDirty()
            ->useLogName('store');
    }

    public function scopeMarketplaceReady(Builder $query): Builder
    {
        return $query->where('branches.status', self::STATUS_ACTIVE)
            ->where('branches.is_marketplace_enabled', true)
            ->whereNotNull('branches.latitude')
            ->whereNotNull('branches.longitude');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function hasLocation(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /** "Mon–Fri 08:00–18:00"-style lines for display, one per open day. */
    public function openingHoursLines(): array
    {
        $lines = [];

        foreach (self::DAYS as $key => $label) {
            $day = $this->opening_hours[$key] ?? null;
            $lines[$label] = ($day && ! empty($day['open']) && ! empty($day['close'])) ? $day['open'].'–'.$day['close'] : 'Closed';
        }

        return $lines;
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
