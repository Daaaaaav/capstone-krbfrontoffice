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

/**
 * Central AI service — v2.
 *
 * Enhancements over v1:
 *  - Automatic provider failover through AI_PROVIDER_PRIORITY chain.
 *  - Per-provider health cache: unhealthy providers are skipped temporarily.
 *  - Logical model alias resolution (e.g. "qwen_32b" → provider-specific slug).
 *  - Non-retryable auth errors skip directly to the next provider.
 *  - All existing callers (ChatModal, GroqService shim) continue to work unchanged.
 */
class AIService
{
    /** Seconds to cache a provider as "unhealthy" after a hard failure. */
    private int $healthTtl;

    /** Seconds between per-provider retry attempts. */
    private int $retryDelay;

    /** Attempts per provider before moving to the next one. */
    private int $maxAttempts;

    /** Ordered list of provider names to try. */
    private array $priorityList;

    public function __construct()
    {
        $this->healthTtl    = (int) config('ai.health_ttl_seconds', 300);
        $this->retryDelay   = (int) config('ai.retry_delay_seconds', 1);
        $this->maxAttempts  = (int) config('ai.max_attempts', 2);
        $this->priorityList = $this->buildPriorityList();
    }

    // ──────────────────────────────────────────────────────────
    // Public API (identical signature to v1)
    // ──────────────────────────────────────────────────────────

    /**
     * Send a chat completion request with automatic provider failover.
     *
     * @param  string  $systemPrompt
     * @param  string  $userPrompt
     * @param  array   $history       Multi-turn history: [['role'=>'user'|'assistant','content'=>string]]
     * @return string  Raw model reply text.
     */
    public function chat(string $systemPrompt, string $userPrompt, array $history = []): string
    {
        foreach ($this->priorityList as $providerName) {
            // Skip providers currently marked unhealthy
            if ($this->isUnhealthy($providerName)) {
                Log::info("AIService: skipping unhealthy provider [{$providerName}]");
                continue;
            }

            $provider = $this->buildProvider($providerName);
            $result   = $this->attemptProvider($provider, $providerName, $systemPrompt, $userPrompt, $history);

            if ($result !== null) {
                return $result;
            }
            // null means this provider failed — continue to next in chain
        }

        // All providers exhausted
        Log::error('AIService: all providers in the failover chain failed', [
            'priority_list' => $this->priorityList,
        ]);
        return $this->genericErrorMessage();
    }

    /**
     * Return the name of the first healthy provider in the priority list.
     */
    public function getProviderName(): string
    {
        foreach ($this->priorityList as $name) {
            if (! $this->isUnhealthy($name)) {
                return ucfirst($name);
            }
        }
        return ucfirst($this->priorityList[0] ?? 'unknown');
    }

    /**
     * Return current health status of all configured providers.
     * Useful for admin dashboards / health endpoints.
     */
    public function getProviderHealth(): array
    {
        $status = [];
        foreach ($this->priorityList as $name) {
            $status[$name] = $this->isUnhealthy($name) ? 'unhealthy' : 'healthy';
        }
        return $status;
    }

    // ──────────────────────────────────────────────────────────
    // Per-provider attempt loop
    // ──────────────────────────────────────────────────────────

