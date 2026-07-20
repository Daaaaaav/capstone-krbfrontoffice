<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Provider
    |--------------------------------------------------------------------------
    | Supported: "groq", "openrouter", "siliconflow", "dashscope"
    |
    | Switch providers by changing AI_PROVIDER in your .env file.
    | No other code changes are required.
    */
    'provider' => env('AI_PROVIDER', 'groq'),

    /*
    |--------------------------------------------------------------------------
    | API Key
    |--------------------------------------------------------------------------
    | The API key for whichever provider is selected above.
    */
    'api_key' => env('AI_API_KEY', env('GROQ_API_KEY', '')),

    /*
    |--------------------------------------------------------------------------
    | Model
    |--------------------------------------------------------------------------
    | The model slug to request from the provider.
    |
    | Groq examples     : qwen/qwen3-32b, llama-3.3-70b-versatile
    | OpenRouter examples: qwen/qwen3-32b, openai/gpt-4o-mini
    | SiliconFlow examples: Qwen/Qwen3-32B, deepseek-ai/DeepSeek-V3
    | DashScope examples: qwen-plus, qwen-max, qwen3-32b
    */
    'model' => env('AI_MODEL', env('GROQ_MODEL', 'qwen/qwen3-32b')),

    /*
    |--------------------------------------------------------------------------
    | Base URL
    |--------------------------------------------------------------------------
    | The base URL for the provider's OpenAI-compatible endpoint.
    | Leave empty to use each provider's default.
    |
    | Groq        : https://api.groq.com/openai/v1
    | OpenRouter  : https://openrouter.ai/api/v1
    | SiliconFlow : https://api.siliconflow.cn/v1
    | DashScope   : https://dashscope-intl.aliyuncs.com/compatible-mode/v1
    |               (China region: https://dashscope.aliyuncs.com/compatible-mode/v1)
    */
    'base_url' => env('AI_BASE_URL', ''),

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    | HTTP timeout in seconds for each individual provider request.
    */
    'timeout' => (int) env('AI_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Retry Configuration
    |--------------------------------------------------------------------------
    | max_attempts : total attempts before giving up (includes first attempt).
    | retry_delay_seconds : seconds to wait between retry attempts.
    | Rate-limit (429) responses are never retried — they fail immediately.
    */
    'max_attempts'         => (int) env('AI_MAX_ATTEMPTS', 3),
    'retry_delay_seconds'  => (int) env('AI_RETRY_DELAY', 2),

    /*
    |--------------------------------------------------------------------------
    | Conversational Booking
    |--------------------------------------------------------------------------
    | max_draft_turns: how many conversation turns to include as history
    |                  when passing multi-turn context to the AI.
    */
    'max_draft_turns' => (int) env('AI_MAX_DRAFT_TURNS', 10),

];
