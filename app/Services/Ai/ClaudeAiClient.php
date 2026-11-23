<?php

namespace App\Services\Ai;

use Anthropic\Client;
use Anthropic\Core\Exceptions\AnthropicException;
use Illuminate\Support\Facades\Log;

/** Claude through the official Anthropic PHP SDK. */
class ClaudeAiClient implements AiClient
{
    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $model,
        private readonly string $effort = 'low',
    ) {}

    public function isConfigured(): bool
    {
        return filled($this->apiKey);
    }

    public function structured(string $system, string|array $prompt, array $schema, int $maxTokens = 4000): AiResponse
    {
        if (! $this->isConfigured()) {
            throw new AiUnavailableException('ANTHROPIC_API_KEY is not set.');
        }

        try {
            $message = (new Client(apiKey: $this->apiKey))->messages->create(
                model: $this->model,
                maxTokens: $maxTokens,
                system: [
                    ['type' => 'text', 'text' => $system, 'cacheControl' => ['type' => 'ephemeral']],
                ],
                messages: is_string($prompt) ? [['role' => 'user', 'content' => $prompt]] : $prompt,
                outputConfig: [
                    'effort' => $this->effort,
                    'format' => ['type' => 'json_schema', 'schema' => $schema],
                ],
            );
        } catch (AnthropicException $e) {
            Log::warning('Claude request failed', ['error' => $e->getMessage()]);

            throw new AiUnavailableException($e->getMessage(), previous: $e);
        }

        // Fresh input, output, and cached prompt tokens apart: they are priced differently.
        $usage = [
            'inputTokens' => (int) $message->usage->inputTokens,
            'outputTokens' => (int) $message->usage->outputTokens,
            'cacheReadTokens' => (int) $message->usage->cacheReadInputTokens,
            'cacheWriteTokens' => (int) $message->usage->cacheCreationInputTokens,
        ];

        if ($message->stopReason === 'refusal') {
            return new AiResponse([], $this->model, ...$usage, refused: true);
        }

        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $data = json_decode($block->text, true);
                if (is_array($data)) {
                    return new AiResponse($data, $this->model, ...$usage);
                }
            }
        }

        throw new AiUnavailableException('The model returned no usable answer (stop reason: '.$message->stopReason.').');
    }
}
