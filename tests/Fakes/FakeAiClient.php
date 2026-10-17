<?php

namespace Tests\Fakes;

use App\Services\Ai\AiClient;
use App\Services\Ai\AiResponse;
use App\Services\Ai\AiUnavailableException;

/** Records prompts and answers with canned JSON — tests never call the real API. */
class FakeAiClient implements AiClient
{
    /** @var list<array{system:string, prompt:string}> */
    public array $calls = [];

    /** @param  array<string, mixed>|null  $answer  null makes the client unavailable */
    public function __construct(public ?array $answer = [], public bool $refuse = false) {}

    public function isConfigured(): bool
    {
        return $this->answer !== null;
    }

    public function structured(string $system, string $prompt, array $schema, int $maxTokens = 4000): AiResponse
    {
        $this->calls[] = ['system' => $system, 'prompt' => $prompt];

        if ($this->answer === null) {
            throw new AiUnavailableException('ANTHROPIC_API_KEY is not set.');
        }

        return new AiResponse($this->answer, 'claude-opus-5-5', 1200, 150, $this->refuse);
    }
}
