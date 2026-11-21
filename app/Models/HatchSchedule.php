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
 * An order day: the day one agent's chicks of one type from one hatchery
 * arrive at its stores for pickup (hatch_date), with the cap of chicks it
 * will take (capacity, whole boxes). Customers book whole boxes until the
 * day is full or has passed — they can still book on the day itself.
 * Booked chicks are counted from orders (cancelled and rejected ones give
 * their chicks back), so the count can never drift.
 */
#[Fillable(['merchant_id', 'chick_contract_id', 'producer_id', 'chick_type_id', 'product_id', 'hatch_date', 'order_cutoff_date', 'capacity', 'price_per_chick', 'is_closed', 'is_sample', 'notes', 'created_by'])]
class HatchSchedule extends Model
{
    use LogsActivity;

    public const OPEN = 'open';

    public const FULL = 'full';

    public const CLOSED = 'closed';

    public const PAST = 'past';

    public const LABELS = [self::OPEN => 'Open', self::FULL => 'Full', self::CLOSED => 'Closed', self::PAST => 'Past'];

    public const LABELS_SW = [self::OPEN => 'Wazi', self::FULL => 'Imejaa', self::CLOSED => 'Imefungwa', self::PAST => 'Imepita'];

    protected function casts(): array
    {
        return [
            'hatch_date' => 'date',
            'order_cutoff_date' => 'date',
            'capacity' => 'integer',
            'price_per_chick' => 'decimal:2',
            'is_closed' => 'boolean',
            'is_sample' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['hatch_date', 'order_cutoff_date', 'capacity', 'price_per_chick', 'is_closed'])
            ->logOnlyDirty()
            ->useLogName('chicks');
    }

    public static function boxSize(): int
    {
        return (int) config('hub.chick_box_size', 50);
    }

    /** The agent selling this order day. */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(ChickContract::class, 'chick_contract_id');
    }

    public function producer(): BelongsTo
    {
        return $this->belongsTo(Producer::class);
    }

    public function chickType(): BelongsTo
    {
        return $this->belongsTo(ChickType::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** Adds booked_chicks: chicks on orders that are not cancelled or rejected. */
    public function scopeWithBooked(Builder $query): Builder
    {
        return $query->withSum(['orderItems as booked_chicks' => fn (Builder $q) => $q->whereHas(
            'order', fn (Builder $o) => $o->whereNotIn('status', [Order::CANCELLED, Order::REJECTED])
        )], 'quantity');
    }

    /** Order days customers can still book: not closed, and today or later. */
    public function scopeBookable(Builder $query): Builder
    {
        return $query->where('is_closed', false)->whereDate('hatch_date', '>=', today());
    }

    public function booked(): int
    {
        return (int) ($this->booked_chicks ?? $this->orderItems()
            ->whereHas('order', fn (Builder $o) => $o->whereNotIn('status', [Order::CANCELLED, Order::REJECTED]))
            ->sum('quantity'));
    }

    public function available(): int
    {
        return max(0, $this->capacity - $this->booked());
    }

    /** Whole boxes still free. */
    public function capBoxes(): int
    {
        return intdiv($this->capacity, static::boxSize());
    }

    public function boxesAvailable(): int
    {
        return intdiv($this->available(), static::boxSize());
    }

    public function status(): string
    {
        return match (true) {
            $this->hatch_date->lt(today()) => self::PAST,
            $this->is_closed => self::CLOSED,
            $this->boxesAvailable() === 0 => self::FULL,
            default => self::OPEN,
        };
    }

    public function statusLabel(): string
    {
        return self::LABELS[$this->status()];
    }

    /** For the API and the customer pages. */
    public function summaryArray(bool $withPrice): array
    {
        return [
            'id' => $this->id,
            'hatch_date' => $this->hatch_date->toDateString(),
            'cap_boxes' => $this->capBoxes(),
            'available_chicks' => $this->available(),
            'boxes_available' => $this->boxesAvailable(),
            'status' => $this->status(),
            'status_label' => $this->statusLabel(),
            'status_label_sw' => self::LABELS_SW[$this->status()],
            'price_per_chick' => $withPrice && $this->price_per_chick !== null ? (float) $this->price_per_chick : null,
            'price_per_box' => $withPrice && $this->price_per_chick !== null ? (float) $this->price_per_chick * static::boxSize() : null,
            'notes' => $this->notes,
        ];
    }
}
