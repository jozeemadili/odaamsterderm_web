<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * An agent's agreement to sell one hatchery's chicks of one type: which of
 * its stores the chicks arrive at, on which weekdays (Monday and Thursday
 * by default), the cap of boxes per order day and the price per chick.
 * Declared by the agent; order days are generated from it.
 */
#[Fillable(['merchant_id', 'producer_id', 'chick_type_id', 'branch_ids', 'weekdays', 'cap_boxes', 'price_per_chick', 'is_active', 'is_sample', 'generated_until'])]
class ChickContract extends Model
{
    use LogsActivity;

    public const DEFAULT_WEEKDAYS = [1, 4]; // Monday, Thursday

    public const DEFAULT_CAP_BOXES = 100;

    public const WEEKDAYS = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];

    protected function casts(): array
    {
        return [
            'branch_ids' => 'array',
            'weekdays' => 'array',
            'cap_boxes' => 'integer',
            'price_per_chick' => 'decimal:2',
            'is_active' => 'boolean',
            'is_sample' => 'boolean',
            'generated_until' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['producer_id', 'chick_type_id', 'branch_ids', 'weekdays', 'cap_boxes', 'price_per_chick', 'is_active'])
            ->logOnlyDirty()
            ->useLogName('chicks');
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function producer(): BelongsTo
    {
        return $this->belongsTo(Producer::class);
    }

    public function chickType(): BelongsTo
    {
        return $this->belongsTo(ChickType::class);
    }

    public function orderDays(): HasMany
    {
        return $this->hasMany(HatchSchedule::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** The stores the chicks arrive at: the chosen ones, or every store of the agent on the marketplace. */
    public function pickupStores(): Collection
    {
        return Branch::where('merchant_id', $this->merchant_id)
            ->where('status', Branch::STATUS_ACTIVE)
            ->where('is_marketplace_enabled', true)
            ->when($this->branch_ids, fn ($q) => $q->whereIn('id', $this->branch_ids))
            ->orderByDesc('is_primary')->orderBy('name')
            ->get();
    }

    public function weekdayLabels(): string
    {
        return collect($this->weekdays ?? [])->sort()->map(fn ($d) => self::WEEKDAYS[$d] ?? $d)->join(', ');
    }
}
