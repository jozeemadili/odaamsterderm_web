<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * A customer's search location (device GPS or a point picked on the map).
 */
final class GeoPoint
{
    private const EARTH_RADIUS_KM = 6371;

    public function __construct(
        public readonly float $latitude,
        public readonly float $longitude,
        public readonly ?string $label = null,
    ) {}

    /** Builds a point from loose input, or null when it's missing or out of range. */
    public static function tryFrom(mixed $latitude, mixed $longitude, ?string $label = null): ?self
    {
        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return null;
        }

        $lat = (float) $latitude;
        $lng = (float) $longitude;

        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return null;
        }

        return new self($lat, $lng, $label);
    }

    /**
     * Great-circle (haversine/spherical law of cosines) distance in km from
     * this point to the given latitude/longitude columns, as SQL. The cosine
     * term is clamped to [-1, 1] so rounding can't push acos() out of range.
     *
     * @return array{0: string, 1: array<int, float>}
     */
    public function distanceSql(string $latColumn, string $lngColumn): array
    {
        [$least, $greatest] = DB::connection()->getDriverName() === 'sqlite'
            ? ['min', 'max']
            : ['least', 'greatest'];

        $sql = self::EARTH_RADIUS_KM." * acos({$least}(1, {$greatest}(-1, "
            ."cos(radians(?)) * cos(radians({$latColumn})) * cos(radians({$lngColumn}) - radians(?)) "
            ."+ sin(radians(?)) * sin(radians({$latColumn})))))";

        return [$sql, [$this->latitude, $this->longitude, $this->latitude]];
    }

    /** Same formula in PHP, for a single distance. */
    public function distanceTo(float $latitude, float $longitude): float
    {
        $cos = cos(deg2rad($this->latitude)) * cos(deg2rad($latitude)) * cos(deg2rad($longitude) - deg2rad($this->longitude))
            + sin(deg2rad($this->latitude)) * sin(deg2rad($latitude));

        return self::EARTH_RADIUS_KM * acos(max(-1, min(1, $cos)));
    }

    /** "450 m", "1.2 km", "12 km" */
    public static function formatKm(?float $km): ?string
    {
        if ($km === null) {
            return null;
        }

        return match (true) {
            $km < 1 => (int) round($km * 1000, -1).' m',
            $km < 10 => number_format($km, 1).' km',
            default => number_format($km).' km',
        };
    }

    public function toArray(): array
    {
        return ['latitude' => $this->latitude, 'longitude' => $this->longitude, 'label' => $this->label];
    }
}
