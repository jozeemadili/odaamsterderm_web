<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Which of a seller's stores (branches) a person may see and work in, and
 * which one they are working in right now.
 *
 * - The business owner and staff marked "all stores" see every store.
 * - Other staff see only the stores assigned to them.
 * - The current store comes from the portal's store switcher (session) or,
 *   for the app, the X-Store-Id header / store_id parameter. "No current
 *   store" means all the stores the person may see.
 */
class StoreAccess
{
    public const SESSION_KEY = 'portal.store_id';

    /** Per user object (never outlives the request that loaded the user). */
    private static ?\WeakMap $cache = null;

    public static function seesAll(?User $user = null): bool
    {
        $user ??= Auth::user();

        return $user !== null && ($user->all_branches || $user->hasRole('merchant_owner'));
    }

    /** @return list<int> store ids this person may see */
    public static function branchIds(?User $user = null): array
    {
        $user ??= Auth::user();
        if (! $user?->merchant_id) {
            return [];
        }

        self::$cache ??= new \WeakMap;

        return self::$cache[$user] ??= self::seesAll($user)
            ? Branch::where('merchant_id', $user->merchant_id)->orderBy('id')->pluck('id')->all()
            : $user->branches()->where('branches.merchant_id', $user->merchant_id)->orderBy('branches.id')->pluck('branches.id')->all();
    }

    public static function allows(?int $branchId, ?User $user = null): bool
    {
        $user ??= Auth::user();

        return $branchId === null ? self::seesAll($user) : in_array($branchId, self::branchIds($user), true);
    }

    /** The store being worked in, or null for "all my stores". */
    public static function currentId(?User $user = null): ?int
    {
        $user ??= Auth::user();
        $ids = self::branchIds($user);

        if (count($ids) === 1) {
            return $ids[0];
        }

        $request = request();
        $chosen = $request->header('X-Store-Id') ?? $request->query('store_id');
        if ($chosen === null && app()->bound('session')) {
            $chosen = session(self::SESSION_KEY);
        }

        return in_array((int) $chosen, $ids, true) ? (int) $chosen : null;
    }

    public static function current(?User $user = null): ?Branch
    {
        $id = self::currentId($user);

        return $id ? Branch::find($id) : null;
    }

    /** Ids to filter lists by: the current store, else every store the person may see. */
    public static function scopeIds(?User $user = null): array
    {
        $current = self::currentId($user);

        return $current ? [$current] : self::branchIds($user);
    }

    public static function choose(?int $branchId): void
    {
        session([self::SESSION_KEY => $branchId && self::allows($branchId) ? $branchId : null]);
    }

    public static function forget(): void
    {
        self::$cache = null;
    }
}
