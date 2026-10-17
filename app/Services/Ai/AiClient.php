<?php

namespace App\Services\Ai;

/** A language model that answers with JSON matching a schema. Swapped for a fake in tests. */
interface AiClient
{
    /**
     * @param  string  $system  stable instructions and reference data (cached by the provider)
     * @param  string|list<array{role:string, content:string}>  $prompt  one question, or a conversation ending with the user
     * @param  array<string, mixed>  $schema  JSON schema the answer must match
     *
     * @throws AiUnavailableException when the model can't be reached or isn't configured
     */
    public function structured(string $system, string|array $prompt, array $schema, int $maxTokens = 4000): AiResponse;

    /** Whether credentials are configured. */
    public function isConfigured(): bool;
}
