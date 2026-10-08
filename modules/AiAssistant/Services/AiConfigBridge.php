<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Services;

use App\Contracts\Services\ModuleSettingsServiceInterface;
use App\Core\Capabilities\ExecutionContext;
use Modules\AiAssistant\Contracts\AiConfigBridgeInterface;
use Modules\AiAssistant\Contracts\AiModelCatalogInterface;
use Modules\AiAssistant\Contracts\AiProviderRegistryInterface;
use Modules\AiAssistant\DTOs\ChatRequest;
use Modules\AiAssistant\DTOs\ModelOption;
use Modules\AiAssistant\DTOs\ProviderDefinition;
use Modules\AiAssistant\Exceptions\AiProviderException;

/**
 * Backoffice-facing facade that resolves providers, credentials and the
 * live model catalog. All provider-specific behavior lives in the adapters.
 */
class AiConfigBridge implements AiConfigBridgeInterface
{
    public function __construct(
        protected readonly AiProviderRegistryInterface $registry,
        protected readonly AiModelCatalogInterface $catalog,
        protected readonly AiProviderSettings $settings,
        protected readonly ModuleSettingsServiceInterface $settingsService,
    ) {}

    public function getActiveProvider(?string $modality = null): string
    {
        return $this->settings->activeProvider($modality ?: 'text');
    }

    public function getApiKeyForProvider(string $provider): ?string
    {
        return $this->settings->apiKey($provider);
    }

    public function getModelForProvider(string $provider): string
    {
        $stored = $this->settings->modelForModality($provider, 'text') ?? $this->settings->model($provider);
        if ($stored !== null) {
            return $stored;
        }

        // No stored model: fall back to the first live model, if any.
        // Unconfigured providers expose no models, so this stays empty.
        $models = $this->catalog->models($provider);

        return $models[0]->id ?? '';
    }

    public function hasApiKeyForProvider(string $provider): bool
    {
        return $this->settings->hasApiKey($provider);
    }

    public function isConfigured(?string $provider = null): bool
    {
        $target = $provider ?: $this->getActiveProvider();
        $definition = $this->registry->resolve($target)->definition();

        // Local/self-hosted providers are configured once an endpoint is saved.
        if (! $definition->requiresApiKey) {
            return $this->settings->baseUrl($target) !== null;
        }

        return $this->hasApiKeyForProvider($target);
    }

    /**
     * @return list<array{id: string, name: string, description: string, source: ?string, family: string, experimental: bool}>
     */
    public function getRegisteredModels(string $provider): array
    {
        return array_map(static function (ModelOption $model): array {
            $origin = $model->source ? strtoupper($model->source) : null;
            $description = $origin !== null ? "Gateway OpenCode · {$origin}" : '';

            if ($model->experimental) {
                $description = trim($description.' (experimental)');
            }

            return [
                'id' => $model->id,
                'name' => $model->label,
                'description' => $description,
                'source' => $model->source,
                'family' => $model->family->value,
                'experimental' => $model->experimental,
            ];
        }, $this->catalog->models($provider));
    }

    /**
     * @return list<array{id: string, name: string, description: string, source: ?string, family: string, experimental: bool}>
     */
    public function listModels(string $provider, bool $forceRefresh = false): array
    {
        $models = $forceRefresh
            ? $this->catalog->refresh($provider)
            : $this->catalog->models($provider);

        return array_map(static fn (ModelOption $model): array => $model->toArray() + [
            'name' => $model->label,
            'description' => '',
        ], $models);
    }

