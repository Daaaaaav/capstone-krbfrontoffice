<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\Exceptions\AIAuthException;
use App\Services\AI\Exceptions\AIProviderException;
use App\Services\AI\Exceptions\AIRateLimitException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OpenRouter provider — OpenAI-compatible endpoint supporting many models.
 * Docs: https://openrouter.ai/docs
 *
 * Set in .env:
 *   AI_PROVIDER=openrouter
 *   AI_BASE_URL=https://openrouter.ai/api/v1
 *   AI_MODEL=qwen/qwen3-32b          (or any model slug from openrouter.ai/models)
 *   AI_API_KEY=sk-or-...
 */
class OpenRouterProvider implements AIProviderInterface
{
    private string $apiKey;
    private string $model;
    private string $baseUrl;
    private int    $timeout;

    public function __construct(string $apiKey, string $model, string $baseUrl, int $timeout = 30)
    {
        $this->apiKey  = $apiKey;
        $this->model   = $model;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->timeout = $timeout;
    }

    public function getName(): string
    {
        return 'OpenRouter';
    }

    public function chat(string $systemPrompt, string $userPrompt, array $history = []): string
    {
        if (empty($this->apiKey)) {
            throw new AIProviderException('OpenRouter API key is not configured (AI_API_KEY).');
        }

        $messages = $this->buildMessages($systemPrompt, $userPrompt, $history);

        Log::debug('OpenRouterProvider: sending request', [
            'model'        => $this->model,
            'system_chars' => strlen($systemPrompt),
            'user_chars'   => strlen($userPrompt),
            'history_turns'=> count($history),
        ]);

        $response = Http::withToken($this->apiKey)
            ->withHeaders([
                'HTTP-Referer' => config('app.url', 'https://localhost'),
                'X-Title'      => config('app.name', 'KRB System'),
            ])
            ->timeout($this->timeout)
            ->post("{$this->baseUrl}/chat/completions", [
                'model'       => $this->model,
                'temperature' => 0.3,
                'messages'    => $messages,
            ]);

        if ($response->status() === 429) {
            throw new AIRateLimitException('OpenRouter rate limit exceeded (429).');
        }

        if (in_array($response->status(), [401, 403])) {
            throw new AIAuthException("OpenRouter auth error {$response->status()}: " . $response->body());
        }

        if (! $response->successful()) {
            throw new AIProviderException(
                "OpenRouter API error {$response->status()}: " . $response->body()
            );
        }

        return $response->json('choices.0.message.content')
            ?? throw new AIProviderException('OpenRouter response contained no content.');
    }

    private function buildMessages(string $system, string $user, array $history): array
    {
        $msgs = [['role' => 'system', 'content' => $system]];

        foreach ($history as $turn) {
            if (isset($turn['role'], $turn['content'])) {
                $msgs[] = ['role' => $turn['role'], 'content' => $turn['content']];
            }
        }

        $msgs[] = ['role' => 'user', 'content' => $user];

        return $msgs;
    }
}
