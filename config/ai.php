<?php

return [
    'provider' => env('AI_PROVIDER', 'groq'),
    'provider_priority' => array_filter(
        array_map('trim', explode(',', env('AI_PROVIDER_PRIORITY', env('AI_PROVIDER', 'groq'))))
    ),

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
    'model' => env('AI_MODEL', env('GROQ_MODEL', 'qwen/qwen3-32b')),

    'model_aliases' => [
        'qwen_32b' => [
            'groq'        => 'llama-3.3-70b-versatile',
            'openrouter'  => 'qwen/qwen3-32b',
            'siliconflow' => 'Qwen/Qwen3-32B',
            'dashscope'   => 'qwen3-32b',
        ],
        'qwen_plus' => [
            'groq'        => 'llama-3.3-70b-versatile',
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
        'qwen/qwen3-32b' => [
            'groq'        => 'llama-3.3-70b-versatile',
            'openrouter'  => 'qwen/qwen3-32b',
            'siliconflow' => 'Qwen/Qwen3-32B',
            'dashscope'   => 'qwen3-32b',
        ],
    ],
    'timeout' => (int) env('AI_TIMEOUT', 30),
    'max_attempts'        => (int) env('AI_MAX_ATTEMPTS', 2),
    'retry_delay_seconds' => (int) env('AI_RETRY_DELAY', 1),
    'health_ttl_seconds' => (int) env('AI_HEALTH_TTL', 300),
    'max_draft_turns' => (int) env('AI_MAX_DRAFT_TURNS', 10),
    'context_cache_ttl' => (int) env('AI_CONTEXT_CACHE_TTL', 90),
];
