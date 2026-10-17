<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One customer request to the AI assistant — for usage limits and cost tracking. */
class AiRequest extends Model
{
    public const FEATURE_SEARCH = 'search';

    public const STATUS_OK = 'ok';

    public const STATUS_REFUSED = 'refused';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id', 'ip_address', 'feature', 'query', 'model', 'status',
        'input_tokens', 'output_tokens', 'results_count', 'duration_ms', 'error',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
