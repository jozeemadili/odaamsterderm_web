<?php

namespace App\Models;

use App\Support\StoreAccess;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

/**
 * Stock moved between two stores of the same seller. Stock leaves the
 * sending store when the transfer is sent and arrives when staff at the
 * receiving store confirm it; cancelling before that returns it.
 */
#[Fillable(['merchant_id', 'number', 'from_branch_id', 'to_branch_id', 'status', 'notes', 'sent_by', 'sent_at', 'received_by', 'received_at', 'cancelled_by', 'cancelled_at'])]
class StockTransfer extends Model
{
    public const STATUS_SENT = 'sent';

    public const STATUS_RECEIVED = 'received';

    public const STATUS_CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'received_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function isSent(): bool
    {
        return $this->status === self::STATUS_SENT;
    }

    /** Transfers going out of, or coming into, a store the person works in. */
    public function scopeForUser(Builder $query, ?User $user = null): Builder
    {
        $user ??= Auth::user();
        $ids = StoreAccess::scopeIds($user);

        return $query->where('merchant_id', $user?->merchant_id)
            ->where(fn ($q) => $q->whereIn('from_branch_id', $ids)->orWhereIn('to_branch_id', $ids));
    }

    public function canBeSeenBy(?User $user = null): bool
    {
        $user ??= Auth::user();

        return $user?->merchant_id === $this->merchant_id
            && (StoreAccess::allows($this->from_branch_id, $user) || StoreAccess::allows($this->to_branch_id, $user));
    }

    /** Owners and managers send transfers between stores they work in. */
    public static function canSend(?User $user = null): bool
    {
        $user ??= Auth::user();

        return (bool) $user?->hasAnyRole(['merchant_owner', 'merchant_manager']);
    }

    /** Anyone who works in the receiving store confirms arrival. */
    public function canReceive(?User $user = null): bool
    {
        return $this->isSent() && StoreAccess::allows($this->to_branch_id, $user ?? Auth::user());
    }

    /** The sender's side (owner/manager of the sending store) can call it back. */
    public function canCancel(?User $user = null): bool
    {
        $user ??= Auth::user();

        return $this->isSent() && self::canSend($user) && StoreAccess::allows($this->from_branch_id, $user);
    }
}
