<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A marketplace order for pickup: one customer, one seller, one store.
 *
 * pending → accepted → preparing → ready_for_pickup → completed
 * pending/accepted/preparing → rejected (seller) · pending/accepted → cancelled (customer)
 */
#[Fillable([
    'reference', 'user_id', 'merchant_id', 'branch_id', 'status', 'total', 'items_count',
    'customer_name', 'customer_phone', 'customer_note', 'status_reason',
    'accepted_at', 'ready_at', 'completed_at', 'closed_at',
])]
class Order extends Model
{
    use LogsActivity;

    public const PENDING = 'pending';

    public const ACCEPTED = 'accepted';

    public const PREPARING = 'preparing';

    public const READY = 'ready_for_pickup';

    public const COMPLETED = 'completed';

    public const REJECTED = 'rejected';

    public const CANCELLED = 'cancelled';

    public const LABELS = [
        self::PENDING => 'Waiting for seller',
        self::ACCEPTED => 'Accepted',
        self::PREPARING => 'Being prepared',
        self::READY => 'Ready for pickup',
        self::COMPLETED => 'Completed',
        self::REJECTED => 'Rejected by seller',
        self::CANCELLED => 'Cancelled',
    ];

    /** Seller-driven next steps from each status. */
    public const SELLER_TRANSITIONS = [
        self::PENDING => [self::ACCEPTED, self::REJECTED],
        self::ACCEPTED => [self::PREPARING, self::READY, self::REJECTED],
        self::PREPARING => [self::READY, self::REJECTED],
        self::READY => [self::COMPLETED],
    ];

    /** Statuses in which units are still reserved. */
    public const OPEN = [self::PENDING, self::ACCEPTED, self::PREPARING, self::READY];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'accepted_at' => 'datetime',
            'ready_at' => 'datetime',
            'completed_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['reference', 'status', 'status_reason', 'total'])
            ->logOnlyDirty()
            ->useLogName('order');
    }

    public static function newReference(): string
    {
        do {
            $reference = 'OH-'.now()->format('ym').'-'.Str::upper(Str::random(5));
        } while (self::where('reference', $reference)->exists());

        return $reference;
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', self::OPEN);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }

    public function canBeCancelledByCustomer(): bool
    {
        return in_array($this->status, [self::PENDING, self::ACCEPTED], true);
    }

    /** @return array<int, string> */
    public function nextSellerStatuses(): array
    {
        return self::SELLER_TRANSITIONS[$this->status] ?? [];
    }

    /** Signed link to the seller-branded receipt (no sign-in needed). */
    public function receiptUrl(): string
    {
        return URL::signedRoute('receipts.order', $this);
    }

    public function statusLabel(): string
    {
        return self::LABELS[$this->status] ?? Str::headline($this->status);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class)->orderBy('created_at')->orderBy('id');
    }

    public function sale(): HasOne
    {
        return $this->hasOne(SalesRecord::class);
    }
}
