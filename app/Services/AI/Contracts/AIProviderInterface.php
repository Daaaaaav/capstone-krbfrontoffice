<?php

namespace App\Services\AI\Contracts;

interface AIProviderInterface
{
    public function chat(string $systemPrompt, string $userPrompt, array $history = []): string;
    public function getName(): string;
}
