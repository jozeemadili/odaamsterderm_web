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
    'code', 'short_code', 'merchant_id', 'name', 'store_type', 'address', 'phone', 'whatsapp', 'email', 'manager_name',
    'is_primary', 'region_id', 'city', 'area', 'landmark', 'latitude', 'longitude', 'pickup_instructions', 'opening_hours',
    'delivery_radius_km', 'delivery_base_fee', 'delivery_fee_per_km', 'pickup_available', 'cold_chain_capable', 'vet_advice_on_site',
    'bio', 'bio_sw', 'is_marketplace_enabled', 'status', 'suspension_reason',
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
        'pickup_available' => true,
        'cold_chain_capable' => false,
        'vet_advice_on_site' => false,
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
            'delivery_radius_km' => 'float',
            'delivery_base_fee' => 'float',
            'delivery_fee_per_km' => 'float',
            'pickup_available' => 'boolean',
            'cold_chain_capable' => 'boolean',
            'vet_advice_on_site' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'address', 'latitude', 'longitude', 'delivery_radius_km', 'delivery_base_fee', 'delivery_fee_per_km', 'cold_chain_capable', 'is_marketplace_enabled', 'status', 'suspension_reason'])
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

    /** A store delivers when it has a delivery radius; otherwise pickup only. */
    public function offersDelivery(): bool
    {
        return $this->delivery_radius_km !== null && $this->delivery_radius_km > 0;
    }

    /**
     * Delivery fee to a point this far away: the base fee plus the per-km
     * fee, rounded up to the next 100 TZS. Null when the store doesn't
     * deliver that far.
     */
    public function deliveryFeeFor(?float $distanceKm): ?float
    {
        if (! $this->offersDelivery() || $distanceKm === null || $distanceKm > $this->delivery_radius_km) {
            return null;
        }

        $fee = ($this->delivery_base_fee ?? 0) + ($this->delivery_fee_per_km ?? 0) * $distanceKm;

        return ceil($fee / 100) * 100;
    }

    /** The bio in the reader's language, falling back to the other one. */
    public function localBio(?string $locale = null): ?string
    {
        return ($locale ?? app()->getLocale()) === 'sw' ? ($this->bio_sw ?: $this->bio) : ($this->bio ?: $this->bio_sw);
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