    public function refreshModels(string $provider): array
    {
        return $this->listModels($provider, true);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getProvidersCatalog(): array
    {
        $active = $this->getActiveProvider();
        $catalog = [];

        foreach ($this->registry->definitions() as $id => $definition) {
            $catalog[$id] = $this->providerView($definition, $active);
        }

        return $catalog;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveProviderConfig(string $provider, array $data): bool
    {
        $this->registry->resolve($provider);
        $this->settings->save($provider, $data);
        $this->catalog->forget($provider);

        return true;
    }

    public function setDefaultProvider(string $provider, string $modality = 'text'): bool
    {
        if (! $this->registry->has($provider) || ! $this->registry->resolve($provider)->definition()->supportsModality($modality)) {
            return false;
        }

        $this->settings->setActiveProvider($provider, $modality);

        return true;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getModalitiesMatrix(): array
    {
        $catalog = $this->getProvidersCatalog();
        $matrix = [];

        foreach (['text', 'image', 'video'] as $modality) {
            $matching = [];
            $hasConfigured = false;
            $active = $this->settings->activeProvider($modality);

            foreach ($this->registry->definitionsForModality($modality) as $id => $def) {
                if (isset($catalog[$id])) {
                    $item = $catalog[$id];
                    $item['is_active_for_modality'] = ($active === $id);
                    $matching[] = $item;
                    if ($item['is_configured']) {
                        $hasConfigured = true;
                    }
                }
            }

            $matrix[$modality] = [
                'available' => $hasConfigured,
                'active_provider' => $active,
                'providers' => $matching,
            ];
        }

        return $matrix;
    }

    /**
     * @return list<string>
     */
    public function getProviderIds(): array
    {
        return $this->registry->ids();
    }

    public function supportsProvider(string $provider): bool
    {
        return $this->registry->has($provider);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{content: string, provider: string, model: string, prompt_tokens: int, completion_tokens: int, latency_ms: int}
     */
    public function generateCompletion(string $systemPrompt, string $userPrompt, ?string $provider = null, array $options = []): array
    {
        $startTime = microtime(true);
        $context = app(ExecutionContext::class);
        $action = AiActionRunner::MCP_TOOL_ACTIONS[$context->capabilityName ?? ''] ?? '';
        if ($context->actor !== null && in_array($action, ['block', 'draft', 'recommend_blocks', 'compose_blocks', 'proofread', 'humanize', 'seo', 'page_element', 'research', 'orchestrate_multimodal'], true)) {
            $selectedProvider = trim((string) ($context->arguments['text_provider'] ?? $context->arguments['provider'] ?? ''));
            $selectedModel = trim((string) ($context->arguments['text_model'] ?? $context->arguments['model'] ?? ''));
            if ($provider === null && $selectedProvider !== '') {
                $provider = $selectedProvider;
            }
            if (! isset($options['model']) && $selectedModel !== '' && ($provider === null || $selectedProvider === '' || $provider === $selectedProvider)) {
                $options['model'] = $selectedModel;
            }
        }
        $targetProvider = $provider ?: $this->getActiveProvider();
        $adapter = $this->registry->resolve($targetProvider);
        $model = (string) ($options['model'] ?? $this->getModelForProvider($targetProvider));
        $apiKey = (string) ($this->getApiKeyForProvider($targetProvider) ?? '');

        if (! $adapter->definition()->supportsModality('text')) {
            throw new AiProviderException('Provider does not support text generation.');
        }
        if (! app()->environment('testing') && ! $this->isConfigured($targetProvider)) {
            throw new AiProviderException('AI provider is not configured.');
        }
        if (! app()->environment('testing') && $model === '') {
            throw new AiProviderException('Select a configured model before generating content.');
        }
        if (app()->environment('testing')) {
            $mockContent = $this->synthesizeMockResponse($userPrompt);
            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

            return [
                'content' => $mockContent,
                'provider' => $targetProvider,
                'model' => $model,
                'prompt_tokens' => (int) round(strlen($systemPrompt.$userPrompt) / 4),
                'completion_tokens' => (int) round(strlen($mockContent) / 4),
                'latency_ms' => max(15, $latencyMs),
            ];
        }

        $request = new ChatRequest(
            systemPrompt: $systemPrompt,
            userPrompt: $userPrompt,
            temperature: (float) ($options['temperature'] ?? $this->settingsService->get('ai_assistant', 'default_temperature', 0.7)),
            jsonMode: (bool) ($options['json'] ?? true),
        );

        $completion = $adapter->chat($request, $apiKey, $model, $this->resolveBaseUrl($targetProvider, $model));
        $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

        return [
            'content' => $completion->content,
            'provider' => $completion->provider,
            'model' => $completion->model,
            'prompt_tokens' => $completion->promptTokens,
            'completion_tokens' => $completion->completionTokens,
            'latency_ms' => $latencyMs,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function providerView(ProviderDefinition $definition, string $activeProvider): array
    {
        $hasApiKey = $this->hasApiKeyForProvider($definition->id);
        $hasBaseUrl = $this->settings->baseUrl($definition->id) !== null;
        $isConfigured = $this->isConfigured($definition->id);
        $models = $this->getRegisteredModels($definition->id);
        $connection = $this->catalog instanceof AiModelCatalogService ? $this->catalog->connectionState($definition->id) : ['state' => $isConfigured ? 'configured' : 'unconfigured', 'verified' => false];

        return [
            'id' => $definition->id,
            'name' => $definition->label,
            'badge' => $definition->badge,
            'badge_color' => $definition->badgeColor,
            'description' => $definition->description,
            'icon' => $definition->icon,
            'doc_url' => $definition->docUrl,
            'api_key' => $hasApiKey ? AiProviderSettings::MASKED_KEY : '',
            'has_key' => $isConfigured,
            'is_configured' => $isConfigured,
            'current_model' => $this->getModelForProvider($definition->id),
            'image_model' => $this->settings->modelForModality($definition->id, 'image'),
            'video_model' => $this->settings->modelForModality($definition->id, 'video'),
            'base_url' => $this->settings->baseUrl($definition->id) ?? '',
            'default_base_url' => $definition->defaultBaseUrl ?? '',
            'is_active' => $activeProvider === $definition->id,
            'registered_models' => $models,
            'connection_state' => $connection['state'],
            'connection_verified' => $connection['verified'],
            'catalog_source' => $definition->supportsModelListing ? 'live' : 'documented',
            'requires_api_key' => $definition->requiresApiKey,
            'supports_model_listing' => $definition->supportsModelListing,
            'modalities' => $definition->modalities,
        ];
    }

    private function resolveBaseUrl(string $provider, string $model): ?string
    {
        $definition = $this->registry->resolve($provider)->definition();

        if ($definition->sourceBaseUrls !== []) {
            foreach ($this->catalog->models($provider) as $option) {
                if ($option->id === $model && $option->source !== null && isset($definition->sourceBaseUrls[$option->source])) {
                    return $definition->sourceBaseUrls[$option->source];
                }
            }
        }

        return $this->settings->baseUrl($provider);
    }

    /**
     * Synthesizes realistic JSON response for mock/test execution based on requested action.
     */
    protected function synthesizeMockResponse(string $userPrompt): string
    {
        if (str_contains($userPrompt, 'Genera un bloque de tipo')) {
            preg_match("/tipo '([^']+)'/", $userPrompt, $matches);
            $blockType = $matches[1] ?? 'hero';

            $mockData = match ($blockType) {
                'hero' => [
                    'title' => 'Impulsa tu arquitectura digital con dbCMS',
                    'subtitle' => 'CMS desacoplado de alto rendimiento con inteligencia artificial integrada y soporte multilingüe.',
                    'cta_text' => 'Comenzar ahora',
                    'cta_url' => '/contacto',
                    'align' => 'center',
                ],
                'features' => [
                    'headline' => 'Características diseñadas para escalar',
                    'items' => [
                        ['title' => 'Rendimiento Extremo', 'description' => 'Arquitectura optimizada en Laravel LTS con caché inteligente.', 'icon' => 'fa-solid fa-bolt'],
                        ['title' => 'Seguridad OAuth2', 'description' => 'Protección criptográfica mediante Laravel Passport para agentes y APIs.', 'icon' => 'fa-solid fa-shield-halved'],
                        ['title' => 'Model Context Protocol', 'description' => 'Integración nativa bidireccional con OpenCode, Cursor y cualquier cliente MCP.', 'icon' => 'fa-solid fa-network-wired'],
                    ],
                ],
                'faq' => [
                    'headline' => 'Preguntas Frecuentes',
                    'items' => [
                        ['question' => '¿Cómo se conecta un agente externo?', 'answer' => 'A través de tokens seguros de Laravel Passport con los scopes correspondientes.'],
                        ['question' => '¿Es compatible con múltiples idiomas?', 'answer' => 'Sí, soporta mercados y locales dinámicos de forma nativa.'],
                    ],
                ],
                'cta' => [
                    'title' => '¿Listo para transformar tu flujo editorial?',
                    'description' => 'Únete a las empresas que gestionan contenidos a velocidad de código.',
                    'button_text' => 'Solicitar Demostración',
                    'button_url' => '/demo',
                ],
                'pricing' => [
                    'headline' => 'Planes y Tarifas Flexibles',
                    'plans' => [
                        ['name' => 'Starter', 'price' => '29€', 'period' => '/mes', 'features' => ['Hasta 5 sitios', 'API MCP básica'], 'cta_text' => 'Elegir Plan', 'cta_url' => '/planes', 'is_featured' => false],
                        ['name' => 'Pro Enterprise', 'price' => '99€', 'period' => '/mes', 'features' => ['Sitios ilimitados', 'Agentes autónomos', 'Soporte 24/7'], 'cta_text' => 'Comenzar Pro', 'cta_url' => '/planes', 'is_featured' => true],
                    ],
                ],
                'kpi' => [
                    'metrics' => [
                        ['number' => '99.9%', 'label' => 'Disponibilidad Garantizada', 'icon' => 'fa-solid fa-server'],
                        ['number' => '< 50ms', 'label' => 'Latencia Promedio', 'icon' => 'fa-solid fa-gauge-high'],
                        ['number' => '10k+', 'label' => 'Artículos Publicados', 'icon' => 'fa-solid fa-newspaper'],
                    ],
                ],
                default => [
                    'title' => 'Sección Generada con Asistente IA',
                    'description' => 'Contenido maquetado automáticamente con dbCMS.',
                ],
            };

            return $this->toJson([
                'type' => $blockType,
                'data' => $mockData,
                'reasoning' => 'Bloque generado con estructura equilibrada y copy persuasivo en tono periodístico.',
            ]);
        }

        if (str_contains($userPrompt, 'Redacta un artículo completo')) {
            return $this->toJson([
                'title' => 'Evolución de las arquitecturas modulares en sistemas de contenidos',
                'content' => "## La transformación del desarrollo web\n\nEl desarrollo de software corporativo exige flexibilidad. Cuando los equipos dependen de estructuras rígidas, la velocidad de entrega se desploma ante cualquier cambio regulatorio o técnico.\n\nPor esta razón, la transición hacia arquitecturas modulares desacopladas permite aislar responsabilidades sin perder la cohesión del sistema.\n\n## Ventajas estratégicas del desacoplamiento\n\nAl organizar las funcionalidades como módulos independientes con interfaces estrictas, el mantenimiento se simplifica notablemente.",
                'excerpt' => 'Análisis de cómo las arquitecturas modulares y el protocolo MCP transforman los sistemas de contenidos corporativos.',
                'meta_title' => 'Arquitecturas Modulares en CMS: Guía y Ventajas',
                'meta_description' => 'Descubre cómo las arquitecturas desacopladas optimizan la escalabilidad y reducen la deuda técnica en el desarrollo web moderno.',
                'keywords' => ['arquitectura modular', 'cms', 'laravel', 'mcp', 'desacoplamiento'],
                'recommended_blocks' => [
                    ['type' => 'hero', 'rationale' => 'Introducción visual del concepto.'],
                    ['type' => 'features', 'rationale' => 'Resumen de los pilares arquitectónicos.'],
                    ['type' => 'cta', 'rationale' => 'Invitación a explorar el repositorio.'],
                ],
            ]);
        }

        if (str_contains($userPrompt, 'Revisa, corrige y mejora')) {
            return $this->toJson([
                'original' => $userPrompt,
                'improved' => 'El desarrollo de software corporativo exige flexibilidad. Cuando los equipos dependen de estructuras rígidas, la velocidad de entrega se resiente ante cambios técnicos.',
                'changes' => [
                    'Sustituida frase pasiva por activa para mayor contundencia',
                    'Corregida concordancia y ritmo de la oración',
                ],
            ]);
        }

        if (str_contains($userPrompt, 'Humaniza el siguiente texto')) {
            return $this->toJson([
                'original' => $userPrompt,
                'humanized' => 'Hoy en día en el sector tecnológico, resulta determinante comprender la arquitectura del sistema. Con certeza, la modularidad ofrece múltiples opciones.',
                'cliches_removed' => [
                    'en el vertiginoso mundo de',
                    'juega un papel crucial',
                    'sin lugar a dudas',
                ],
            ]);
        }

        if (str_contains($userPrompt, 'Genera metadatos SEO')) {
            return $this->toJson([
                'meta_title' => 'Guía de Optimización SEO y Arquitectura Web',
                'meta_description' => 'Aprende las mejores prácticas de posicionamiento orgánico, indexación semántica y optimización para motores de búsqueda de IA.',
                'keywords' => ['seo', 'geo', 'optimizacion', 'indexacion', 'arquitectura'],
            ]);
        }

        if (str_contains($userPrompt, 'completo y profesional')) {
            return $this->toJson([
                'title' => 'Guía práctica de arquitectura modular para equipos de producto',
                'slug' => 'guia-practica-arquitectura-modular',
                'summary' => 'Una guía para diseñar sistemas de contenido modulares, mantenibles y listos para escalar entre mercados e idiomas.',
                'body' => "## Por qué modularizar\n\nLos equipos que trabajan con estructuras rígidas pierden velocidad ante cualquier cambio. Separar responsabilidades permite iterar sin romper el conjunto.\n\n## Cómo aplicarlo\n\nDefine interfaces estables entre módulos, aísla la lógica de dominio y deja que la presentación dependa de contratos claros.\n\n## Resultado\n\nUn sistema más predecible, más fácil de probar y más rápido de evolucionar.",
                'meta_title' => 'Arquitectura Modular: Guía Práctica para Producto',
                'meta_description' => 'Aprende a diseñar sistemas de contenido modulares y escalables entre mercados e idiomas con buenas prácticas de arquitectura.',
                'keywords' => ['arquitectura modular', 'cms', 'escalabilidad', 'multi-mercado'],
            ]);
        }

        if (str_contains($userPrompt, 'SECUENCIA DE BLOQUES')) {
            return $this->toJson([
                'title' => 'Guía práctica de arquitectura modular para equipos de producto',
                'summary' => 'Una guía para diseñar sistemas de contenido modulares, mantenibles y listos para escalar entre mercados e idiomas.',
                'content' => "## Por qué modularizar\n\nLos equipos que trabajan con estructuras rígidas pierden velocidad ante cualquier cambio. Separar responsabilidades permite iterar sin romper el conjunto.\n\n## Cómo aplicarlo\n\nDefine interfaces estables entre módulos, aísla la lógica de dominio y deja que la presentación dependa de contratos claros.\n\n## Resultado\n\nUn sistema más predecible, más fácil de probar y más rápido de evolucionar.",
                'meta_title' => 'Arquitectura Modular: Guía Práctica para Producto',
                'meta_description' => 'Aprende a diseñar sistemas de contenido modulares y escalables entre mercados e idiomas con buenas prácticas de arquitectura.',
                'keywords' => ['arquitectura modular', 'cms', 'escalabilidad', 'multi-mercado'],
                'sections' => [
                    [
                        'type' => 'hero',
                        'data' => [
                            'title' => 'Arquitectura modular para equipos de producto',
                            'content' => 'Diseña sistemas de contenido mantenibles y listos para escalar entre mercados e idiomas.',
                            'cta_text' => 'Solicitar información',
                            'cta_url' => '/contacto',
                            'badge' => 'Guía práctica',
                        ],
                    ],
                    [
                        'type' => 'features',
                        'data' => [
                            'title' => 'Pilares de una arquitectura modular',
                            'columns' => '3',
                            'items' => [
                                ['icon' => '⚡', 'title' => 'Responsabilidades claras', 'description' => 'Cada módulo expone un contrato estable y aislado.'],
                                ['icon' => '🛡️', 'title' => 'Lógica de dominio', 'description' => 'La presentación depende de interfaces, no de implementaciones.'],
                                ['icon' => '🤖', 'title' => 'Escalado multi-mercado', 'description' => 'Contenido localizado sin duplicar estructura.'],
                            ],
                        ],
                    ],
                    [
                        'type' => 'gallery',
                        'data' => [
                            'title' => 'Nuestros proyectos',
                            'album_slug' => 'album-inventado',
                            'columns' => '3',
                            'limit' => 6,
                        ],
                    ],
                    [
                        'type' => 'faq',
                        'data' => [
                            'title' => 'Preguntas frecuentes',
                            'items' => [
                                ['question' => '¿Cuándo conviene modularizar?', 'answer' => 'Cuando el equipo crece y los cambios empiezan a romper partes no relacionadas.'],
                                ['question' => '¿Afecta al rendimiento?', 'answer' => 'No: separar responsabilidades facilita además el caché por capas.'],
                            ],
                        ],
                    ],
                    [
                        'type' => 'widget_form',
                        'data' => [
                            'title' => 'Solicita información',
                            'form_slug' => 'form-inventado',
                            'button_text' => 'Enviar',
                        ],
                    ],
                    [
                        'type' => 'cta',
                        'data' => [
                            'title' => '¿Listo para ordenar tu arquitectura?',
                            'content' => 'Hablemos de tu caso y definamos una ruta de modularización realista.',
                            'cta_text' => 'Contactar',
                            'cta_url' => '/contacto',
                        ],
                    ],
                ],
            ]);
        }

        return $this->toJson([
            'status' => 'success',
            'message' => 'Operación completada con éxito por el Asistente IA.',
        ]);
    }

    protected function toJson(mixed $data): string
    {
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE);

        return is_string($encoded) ? $encoded : '{}';
    }
}
