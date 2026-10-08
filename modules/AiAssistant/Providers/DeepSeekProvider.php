<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Providers;

use Modules\AiAssistant\DTOs\ProviderDefinition;

final class DeepSeekProvider extends AbstractOpenAiCompatibleProvider
{
    public function definition(): ProviderDefinition
    {
        return new ProviderDefinition(
            id: 'deepseek',
            label: 'DeepSeek',
            description: 'Modelos de vanguardia ultraeficientes con razonamiento R1 y coste hasta 95% inferior.',
            icon: 'fa-solid fa-brain',
            badge: 'V3 / R1 Low Cost',
            badgeColor: 'bg-blue-500/20 text-blue-300 border-blue-500/40',
            docUrl: 'https://platform.deepseek.com/',
            defaultBaseUrl: 'https://api.deepseek.com/v1',
        );
    }
}
