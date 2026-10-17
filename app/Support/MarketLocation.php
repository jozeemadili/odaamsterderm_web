<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;

/**
 * The location marketplace pages rank stores by: the one chosen this
 * session, else a signed-in customer's saved location.
 */
final class MarketLocation
{
    private const SESSION_KEY = 'market.location';

    public static function current(): ?GeoPoint
    {
        if ($saved = session(self::SESSION_KEY)) {
            return GeoPoint::tryFrom($saved['latitude'] ?? null, $saved['longitude'] ?? null, $saved['label'] ?? null);
        }

        $user = Auth::user();

        if ($user?->isCustomer() && $user->hasLocation()) {
            return new GeoPoint($user->latitude, $user->longitude, $user->location_label);
        }

        return null;
    }

    /** Remembers the point for this visit and, for customers, as their default. */
    public static function set(GeoPoint $point): void
    {
        session([self::SESSION_KEY => $point->toArray()]);

        $user = Auth::user();

        if ($user?->isCustomer()) {
            $user->update([
                'latitude' => $point->latitude,
                'longitude' => $point->longitude,
                'location_label' => $point->label,
            ]);
        }
    }

    public static function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }
}
