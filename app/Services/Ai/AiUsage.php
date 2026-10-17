<?php

namespace App\Services\Ai;

use App\Models\AiRequest;
use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/** Shared limits and the request log for every AI feature. */
class AiUsage
{
    /** Requests per minute per customer (or per IP for guests), per feature. */
    public const PER_MINUTE = 8;

    /**
     * Counts one attempt; false when this person is over the per-minute
     * limit or the platform is over the admin's daily limit.
     */
    public function attempt(string $feature, ?User $user, ?string $ip): bool
    {
        $key = "ai-{$feature}:".($user ? 'u'.$user->id : 'ip'.$ip);
        $dailyLimit = (int) PlatformSetting::get(PlatformSetting::AI_DAILY_LIMIT);

        if (RateLimiter::tooManyAttempts($key, self::PER_MINUTE)) {
            return false;
        }
        if ($dailyLimit > 0 && AiRequest::where('created_at', '>=', today())->count() >= $dailyLimit) {
            return false;
        }

        RateLimiter::hit($key, 60);

        return true;
    }

    public function recordFailure(string $feature, string $query, ?User $user, ?string $ip, float $started, string $error): void
    {
        AiRequest::create([
            'user_id' => $user?->id,
            'ip_address' => $ip,
            'feature' => $feature,
            'query' => Str::limit($query, 490),
            'status' => AiRequest::STATUS_FAILED,
            'error' => Str::limit($error, 250),
            'duration_ms' => (int) ((microtime(true) - $started) * 1000),
        ]);
    }

    public function record(string $feature, string $query, ?User $user, ?string $ip, float $started, AiResponse $response, int $results): void
    {
        AiRequest::create([
            'user_id' => $user?->id,
            'ip_address' => $ip,
            'feature' => $feature,
            'query' => Str::limit($query, 490),
            'model' => $response->model,
            'status' => $response->refused ? AiRequest::STATUS_REFUSED : AiRequest::STATUS_OK,
            'input_tokens' => $response->inputTokens,
            'output_tokens' => $response->outputTokens,
            'results_count' => $results,
            'duration_ms' => (int) ((microtime(true) - $started) * 1000),
        ]);
    }
}
