<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\Exceptions\AIAuthException;
use App\Services\AI\Exceptions\AIProviderException;
use App\Services\AI\Exceptions\AIRateLimitException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SiliconFlow provider — OpenAI-compatible endpoint.
 * Docs: https://docs.siliconflow.cn/en/docs/api-reference/chat-completions
 *
 * Set in .env:
 *   AI_PROVIDER=siliconflow
 *   AI_BASE_URL=https://api.siliconflow.cn/v1
 *   AI_MODEL=Qwen/Qwen3-32B          (SiliconFlow model slug)
 *   AI_API_KEY=sk-...
 */
class SiliconFlowProvider implements AIProviderInterface
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
        return 'SiliconFlow';
    }

    public function chat(string $systemPrompt, string $userPrompt, array $history = []): string
    {
        if (empty($this->apiKey)) {
            throw new AIProviderException('SiliconFlow API key is not configured (AI_API_KEY).');
        }

        $messages = $this->buildMessages($systemPrompt, $userPrompt, $history);

        Log::debug('SiliconFlowProvider: sending request', [
            'model'        => $this->model,
            'system_chars' => strlen($systemPrompt),
            'user_chars'   => strlen($userPrompt),
            'history_turns'=> count($history),
        ]);

        $response = Http::withToken($this->apiKey)
            ->timeout($this->timeout)
            ->post("{$this->baseUrl}/chat/completions", [
                'model'       => $this->model,
                'temperature' => 0.3,
                'messages'    => $messages,
            ]);

        if ($response->status() === 429) {
            throw new AIRateLimitException('SiliconFlow rate limit exceeded (429).');
        }

        if (in_array($response->status(), [401, 403])) {
            throw new AIAuthException("SiliconFlow auth error {$response->status()}: " . $response->body());
        }

        if (! $response->successful()) {
            throw new AIProviderException(
                "SiliconFlow API error {$response->status()}: " . $response->body()
            );
        }

        return $response->json('choices.0.message.content')
            ?? throw new AIProviderException('SiliconFlow response contained no content.');
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
