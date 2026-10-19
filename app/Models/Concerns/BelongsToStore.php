<?php

namespace App\Models\Concerns;

use App\Models\User;
use App\Support\StoreAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/** For records that belong to one of a seller's stores (branch_id). */
trait BelongsToStore
{
    /**
     * Records of the person's business in the stores they may see — limited
     * to the store they are working in when one is chosen.
     */
    public function scopeForUser(Builder $query, ?User $user = null, bool $currentStoreOnly = true): Builder
    {
        $user ??= Auth::user();
        $table = $this->getTable();
        $ids = $currentStoreOnly ? StoreAccess::scopeIds($user) : StoreAccess::branchIds($user);

        return $query
            ->where("{$table}.merchant_id", $user?->merchant_id)
            ->where(function (Builder $q) use ($table, $ids, $user, $currentStoreOnly) {
                $q->whereIn("{$table}.branch_id", $ids);
                // Records without a store are business-wide: only for people who see every store.
                if (StoreAccess::seesAll($user) && (! $currentStoreOnly || StoreAccess::currentId($user) === null)) {
                    $q->orWhereNull("{$table}.branch_id");
                }
            });
    }
}
