<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Platform-wide switches administrators change at runtime (no deploy).
 * Values are JSON; reads are cached and cleared on write.
 */
class PlatformSetting extends Model
{
    public const AI_SEARCH_ENABLED = 'ai.search_enabled';

    public const AI_SEARCH_GUESTS = 'ai.search_guests';

    public const AI_DAILY_LIMIT = 'ai.daily_limit';

    /** Used when a key has never been saved. */
    public const DEFAULTS = [
        self::AI_SEARCH_ENABLED => false,
        self::AI_SEARCH_GUESTS => true,
        self::AI_DAILY_LIMIT => 500,
    ];

    private const CACHE_KEY = 'platform_settings';

    protected $fillable = ['key', 'value', 'updated_by'];

    protected $casts = ['value' => 'json'];

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = Cache::rememberForever(self::CACHE_KEY, fn () => static::query()->pluck('value', 'key')->all());

        return array_key_exists($key, $all) ? $all[$key] : ($default ?? self::DEFAULTS[$key] ?? null);
    }

    public static function set(string $key, mixed $value, ?User $by = null): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value, 'updated_by' => $by?->id]);
        Cache::forget(self::CACHE_KEY);
    }

    protected static function booted(): void
    {
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }
}
