<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Http\Controllers\Admin\Workbench;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\Market;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\AiAssistant\Contracts\AiBrandProfileServiceInterface;
use Modules\AiAssistant\Contracts\AiConfigBridgeInterface;
use Modules\AiAssistant\Models\AiGenerationLog;
use Modules\AiAssistant\Services\AiPromptTemplateEngine;
use Modules\AiAssistant\Services\AiTaxonomyService;
use Modules\AiAssistant\Services\BlockIntelligenceService;

final class GetController extends Controller
{
    public function __construct(
        private readonly AiConfigBridgeInterface $configBridge,
        private readonly BlockIntelligenceService $blockIntelligence,
        private readonly AiBrandProfileServiceInterface $brandProfileService,
        private readonly AiPromptTemplateEngine $promptEngine,
        private readonly AiTaxonomyService $taxonomy
    ) {}

    public function __invoke(Request $request): View
    {
        $activeProvider = $this->configBridge->getActiveProvider();
        $configuredModel = $this->configBridge->getModelForProvider($activeProvider);
        $isConfigured = $this->configBridge->isConfigured($activeProvider);
        $availableBlocks = $this->blockIntelligence->getAvailableBlocks();
        $recentLogs = AiGenerationLog::latest()->take(20)->get();
        $providersCatalog = $this->configBridge->getProvidersCatalog();

        // Entity selector is driven by the schemas declared by the active modules.
        $aiEntities = [];
        $aiPlaceholders = [];
        foreach ($this->taxonomy->entities() as $schema) {
            $aiEntities[$schema->type] = $schema->label;
            $aiPlaceholders[$schema->type] = __("admin.ai.entity_placeholder_{$schema->type}");
        }

        $providers = [
            'gemini' => 'Google Gemini (Recomendado)',
            'openai' => 'OpenAI (GPT-4o)',
            'deepseek' => 'DeepSeek (V3 / R1)',
            'anthropic' => 'Anthropic (Claude 3.5 Sonnet)',
            'opencode' => 'OpenCode (Zen + Go)',
            'ollama' => 'Ollama (Local)',
            'custom' => 'Compatible OpenAI (vLLM / LMStudio)',
        ];

        return view('ai_assistant::admin.workbench', [
            'activeProvider' => $activeProvider,
            'configuredModel' => $configuredModel,
            'isConfigured' => $isConfigured,
            'availableBlocks' => $availableBlocks,
            'recentLogs' => $recentLogs,
            'providers' => $providers,
            'providersCatalog' => $providersCatalog,
            'aiEntities' => $aiEntities,
            'aiPlaceholders' => $aiPlaceholders,
            'aiExamples' => (array) config('ai_taxonomy.examples', []),
            'brandProfile' => $this->brandProfileService->getProfile(),
            'brandPresets' => $this->brandProfileService->getPresets(),
            'compiledSystemPrompt' => $this->promptEngine->buildSystemPrompt('es'),
            'markets' => Market::query()->where('is_active', true)->get(),
            'locales' => Language::query()->where('is_active', true)->get(),
        ]);
    }
}
