<?php

namespace App\Services\AI;

use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\Exceptions\AIProviderException;
use App\Services\AI\Exceptions\AIRateLimitException;
use App\Services\AI\Providers\DashScopeProvider;
use App\Services\AI\Providers\GroqProvider;
use App\Services\AI\Providers\OpenRouterProvider;
use App\Services\AI\Providers\SiliconFlowProvider;
use Illuminate\Support\Facades\Log;

/**
 * Central AI service.
 *
 * Resolves the configured provider, wraps every call with retry logic,
 * rate-limit handling, timeout, and structured logging.
 *
 * The chatbot always talks to AIService — never directly to a provider.
 *
 * Configuration (config/ai.php, driven by .env):
 *   AI_PROVIDER  = groq | openrouter | siliconflow | dashscope
 *   AI_API_KEY   = <key>
 *   AI_MODEL     = <model slug>
 *   AI_BASE_URL  = <base URL for the provider>
 */
class AIService
{
    private AIProviderInterface $provider;

    /** Maximum number of attempts before giving up. */
    private int $maxAttempts;

    /** Seconds to wait between retry attempts. */
    private int $retryDelay;

    public function __construct()
    {
        $this->provider    = $this->resolveProvider();
        $this->maxAttempts = (int) config('ai.max_attempts', 3);
        $this->retryDelay  = (int) config('ai.retry_delay_seconds', 2);
    }

    // ──────────────────────────────────────────────────────────
    // Public API
    // ──────────────────────────────────────────────────────────

    /**
     * Send a chat completion request.
     *
     * @param  string  $systemPrompt  The system / instruction prompt.
     * @param  string  $userPrompt    The user's current message.
     * @param  array   $history       Optional prior turns for multi-turn context.
     *                                Each entry: ['role' => 'user'|'assistant', 'content' => string]
     * @return string  The model's reply text (raw — callers strip think-blocks / parse JSON).
     */
    public function chat(string $systemPrompt, string $userPrompt, array $history = []): string
    {
        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                $providerName = $this->provider->getName();

                Log::info("AIService [{$providerName}]: attempt {$attempt}", [
                    'model'         => config('ai.model'),
                    'system_chars'  => strlen($systemPrompt),
                    'user_chars'    => strlen($userPrompt),
                    'history_turns' => count($history),
                ]);

                $reply = $this->provider->chat($systemPrompt, $userPrompt, $history);

                Log::info("AIService [{$providerName}]: success on attempt {$attempt}", [
                    'reply_chars' => strlen($reply),
                ]);

                return $reply;

            } catch (AIRateLimitException $e) {
                // Rate limit is never retried — return a friendly message immediately.
                Log::warning("AIService: rate limit hit on attempt {$attempt}", [
                    'provider' => $this->provider->getName(),
                    'error'    => $e->getMessage(),
                ]);

                return $this->rateLimitMessage();

            } catch (AIProviderException $e) {
                Log::warning("AIService: provider error on attempt {$attempt}", [
                    'provider' => $this->provider->getName(),
                    'error'    => $e->getMessage(),
                ]);

                if ($attempt >= $this->maxAttempts) {
                    Log::error("AIService: all {$this->maxAttempts} attempts failed", [
                        'provider' => $this->provider->getName(),
                        'last_error' => $e->getMessage(),
                    ]);

                    return $this->genericErrorMessage();
                }

                // Wait before retrying
                if ($this->retryDelay > 0) {
                    sleep($this->retryDelay);
                }

            } catch (\Throwable $e) {
                // Unexpected exceptions (network timeout, SSL, etc.)
                Log::error("AIService: unexpected exception on attempt {$attempt}", [
                    'provider' => $this->provider->getName(),
                    'error'    => $e->getMessage(),
                    'class'    => get_class($e),
                ]);

                if ($attempt >= $this->maxAttempts) {
                    return $this->genericErrorMessage();
                }

                if ($this->retryDelay > 0) {
                    sleep($this->retryDelay);
                }
            }
        }
    }

    /**
     * Expose the underlying provider name for logging / display.
     */
    public function getProviderName(): string
    {
        return $this->provider->getName();
    }

    // ──────────────────────────────────────────────────────────
    // Provider resolution
    // ──────────────────────────────────────────────────────────

    private function resolveProvider(): AIProviderInterface
    {
        $name    = strtolower((string) config('ai.provider', 'groq'));
        $apiKey  = (string) config('ai.api_key', '');
        $model   = (string) config('ai.model', 'qwen/qwen3-32b');
        $baseUrl = (string) config('ai.base_url', 'https://api.groq.com/openai/v1');
        $timeout = (int)    config('ai.timeout', 30);

        return match ($name) {
            'openrouter'  => new OpenRouterProvider($apiKey,  $model, $baseUrl ?: 'https://openrouter.ai/api/v1',                             $timeout),
            'siliconflow' => new SiliconFlowProvider($apiKey, $model, $baseUrl ?: 'https://api.siliconflow.cn/v1',                             $timeout),
            'dashscope'   => new DashScopeProvider($apiKey,   $model, $baseUrl ?: 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1',    $timeout),
            default       => new GroqProvider($apiKey,        $model, $baseUrl ?: 'https://api.groq.com/openai/v1',                            $timeout),
        };
    }

    // ──────────────────────────────────────────────────────────
    // User-facing error messages
    // ──────────────────────────────────────────────────────────

    private function rateLimitMessage(): string
    {
        return "The AI assistant is temporarily busy because the API rate limit has been reached.\n\n"
             . "Please wait about 20 seconds and try again.";
    }

    private function genericErrorMessage(): string
    {
        return "Sorry, I could not reach the AI service right now. "
             . "Please check your connection and try again.";
    }
}
