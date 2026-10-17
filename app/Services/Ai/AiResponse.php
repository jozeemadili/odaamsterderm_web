<?php

namespace App\Services\Ai;

final class AiResponse
{
    /** @param  array<string, mixed>  $data */
    public function __construct(
        public readonly array $data,
        public readonly string $model,
        public readonly int $inputTokens = 0,
        public readonly int $outputTokens = 0,
        public readonly bool $refused = false,
    ) {}
}
