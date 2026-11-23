<?php

namespace App\Models;

use App\Services\Ai\AiPricing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One customer request to the AI assistant — for usage limits and cost tracking. */
class AiRequest extends Model
{
    public const FEATURE_SEARCH = 'search';

    public const FEATURE_ORDER = 'order';

    public const STATUS_OK = 'ok';

    public const STATUS_REFUSED = 'refused';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id', 'ip_address', 'feature', 'query', 'model', 'status',
        'input_tokens', 'output_tokens', 'cache_read_tokens', 'cache_write_tokens', 'results_count', 'duration_ms', 'error',
    ];

    /** Estimated cost in USD at list prices. */
    public function costUsd(): float
    {
        return AiPricing::usd($this->model, (int) $this->input_tokens, (int) $this->output_tokens, (int) $this->cache_read_tokens, (int) $this->cache_write_tokens);
    }

    public function totalTokens(): int
    {
        return (int) $this->input_tokens + (int) $this->output_tokens + (int) $this->cache_read_tokens + (int) $this->cache_write_tokens;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
