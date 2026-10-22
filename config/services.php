<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | AI Provider API Keys (environment fallback)
    |--------------------------------------------------------------------------
    |
    | Optional fallback keys used when no key has been stored from the
    | backoffice. OpenCode Zen and Go share a single API key.
    |
    */

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
    ],

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
    ],

    'deepseek' => [
        'key' => env('DEEPSEEK_API_KEY'),
    ],

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
    ],

    'opencode' => [
        'key' => env('OPENCODE_API_KEY'),
    ],
];
