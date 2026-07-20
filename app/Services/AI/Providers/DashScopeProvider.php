<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\Exceptions\AIAuthException;
use App\Services\AI\Exceptions\AIProviderException;
use App\Services\AI\Exceptions\AIRateLimitException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Alibaba Cloud DashScope provider — OpenAI-compatible endpoint.
 * Docs: https://www.alibabacloud.com/help/en/model-studio/developer-reference/use-qwen-by-calling-api
 *
 * Set in .env:
 *   AI_PROVIDER=dashscope
 *   AI_BASE_URL=https://dashscope-intl.aliyuncs.com/compatible-mode/v1
 *   AI_MODEL=qwen-plus                (or qwen-max, qwen-turbo, qwen3-32b, etc.)
 *   AI_API_KEY=sk-...
 *
 * Note: DashScope uses the same OpenAI-compatible /chat/completions endpoint.
 * For the China region use: https://dashscope.aliyuncs.com/compatible-mode/v1
 */
class DashScopeProvider implements AIProviderInterface
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
        return 'DashScope';
    }

    public function chat(string $systemPrompt, string $userPrompt, array $history = []): string
    {
        if (empty($this->apiKey)) {
            throw new AIProviderException('DashScope API key is not configured (AI_API_KEY).');
        }

        $messages = $this->buildMessages($systemPrompt, $userPrompt, $history);

        Log::debug('DashScopeProvider: sending request', [
            'model'        => $this->model,
            'system_chars' => strlen($systemPrompt),
            'user_chars'   => strlen($userPrompt),
            'history_turns'=> count($history),
        ]);

        // DashScope uses the same Authorization: Bearer <key> scheme as OpenAI
        $response = Http::withToken($this->apiKey)
            ->withHeaders([
                // Required header for DashScope streaming mode (not used here, but accepted)
                'X-DashScope-SSE' => 'disable',
            ])
            ->timeout($this->timeout)
            ->post("{$this->baseUrl}/chat/completions", [
                'model'       => $this->model,
                'temperature' => 0.3,
                'messages'    => $messages,
            ]);

        if ($response->status() === 429) {
            throw new AIRateLimitException('DashScope rate limit exceeded (429).');
        }

        if (in_array($response->status(), [401, 403])) {
            throw new AIAuthException("DashScope auth error {$response->status()}: " . $response->body());
        }

        if (! $response->successful()) {
            throw new AIProviderException(
                "DashScope API error {$response->status()}: " . $response->body()
            );
        }

        return $response->json('choices.0.message.content')
            ?? throw new AIProviderException('DashScope response contained no content.');
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
