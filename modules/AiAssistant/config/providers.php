<?php

declare(strict_types=1);

use Modules\AiAssistant\Providers\AnthropicProvider;
use Modules\AiAssistant\Providers\CustomOpenAiProvider;
use Modules\AiAssistant\Providers\DeepSeekProvider;
use Modules\AiAssistant\Providers\GeminiProvider;
use Modules\AiAssistant\Providers\HiggsfieldProvider;
use Modules\AiAssistant\Providers\MagnificProvider;
use Modules\AiAssistant\Providers\OllamaProvider;
use Modules\AiAssistant\Providers\OpenAiProvider;
use Modules\AiAssistant\Providers\OpenCodeProvider;
use Modules\AiAssistant\Providers\VllmProvider;

/**
 * Data-driven AI provider registry.
 *
 * Add a new provider by appending its adapter class here: the registry,
 * catalog, backoffice hub and validation rules pick it up automatically.
 */
return [
    'providers' => [
        GeminiProvider::class,
        OpenAiProvider::class,
        DeepSeekProvider::class,
        AnthropicProvider::class,
        CustomOpenAiProvider::class,
        OpenCodeProvider::class,
        OllamaProvider::class,
        VllmProvider::class,
        MagnificProvider::class,
        HiggsfieldProvider::class,
    ],
];
