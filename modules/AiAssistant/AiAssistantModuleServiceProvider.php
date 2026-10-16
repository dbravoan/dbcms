<?php

declare(strict_types=1);

namespace Modules\AiAssistant;

use App\Contracts\Services\LocalizationServiceInterface;
use App\Core\Capabilities\CapabilityRegistry;
use App\Core\Integrations\IntegrationGuideRegistry;
use App\Core\Modules\BaseModuleServiceProvider;
use App\Core\Modules\ModuleManager;
use App\Core\Modules\ModuleSettingsExtensionRegistry;
use App\Http\Middleware\EnsureAdminAuthenticated;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Modules\AiAssistant\Contracts\AiAssistantDomainServiceInterface;
use Modules\AiAssistant\Contracts\AiBrandProfileServiceInterface;
use Modules\AiAssistant\Contracts\AiConfigBridgeInterface;
use Modules\AiAssistant\Contracts\AiEntityTranslationServiceInterface;
use Modules\AiAssistant\Contracts\AiGenerationContextResolverInterface;
use Modules\AiAssistant\Contracts\AiModelCatalogInterface;
use Modules\AiAssistant\Contracts\AiMultimodalOrchestratorInterface;
use Modules\AiAssistant\Contracts\AiProviderRegistryInterface;
use Modules\AiAssistant\Services\AiActionRunner;
use Modules\AiAssistant\Services\AiAssistantDomainService;
use Modules\AiAssistant\Services\AiBrandProfileService;
use Modules\AiAssistant\Services\AiCapabilityRegistrar;
use Modules\AiAssistant\Services\AiConfigBridge;
use Modules\AiAssistant\Services\AiEntityTranslationService;
use Modules\AiAssistant\Services\AiGenerationContextResolver;
use Modules\AiAssistant\Services\AiModelCatalogService;
use Modules\AiAssistant\Services\AiMultimodalOrchestratorService;
use Modules\AiAssistant\Services\AiPromptTemplateEngine;
use Modules\AiAssistant\Services\AiProviderRegistry;
use Modules\AiAssistant\Services\AiProviderSettings;
use Modules\AiAssistant\Services\AiResearchService;
use Modules\AiAssistant\Services\AiTaxonomyService;
use Modules\AiAssistant\Services\BlockIntelligenceService;

class AiAssistantModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $identifier = 'ai_assistant';

    protected string $name = 'Copiloto Asistente IA';

    protected string $version = '1.0.0';

    protected bool $isPageable = false;

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config/providers.php', 'ai_providers');
        $this->mergeConfigFrom(__DIR__.'/config/taxonomy.php', 'ai_taxonomy');

        $this->app->singleton(AiProviderSettings::class);

        $this->app->singleton(AiProviderRegistryInterface::class, function ($app): AiProviderRegistry {
            $classes = (array) config('ai_providers.providers', []);
            $providers = array_map(static fn (string $class) => $app->make($class), $classes);

            return new AiProviderRegistry($providers);
        });

        $this->app->singleton(AiModelCatalogInterface::class, fn ($app): AiModelCatalogService => new AiModelCatalogService(
            $app->make(AiProviderRegistryInterface::class),
            $app->make(AiProviderSettings::class),
        ));

        $this->app->singleton(AiConfigBridge::class);
        $this->app->alias(AiConfigBridge::class, AiConfigBridgeInterface::class);
        $this->app->singleton(BlockIntelligenceService::class);
        $this->app->singleton(AiBrandProfileService::class);
        $this->app->alias(AiBrandProfileService::class, AiBrandProfileServiceInterface::class);
        $this->app->singleton(AiPromptTemplateEngine::class);

        $this->app->singleton(AiAssistantDomainService::class);
        $this->app->alias(AiAssistantDomainService::class, AiAssistantDomainServiceInterface::class);

        $this->app->singleton(AiResearchService::class);
        $this->app->singleton(AiMultimodalOrchestratorService::class);
        $this->app->alias(AiMultimodalOrchestratorService::class, AiMultimodalOrchestratorInterface::class);

        $this->app->bind(AiEntityTranslationServiceInterface::class, AiEntityTranslationService::class);
        $this->app->singleton(AiActionRunner::class);
        $this->app->bind(AiGenerationContextResolverInterface::class, AiGenerationContextResolver::class);
        $this->app->singleton(AiTaxonomyService::class);
    }

    public function boot(): void
    {
        $this->app->make(ModuleSettingsExtensionRegistry::class)->registerNormalizer('ai_assistant',
            fn (array $settings): array => $this->app->make(AiProviderSettings::class)->prepareModuleSettings($settings),
        );
        parent::boot();

        $this->loadViewsFrom(__DIR__.'/Resources/views', 'ai_assistant');
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        $this->app->make(AiCapabilityRegistrar::class)->register($this->app->make(CapabilityRegistry::class));
        $this->app->make(IntegrationGuideRegistry::class)->register('ai_assistant', require __DIR__.'/Resources/guides/providers.php');
        $this->registerRoutes();
        View::composer(['admin.dashboard', 'themes::admin.*.admin.dashboard', 'components.frontoffice-admin-bar'], function (\Illuminate\View\View $view): void {
            if (! $this->app->make(ModuleManager::class)->isModuleEnabled('ai_assistant') || ! auth()->user()?->can('use:ai')) {
                return;
            }
            $stack = $view->getName() === 'components.frontoffice-admin-bar' ? 'front_admin_actions' : 'admin_dashboard_actions';
            $view->getFactory()->startPush($stack, view('ai_assistant::admin.partials.editorial_guide_link')->render());
        });
        $this->registerLayoutExtensions();
        $this->registerCreateFill();
        $this->registerBlockManagerActions();
        $this->registerSeoBlockActions();
    }

    /**
     * Injects the "Generate SEO with AI" trigger into the SEO/GEO block header
     * (generic extension point) so the block stays AI-agnostic.
     */
    protected function registerSeoBlockActions(): void
    {
        View::composer('components.admin.seo-meta-box', function ($view): void {
            $view->getFactory()->startPush(
                'seo_block_actions',
                view('ai_assistant::admin.partials.seo_generate')->render()
            );
        });
    }

    /**
     * Injects the "generate sections with AI" trigger into the block manager
     * toolbar (generic extension point) so the manager stays AI-agnostic.
     */
    protected function registerBlockManagerActions(): void
    {
        View::composer('components.admin.block-manager', function ($view): void {
            $view->getFactory()->startPush(
                'block_manager_actions',
                view('ai_assistant::admin.partials.block_generate')->render()
            );
        });
    }

    /**
     * Injects the "Create with AI" trigger into the create form header of every
     * entity whose module declared a `createView` in its schema. Modules stay
     * completely unaware of the AI assistant.
     */
    protected function registerCreateFill(): void
    {
        View::composer('*', function ($view): void {
            $name = $view->name();
            if (! is_string($name) || $name === '') {
                return;
            }

            $type = $this->createViewEntityType($name);
            if ($type === null) {
                return;
            }

            $view->getFactory()->startPush(
                'entity_form_header',
                view('ai_assistant::admin.partials.create_fill', [
                    'entityType' => $type,
                    'locale' => app()->getLocale(),
                    'placeholder' => __("admin.ai.entity_placeholder_{$type}"),
                ])->render()
            );
        });
    }

    /**
     * @var array<string, string>|null
     */
    private ?array $createViewMap = null;

    protected function createViewEntityType(string $viewName): ?string
    {
        if ($this->createViewMap === null) {
            $this->createViewMap = [];

            /** @var AiTaxonomyService $taxonomy */
            $taxonomy = $this->app->make(AiTaxonomyService::class);

            foreach ($taxonomy->entities() as $schema) {
                if ($schema->createView !== null) {
                    $this->createViewMap[$schema->createView] = $schema->type;
                }
            }
        }

        return $this->createViewMap[$viewName] ?? null;
    }

    private bool $layoutExtensionsRegistered = false;

    protected function registerLayoutExtensions(): void
    {
        if ($this->layoutExtensionsRegistered) {
            return;
        }
        $this->layoutExtensionsRegistered = true;

        // Autonomous Copilot Bridge injection into admin layout (zero-knowledge for other modules)
        View::composer('admin.layout', function ($view): void {
            $view->getFactory()->startPush(
                'admin_modals',
                view('ai_assistant::admin.partials.copilot_bridge')->render()
                .view('ai_assistant::admin.partials.entity_translation', $view->getData() + ['aiTranslationLanguages' => app(LocalizationServiceInterface::class)->activeLanguages()])->render()
            );
        });
    }

    protected function registerRoutes(): void
    {
        Route::prefix(trim((string) config('cms.admin_path', 'admin'), '/').'/ai-assistant')
            ->middleware(['web', 'auth', EnsureAdminAuthenticated::class, 'module.enabled:ai_assistant'])
            ->group(__DIR__.'/Routes/web.php');

        Route::prefix('api/ai-assistant')
            ->middleware(['api', 'module.enabled:ai_assistant'])
            ->group(__DIR__.'/Routes/api.php');
    }

    public function hasDedicatedPanel(): bool
    {
        return true;
    }

    public function getDedicatedPanelUrl(): ?string
    {
        return route('admin.ai_assistant.workbench');
    }

    public function getAdminNavigation(): array
    {
        return [
            [
                'title' => 'Copiloto IA',
                'route' => 'admin.ai_assistant.workbench',
                'url' => route('admin.ai_assistant.workbench'),
                'icon' => 'wand-magic-sparkles',
                'permission' => 'use:ai',
            ],
        ];
    }
}
