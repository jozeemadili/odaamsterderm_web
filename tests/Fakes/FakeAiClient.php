<?php

namespace Tests\Fakes;

use App\Services\Ai\AiClient;
use App\Services\Ai\AiResponse;
use App\Services\Ai\AiUnavailableException;

/** Records prompts and answers with canned JSON — tests never call the real API. */
class FakeAiClient implements AiClient
{
    /** @var list<array{system:string, prompt:string|array}> */
    public array $calls = [];

    /** @var list<array<string, mixed>> answers for successive calls; the last one repeats */
    public array $answers;

    /** @param  array<string, mixed>|null  $answer  null makes the client unavailable */
    public function __construct(public ?array $answer = [], public bool $refuse = false)
    {
        $this->answers = $answer === null ? [] : [$answer];
    }

    /** Queues answers for the following calls (a conversation). */
    public function then(array ...$answers): static
    {
        $this->answers = [...$this->answers, ...$answers];

        return $this;
    }

    public function isConfigured(): bool
    {
        return $this->answer !== null;
    }

    public function structured(string $system, string|array $prompt, array $schema, int $maxTokens = 4000): AiResponse
    {
        $this->calls[] = ['system' => $system, 'prompt' => $prompt];

        if ($this->answer === null) {
            throw new AiUnavailableException('ANTHROPIC_API_KEY is not set.');
        }

        $answer = count($this->answers) > 1 ? array_shift($this->answers) : $this->answers[0];

        return new AiResponse($answer, 'claude-opus-5-5', 1200, 150, $this->refuse);
    }
}