    /**
     * Attempt up to $maxAttempts calls on a single provider.
     * Returns the reply string on success, or null to signal "try next provider".
     */
    private function attemptProvider(
        AIProviderInterface $provider,
        string              $providerName,
        string              $systemPrompt,
        string              $userPrompt,
        array               $history
    ): ?string {
        $attempt = 0;

        while ($attempt < $this->maxAttempts) {
            $attempt++;

            try {
                Log::info("AIService [{$providerName}]: attempt {$attempt}/{$this->maxAttempts}", [
                    'model'         => $this->resolveModel($providerName),
                    'system_chars'  => strlen($systemPrompt),
                    'user_chars'    => strlen($userPrompt),
                    'history_turns' => count($history),
                ]);

                $reply = $provider->chat($systemPrompt, $userPrompt, $history);

                Log::info("AIService [{$providerName}]: success", ['reply_chars' => strlen($reply)]);
                $this->clearUnhealthy($providerName);

                return $reply;

            } catch (AIAuthException $e) {
                // Auth error — skip this provider entirely, no retries
                Log::warning("AIService [{$providerName}]: auth error — skipping provider", [
                    'error' => $e->getMessage(),
                ]);
                $this->markUnhealthy($providerName);
                return null;

            } catch (AIRateLimitException $e) {
                // Rate limit — mark unhealthy, move to next provider immediately
                Log::warning("AIService [{$providerName}]: rate limited — trying next provider", [
                    'error' => $e->getMessage(),
                ]);
                $this->markUnhealthy($providerName);
                return null;

            } catch (AIProviderException $e) {
                Log::warning("AIService [{$providerName}]: provider error on attempt {$attempt}", [
                    'error' => $e->getMessage(),
                ]);

                if ($attempt >= $this->maxAttempts) {
                    // Hard failure — mark unhealthy, try next provider
                    $this->markUnhealthy($providerName);
                    return null;
                }

                if ($this->retryDelay > 0) {
                    sleep($this->retryDelay);
                }

            } catch (\Throwable $e) {
                Log::error("AIService [{$providerName}]: unexpected error on attempt {$attempt}", [
                    'error' => $e->getMessage(),
                    'class' => get_class($e),
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

    // ──────────────────────────────────────────────────────────
    // Provider construction
    // ──────────────────────────────────────────────────────────

    private function buildProvider(string $name): AIProviderInterface
    {
        $creds   = config("ai.credentials.{$name}", []);
        $apiKey  = (string) ($creds['api_key']  ?? config('ai.api_key', ''));
        $baseUrl = (string) ($creds['base_url'] ?? '');
        $model   = $this->resolveModel($name);
        $timeout = (int) config('ai.timeout', 30);

        return match ($name) {
            'openrouter'  => new OpenRouterProvider($apiKey,  $model, $baseUrl ?: 'https://openrouter.ai/api/v1',                           $timeout),
            'siliconflow' => new SiliconFlowProvider($apiKey, $model, $baseUrl ?: 'https://api.siliconflow.cn/v1',                           $timeout),
            'dashscope'   => new DashScopeProvider($apiKey,   $model, $baseUrl ?: 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1',  $timeout),
            default       => new GroqProvider($apiKey,        $model, $baseUrl ?: 'https://api.groq.com/openai/v1',                          $timeout),
        };
    }

    /**
     * Resolve the model slug for a specific provider.
     * Handles logical aliases (e.g. "qwen_32b") and raw slugs.
     */
    private function resolveModel(string $providerName): string
    {
        $modelConfig = (string) config('ai.model', 'qwen/qwen3-32b');
        $aliases     = (array)  config('ai.model_aliases', []);

        // Check if the configured model is a logical alias
        if (isset($aliases[$modelConfig][$providerName])) {
            return $aliases[$modelConfig][$providerName];
        }

        // Not an alias — use the raw slug as-is for all providers
        return $modelConfig;
    }

    // ──────────────────────────────────────────────────────────
    // Priority list
    // ──────────────────────────────────────────────────────────

    private function buildPriorityList(): array
    {
        $list = (array) config('ai.provider_priority', [config('ai.provider', 'groq')]);
        $list = array_filter(array_map('strtolower', array_map('trim', $list)));
        return array_values($list ?: ['groq']);
    }

    // ──────────────────────────────────────────────────────────
    // Health cache
    // ──────────────────────────────────────────────────────────

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
        Log::warning("AIService: marked [{$provider}] as unhealthy for {$this->healthTtl}s");
    }

    private function clearUnhealthy(string $provider): void
    {
        Cache::forget($this->healthKey($provider));
    }

    // ──────────────────────────────────────────────────────────
    // User-facing messages
    // ──────────────────────────────────────────────────────────

    private function genericErrorMessage(): string
    {
        return "Sorry, I could not reach the AI service right now. "
             . "Please check your connection and try again in a moment.";
    }
}
