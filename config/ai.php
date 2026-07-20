<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Primary AI Provider (legacy single-provider mode)
    |--------------------------------------------------------------------------
    | Still used as the first entry in the priority list when
    | AI_PROVIDER_PRIORITY is not set.
    */
    'provider' => env('AI_PROVIDER', 'groq'),

    /*
    |--------------------------------------------------------------------------
    | Provider Priority / Failover Chain
    |--------------------------------------------------------------------------
    | Comma-separated list of providers to try in order.
    | AIService will advance to the next provider on: 429, 500, 502, 503,
    | timeout, or network errors. It will NOT retry on 401/403 (auth errors).
    |
    | Example:
    |   AI_PROVIDER_PRIORITY=groq,openrouter,siliconflow,dashscope
    |
    | Leave blank to fall back to AI_PROVIDER (single-provider mode).
    */
    'provider_priority' => array_filter(
        array_map('trim', explode(',', env('AI_PROVIDER_PRIORITY', env('AI_PROVIDER', 'groq'))))
    ),

    /*
    |--------------------------------------------------------------------------
    | Per-Provider Credentials
    |--------------------------------------------------------------------------
    | Each provider reads its own env key. AI_API_KEY is the universal key
    | (used when a per-provider key is not set). Legacy GROQ_API_KEY still
    | works as a fallback so existing deployments need no changes.
    */
    'credentials' => [
        'groq' => [
            'api_key'  => env('GROQ_API_KEY',        env('AI_API_KEY', '')),
            'base_url' => env('GROQ_BASE_URL',        'https://api.groq.com/openai/v1'),
        ],
        'openrouter' => [
            'api_key'  => env('OPENROUTER_API_KEY',   env('AI_API_KEY', '')),
            'base_url' => env('OPENROUTER_BASE_URL',  'https://openrouter.ai/api/v1'),
        ],
        'siliconflow' => [
            'api_key'  => env('SILICONFLOW_API_KEY',  env('AI_API_KEY', '')),
            'base_url' => env('SILICONFLOW_BASE_URL', 'https://api.siliconflow.cn/v1'),
        ],
        'dashscope' => [
            'api_key'  => env('DASHSCOPE_API_KEY',    env('AI_API_KEY', '')),
            'base_url' => env('DASHSCOPE_BASE_URL',   'https://dashscope-intl.aliyuncs.com/compatible-mode/v1'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logical Model Aliases
    |--------------------------------------------------------------------------
    | Reference models by logical name (e.g. "qwen_32b") in code.
    | AIService resolves the alias to the provider-specific slug at call time.
    |
    | Set AI_MODEL to a logical alias OR a raw provider slug.
    | If the value is not found in aliases, it is used verbatim.
    */
    'model' => env('AI_MODEL', env('GROQ_MODEL', 'qwen/qwen3-32b')),

    'model_aliases' => [
        // Logical alias => [provider => model_slug]
        'qwen_32b' => [
            'groq'        => 'llama-3.3-70b-versatile', // qwen/qwen3-32b not on Groq; use best available
            'openrouter'  => 'qwen/qwen3-32b',
            'siliconflow' => 'Qwen/Qwen3-32B',
            'dashscope'   => 'qwen3-32b',
        ],
        'qwen_plus' => [
            'groq'        => 'llama-3.3-70b-versatile', // qwen/qwen3-32b not on Groq; use best available
            'openrouter'  => 'qwen/qwen3-32b',
            'siliconflow' => 'Qwen/Qwen3-32B',
            'dashscope'   => 'qwen-plus',
        ],
        'llama_70b' => [
            'groq'        => 'llama-3.3-70b-versatile',
            'openrouter'  => 'meta-llama/llama-3.3-70b-instruct',
            'siliconflow' => 'meta-llama/Meta-Llama-3.1-70B-Instruct',
            'dashscope'   => 'llama3.3-70b-instruct',
        ],
        // Raw slug alias — catches deployments still using the literal value
        // "qwen/qwen3-32b" in AI_MODEL / GROQ_MODEL and silently remaps Groq.
        'qwen/qwen3-32b' => [
            'groq'        => 'llama-3.3-70b-versatile',
            'openrouter'  => 'qwen/qwen3-32b',
            'siliconflow' => 'Qwen/Qwen3-32B',
            'dashscope'   => 'qwen3-32b',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    */
    'timeout' => (int) env('AI_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Retry / Failover Configuration
    |--------------------------------------------------------------------------
    | max_attempts : retries per provider before moving to the next one.
    | retry_delay  : seconds between retries on the same provider.
    |
    | HTTP statuses that trigger failover to next provider:
    |   429 (rate limit), 500, 502, 503, 504, timeout, network error.
    | HTTP statuses that do NOT retry (fail immediately):
    |   401, 403 (auth errors) — skip this provider, try next.
    */
    'max_attempts'        => (int) env('AI_MAX_ATTEMPTS', 2),
    'retry_delay_seconds' => (int) env('AI_RETRY_DELAY', 1),

    /*
    |--------------------------------------------------------------------------
    | Provider Health Cache
    |--------------------------------------------------------------------------
    | health_ttl_seconds : how long to mark a provider "unhealthy" after a
    |                       hard failure (5xx / timeout). During this window
    |                       AIService skips that provider in the failover chain.
    |                       Set to 0 to disable the health cache.
    */
    'health_ttl_seconds' => (int) env('AI_HEALTH_TTL', 300),

    /*
    |--------------------------------------------------------------------------
    | Conversational Booking
    |--------------------------------------------------------------------------
    */
    'max_draft_turns' => (int) env('AI_MAX_DRAFT_TURNS', 10),

    /*
    |--------------------------------------------------------------------------
    | Context / RAG Settings
    |--------------------------------------------------------------------------
    | context_cache_ttl : default TTL in seconds for context provider caches.
    |                     Individual providers may override this.
    */
    'context_cache_ttl' => (int) env('AI_CONTEXT_CACHE_TTL', 90),

];
