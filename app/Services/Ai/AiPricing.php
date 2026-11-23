<?php

namespace App\Services\Ai;

/**
 * Anthropic list prices in USD per million tokens, for the usage estimate.
 * Cache reads are a fraction of input; 5-minute cache writes are 1.25x input.
 */
class AiPricing
{
    /** model => [input, output, cache read, cache write (5 min)] */
    public const PRICES = [
        'claude-opus-5-5' => [4.00, 20.00, 0.20, 5.00],
        'claude-sonnet-5-5' => [2.00, 10.00, 0.10, 2.50],
        'claude-haiku-5-5' => [0.10, 0.50, 0.01, 0.125],
    ];

    public static function known(?string $model): bool
    {
        return isset(self::PRICES[(string) $model]);
    }

    /** Estimated USD for one request (or summed tokens of many). */
    public static function usd(?string $model, int|float $input, int|float $output, int|float $cacheRead = 0, int|float $cacheWrite = 0): float
    {
        [$in, $out, $read, $write] = self::PRICES[(string) $model] ?? [0, 0, 0, 0];

        return ($input * $in + $output * $out + $cacheRead * $read + $cacheWrite * $write) / 1_000_000;
    }
}
