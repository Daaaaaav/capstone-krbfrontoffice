<?php

namespace App\Services\AI\Contracts;

interface AIProviderInterface
{
    /**
     * Send a single chat completion request to the provider.
     *
     * @param  string  $systemPrompt  The system/instruction prompt.
     * @param  string  $userPrompt    The user message.
     * @param  array   $history       Optional prior turns: [['role'=>'user'|'assistant','content'=>string], ...]
     * @return string  The raw text content of the model's reply.
     *
     * @throws \App\Services\AI\Exceptions\AIProviderException  on unrecoverable errors.
     * @throws \App\Services\AI\Exceptions\AIRateLimitException on rate-limit (429) responses.
     */
    public function chat(string $systemPrompt, string $userPrompt, array $history = []): string;

    /**
     * Return a human-readable name for this provider (used in logs).
     */
    public function getName(): string;
}
