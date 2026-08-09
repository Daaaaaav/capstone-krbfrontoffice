<?php

namespace App\Services\AI;

use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\Exceptions\AIAuthException;
use App\Services\AI\Exceptions\AIProviderException;
use App\Services\AI\Exceptions\AIRateLimitException;
use App\Services\AI\Providers\DashScopeProvider;
use App\Services\AI\Providers\GroqProvider;
use App\Services\AI\Providers\OpenRouterProvider;
use App\Services\AI\Providers\SiliconFlowProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AIService
{
    private int $healthTtl;
    private int $retryDelay;
    private int $maxAttempts;
    private array $priorityList;

    public function __construct()
    {
        $this->healthTtl    = (int) config('ai.health_ttl_seconds', 300);
        $this->retryDelay   = (int) config('ai.retry_delay_seconds', 1);
        $this->maxAttempts  = (int) config('ai.max_attempts', 2);
        $this->priorityList = $this->buildPriorityList();
    }

    public function chat(string $systemPrompt, string $userPrompt, array $history = []): string
    {
        if (config('app.debug')) {
            $estimatedTokens = $this->estimateTokens($systemPrompt, $userPrompt, $history);
            
            Log::info('AIService: chat() called', [
                'stage'             => 'provider_selection',
                'priority_list'     => $this->priorityList,
                'history_turns'     => count($history),
                'system_chars'      => strlen($systemPrompt),
                'user_chars'        => strlen($userPrompt),
                'estimated_tokens'  => $estimatedTokens,
            ]);
        }

        foreach ($this->priorityList as $providerName) {
            if ($this->isUnhealthy($providerName)) {
                if (config('app.debug')) {
                    Log::info('AIService: skipping unhealthy provider', [
                        'stage'    => 'provider_selection',
                        'provider' => $providerName,
                        'reason'   => 'health_cache_hit',
                    ]);
                }
                continue;
            }

            $model    = $this->resolveModel($providerName);
            if (config('app.debug')) {
                Log::info('AIService: selected provider', [
                    'stage'    => 'provider_selection',
                    'provider' => $providerName,
                    'model'    => $model,
                ]);
            }

            $provider = $this->buildProvider($providerName);
            $result   = $this->attemptProvider($provider, $providerName, $model, $systemPrompt, $userPrompt, $history);

            if ($result !== null) {
                return $result;
            }
        }

        Log::error('AIService: all providers in the failover chain failed', [
            'stage'         => 'provider_selection',
            'priority_list' => $this->priorityList,
        ]);
        return $this->genericErrorMessage();
    }

    public function getProviderName(): string
    {
        foreach ($this->priorityList as $name) {
            if (! $this->isUnhealthy($name)) {
                return ucfirst($name);
            }
        }
        return ucfirst($this->priorityList[0] ?? 'unknown');
    }

    public function getProviderHealth(): array
    {
        $status = [];
        foreach ($this->priorityList as $name) {
            $status[$name] = $this->isUnhealthy($name) ? 'unhealthy' : 'healthy';
        }
        return $status;
    }

    private function attemptProvider(
        AIProviderInterface $provider,
        string              $providerName,
        string              $resolvedModel,
        string              $systemPrompt,
        string              $userPrompt,
        array               $history
    ): ?string {
        $attempt = 0;

        while ($attempt < $this->maxAttempts) {
            $attempt++;

            if (config('app.debug')) {
                $estimatedTokens = $this->estimateTokens($systemPrompt, $userPrompt, $history);
                
                Log::info('AIService: sending request', [
                    'stage'            => 'api_request',
                    'provider'         => $providerName,
                    'model'            => $resolvedModel,
                    'attempt'          => "{$attempt}/{$this->maxAttempts}",
                    'system_chars'     => strlen($systemPrompt),
                    'user_chars'       => strlen($userPrompt),
                    'history_turns'    => count($history),
                    'estimated_tokens' => $estimatedTokens,
                ]);
            }

            try {
                $reply = $provider->chat($systemPrompt, $userPrompt, $history);

                if (config('app.debug')) {
                    Log::info('AIService: request succeeded', [
                        'stage'       => 'api_request',
                        'provider'    => $providerName,
                        'model'       => $resolvedModel,
                        'reply_chars' => strlen($reply),
                    ]);
                }

                $this->clearUnhealthy($providerName);
                return $reply;

            } catch (AIAuthException $e) {
                Log::warning('AIService: auth error — skipping provider', [
                    'stage'    => 'api_request',
                    'provider' => $providerName,
                    'model'    => $resolvedModel,
                    'http'     => $this->extractHttpStatus($e),
                    'error'    => $e->getMessage(),
                    'action'   => 'skip_provider_no_retry',
                ]);
                $this->markUnhealthy($providerName);
                return null;

            } catch (AIRateLimitException $e) {
                Log::warning('AIService: rate limited — trying next provider', [
                    'stage'    => 'api_request',
                    'provider' => $providerName,
                    'model'    => $resolvedModel,
                    'http'     => 429,
                    'error'    => $e->getMessage(),
                    'action'   => 'failover_next_provider',
                ]);
                $this->markUnhealthy($providerName);
                return null;

            } catch (AIProviderException $e) {
                Log::warning('AIService: provider error', [
                    'stage'           => 'api_request',
                    'provider'        => $providerName,
                    'model'           => $resolvedModel,
                    'http'            => $this->extractHttpStatus($e),
                    'attempt'         => "{$attempt}/{$this->maxAttempts}",
                    'error'           => $e->getMessage(),
                    'action'          => $attempt >= $this->maxAttempts ? 'failover_next_provider' : 'retry',
                ]);

                if ($attempt >= $this->maxAttempts) {
                    $this->markUnhealthy($providerName);
                    return null;
                }

                if (config('app.debug')) {
                    Log::info('AIService: waiting before retry', [
                        'stage'    => 'api_request',
                        'provider' => $providerName,
                        'delay_s'  => $this->retryDelay,
                    ]);
                }

                if ($this->retryDelay > 0) {
                    sleep($this->retryDelay);
                }

            } catch (\Throwable $e) {
                Log::error('AIService: unexpected exception', [
                    'stage'    => 'api_request',
                    'provider' => $providerName,
                    'model'    => $resolvedModel,
                    'attempt'  => "{$attempt}/{$this->maxAttempts}",
                    'class'    => get_class($e),
                    'error'    => $e->getMessage(),
                    'file'     => $e->getFile() . ':' . $e->getLine(),
                    'action'   => $attempt >= $this->maxAttempts ? 'failover_next_provider' : 'retry',
                ]);

                if ($attempt >= $this->maxAttempts) {
                    $this->markUnhealthy($providerName);
                    return null;
                }

                if ($this->retryDelay > 0) {
                    sleep($this->retryDelay);
                }
            }
        }

        return null;
    }

    private function buildProvider(string $name): AIProviderInterface
    {
        $creds   = config("ai.credentials.{$name}", []);
        $apiKey  = (string) ($creds['api_key']  ?? config('ai.api_key', ''));
        $baseUrl = (string) ($creds['base_url'] ?? '');
        $model   = $this->resolveModel($name);
        $timeout = (int) config('ai.timeout', 30);

        if (config('app.debug')) {
            Log::info('AIService: resolved model', [
                'stage'    => 'model_resolution',
                'provider' => $name,
                'model'    => $model,
                'api_key_set' => $apiKey !== '',
            ]);
        }

        return match ($name) {
            'openrouter'  => new OpenRouterProvider($apiKey,  $model, $baseUrl ?: 'https://openrouter.ai/api/v1',                           $timeout),
            'siliconflow' => new SiliconFlowProvider($apiKey, $model, $baseUrl ?: 'https://api.siliconflow.cn/v1',                           $timeout),
            'dashscope'   => new DashScopeProvider($apiKey,   $model, $baseUrl ?: 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1',  $timeout),
            default       => new GroqProvider($apiKey,        $model, $baseUrl ?: 'https://api.groq.com/openai/v1',                          $timeout),
        };
    }

    private function resolveModel(string $providerName): string
    {
        $modelConfig = (string) config('ai.model', 'qwen/qwen3-32b');
        $aliases     = (array)  config('ai.model_aliases', []);

        if (isset($aliases[$modelConfig][$providerName])) {
            return $aliases[$modelConfig][$providerName];
        }
        return $modelConfig;
    }

    private function buildPriorityList(): array
    {
        $list = (array) config('ai.provider_priority', [config('ai.provider', 'groq')]);
        $list = array_filter(array_map('strtolower', array_map('trim', $list)));
        return array_values($list ?: ['groq']);
    }

    private function healthKey(string $provider): string
    {
        return "ai_provider_health_{$provider}";
    }

    private function isUnhealthy(string $provider): bool
    {
        if ($this->healthTtl <= 0) {
            return false;
        }
        return Cache::has($this->healthKey($provider));
    }

    private function markUnhealthy(string $provider): void
    {
        if ($this->healthTtl <= 0) {
            return;
        }
        Cache::put($this->healthKey($provider), true, $this->healthTtl);
        Log::warning('AIService: provider marked unhealthy', [
            'stage'    => 'health_cache',
            'provider' => $provider,
            'ttl_s'    => $this->healthTtl,
        ]);
    }

    private function clearUnhealthy(string $provider): void
    {
        Cache::forget($this->healthKey($provider));
    }

    private function genericErrorMessage(): string
    {
        return "Sorry, I could not reach the AI service right now. "
             . "Please check your connection and try again in a moment.";
    }

    private function extractHttpStatus(\Throwable $e): ?int
    {
        if (preg_match('/\b(4\d{2}|5\d{2})\b/', $e->getMessage(), $m)) {
            return (int) $m[1];
        }
        return null;
    }

    private function estimateTokens(string $systemPrompt, string $userPrompt, array $history): int
    {
        $totalChars = strlen($systemPrompt) + strlen($userPrompt);
        
        foreach ($history as $turn) {
            $totalChars += strlen($turn['content'] ?? '');
        }
        
        return (int) ceil($totalChars / 4);
    }
}
