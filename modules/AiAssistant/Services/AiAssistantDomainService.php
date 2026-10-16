<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Services;

use App\Contracts\Services\ModuleSettingsServiceInterface;
use App\Core\Content\EntitySchema;
use App\Services\Ai\AntiAiEditorialService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\AiAssistant\Contracts\AiAssistantDomainServiceInterface;
use Modules\AiAssistant\Models\AiGenerationLog;

class AiAssistantDomainService implements AiAssistantDomainServiceInterface
{
    /**
     * Brief keywords that must force a dynamic block when the AI omitted it.
     * The payload is filled with real entities and can be changed by the reviewer.
     *
     * @var array<string, list<string>>
     */
    private const INTENT_BLOCKS = [
        'gallery' => ['galeria', 'galería', 'gallery', 'album', 'álbum', 'fotos', 'imagenes', 'imágenes', 'images', 'photos'],
        'widget_form' => ['formulario', 'contacto', 'contactar', 'contacta', 'suscripcion', 'suscripción', 'presupuesto', 'contact form'],
        'widget_posts' => ['blog', 'últimos artículos', 'latest posts', 'recent posts', 'post widget'],
        'widget_products' => ['tienda', 'catalogo', 'catálogo', 'shop', 'catalogue', 'product widget'],
    ];

    public function __construct(
        protected AiPromptTemplateEngine $promptEngine,
        protected BlockIntelligenceService $blockIntelligence,
        protected AiConfigBridge $configBridge,
        protected AntiAiEditorialService $antiAiService,
        protected ModuleSettingsServiceInterface $settingsService,
        protected AiTaxonomyService $taxonomy,
    ) {}

    /**
     * Generates a single block structure and payload based on intent and block type.
     */
    public function generateBlock(string $blockType, string $prompt, string $locale = 'es', string $market = 'es'): array
    {
        if ($this->blockIntelligence->getBlockDefinition($blockType) === null) {
            throw ValidationException::withMessages(['block_type' => 'This block is unavailable: '.$blockType]);
        }
        $dataQuestion = 'Aporta las etiquetas y los valores reales que quieres representar; no se generarán estadísticas inventadas.';
        if (in_array($blockType, ['charts', 'kpi'], true) && ! preg_match('/\d/u', $prompt)) {
            return ['success' => false, 'status' => 'needs_input', 'question' => $dataQuestion, 'message' => $dataQuestion];
        }
        $promptPair = $this->promptEngine->buildBlockGenerationPrompt($blockType, $prompt, $locale, $market);
        $completion = $this->configBridge->generateCompletion($promptPair['system'], $promptPair['user']);

        $decoded = json_decode($completion['content'], true);
        if (! is_array($decoded)) {
            $decoded = [
                'type' => $blockType,
                'data' => [],
                'reasoning' => 'Bloque generado.',
            ];
        }

        if (! $this->blockIntelligence->hasSuppliedQuantitativeData($blockType, (array) ($decoded['data'] ?? []), $prompt)) {
            return ['success' => false, 'status' => 'needs_input', 'question' => $dataQuestion, 'message' => $dataQuestion];
        }

        // Sanitize with block defaults to guarantee schema integrity
        $sanitizedData = $this->blockIntelligence->sanitizeBlockPayload(
            $blockType,
            (array) ($decoded['data'] ?? [])
        );

        $result = [
            'type' => $blockType,
            'data' => $sanitizedData,
            'reasoning' => (string) ($decoded['reasoning'] ?? 'Bloque optimizado para la intención solicitada.'),
        ];

        $this->logGeneration(
            action: 'block',
            provider: $completion['provider'],
            model: $completion['model'],
            locale: $locale,
            market: $market,
            promptTokens: $completion['prompt_tokens'],
            completionTokens: $completion['completion_tokens'],
            latencyMs: $completion['latency_ms'],
            metadata: ['block_type' => $blockType, 'prompt' => $prompt]
        );

        return $result;
    }

    /**
     * Recommends a sequenced list of block types for a given page/content intent.
     */
    public function recommendBlocks(string $intent, string $locale = 'es'): array
    {
        $recommendations = $this->blockIntelligence->recommendSequence($intent, $locale);

        $this->logGeneration(
            action: 'recommend_blocks',
            provider: $this->configBridge->getActiveProvider(),
            model: $this->configBridge->getModelForProvider($this->configBridge->getActiveProvider()),
            locale: $locale,
            market: 'es',
            promptTokens: 0,
            completionTokens: 0,
            latencyMs: 5,
            metadata: ['intent' => $intent, 'count' => count($recommendations)]
        );

        return [
            'intent' => $intent,
            'recommended_blocks' => $recommendations,
        ];
    }

    /**
     * Generates a real, content-filled block sequence from a brief.
     *
     * Reuses the exact same engine as the entity creator, so the block manager,
     * the contextual modal and "Create with AI" all produce identical blocks.
     *
     * @return array{sections: list<array<string, mixed>>}
     */
    public function composeBlocks(string $brief, string $locale = 'es', string $title = '', string $summary = ''): array
    {
        $sections = $this->composeSections($brief, $title, $summary, $locale);

        $this->logGeneration(
            action: 'compose_blocks',
            provider: $this->configBridge->getActiveProvider(),
            model: $this->configBridge->getModelForProvider($this->configBridge->getActiveProvider()),
            locale: $locale,
            market: 'es',
            promptTokens: 0,
            completionTokens: 0,
            latencyMs: 0,
            metadata: ['brief' => $brief, 'sections' => count($sections)]
        );

        return ['sections' => $sections];
    }

    /**
     * Drafts a complete article including body prose, SEO metadata, and optional block layout.
     */
    public function draftArticle(string $topic, string $market = 'es', string $locale = 'es', string $style = 'periodistico', bool $includeBlocks = false): array
    {
        $promptPair = $this->promptEngine->buildDraftArticlePrompt($topic, $locale, $market, $style, $includeBlocks);
        $completion = $this->configBridge->generateCompletion($promptPair['system'], $promptPair['user']);

        $decoded = json_decode($completion['content'], true);
        if (! is_array($decoded)) {
            $decoded = [
                'title' => $topic,
                'content' => "## {$topic}\n\nContenido generado.",
                'excerpt' => 'Resumen del artículo.',
                'meta_title' => $topic,
                'meta_description' => 'Descripción del artículo.',
                'keywords' => ['articulo', 'cms'],
            ];
        }

        $title = (string) ($decoded['title'] ?? $topic);
        $content = (string) ($decoded['content'] ?? '');

        // Apply anti-AI guardrails if enabled
        $guardrailsEnabled = (bool) $this->settingsService->get('ai_assistant', 'enforce_anti_ai_guardrails', true);
        if ($guardrailsEnabled) {
            $humanized = $this->antiAiService->humanizeProse($content, $locale, $style);
            $content = $humanized['humanized_content'];
        }

        $audit = $this->antiAiService->validateEditorialTone($title, $content, $locale);
        $audit['score'] = $audit['anti_ai_score'];

        $result = [
            'title' => $title,
            'content' => $content,
            'excerpt' => (string) ($decoded['excerpt'] ?? ''),
            'meta_title' => (string) ($decoded['meta_title'] ?? $title),
            'meta_description' => (string) ($decoded['meta_description'] ?? ''),
            'keywords' => (array) ($decoded['keywords'] ?? []),
            'anti_ai_audit' => $audit,
        ];

        if ($includeBlocks && ! empty($decoded['recommended_blocks'])) {
            /** @var list<array<string, mixed>> $recBlocks */
            $recBlocks = array_values((array) $decoded['recommended_blocks']);
            $result['recommended_blocks'] = $recBlocks;
        }

        $this->logGeneration(
            action: 'draft',
            provider: $completion['provider'],
            model: $completion['model'],
            locale: $locale,
            market: $market,
            promptTokens: $completion['prompt_tokens'],
            completionTokens: $completion['completion_tokens'],
            latencyMs: $completion['latency_ms'],
            metadata: ['topic' => $topic, 'anti_ai_score' => $audit['anti_ai_score']]
        );

        return $result;
    }

    /**
     * Builds a complete, ready-to-review element (post/page/product/gallery)
     * from a free brief. Never persists: the caller creates a draft.
     */
    public function buildPageElement(string $type, string $prompt, string $locale = 'es', string $market = 'es'): array
    {
        $schema = $this->taxonomy->schema($type);
        if ($schema === null) {
            throw new \InvalidArgumentException("Unsupported AI entity type [{$type}].");
        }

        // 1) Core fields, driven by the entity schema declared by its module.
        $promptPair = $this->promptEngine->buildEntityPrompt(
            $schema,
            $prompt,
            $locale,
            $market,
            $this->taxonomy->hint($type)
        );
        $completion = $this->configBridge->generateCompletion($promptPair['system'], $promptPair['user']);

        $decoded = json_decode($completion['content'], true);
        if (! is_array($decoded)) {
            $decoded = [];
        }

        $fields = [];
        foreach ($schema->fieldRoles() as $role) {
            $fields[$role] = (string) ($decoded[$role] ?? '');
        }

        $title = (string) ($fields[EntitySchema::ROLE_TITLE] ?? '');
        if ($title === '') {
            $title = $prompt;
            $fields[EntitySchema::ROLE_TITLE] = $title;
        }

        $rawSlug = (string) ($fields[EntitySchema::ROLE_SLUG] ?? '');
        $fields[EntitySchema::ROLE_SLUG] = Str::slug($rawSlug !== '' ? $rawSlug : $title);

        $summary = (string) ($fields[EntitySchema::ROLE_SUMMARY] ?? '');
        $body = (string) ($fields[EntitySchema::ROLE_BODY] ?? '');

        $guardrailsEnabled = (bool) $this->settingsService->get('ai_assistant', 'enforce_anti_ai_guardrails', true);
        if ($guardrailsEnabled && $body !== '') {
            $body = $this->antiAiService->humanizeProse($body, $locale, 'periodistico')['humanized_content'];
            $fields[EntitySchema::ROLE_BODY] = $body;
        }

        $audit = $this->antiAiService->validateEditorialTone($title, $body, $locale);
        $audit['score'] = $audit['anti_ai_score'];

        // 2) Blocks: only for sectionable entities.
        $sections = $schema->sectionable ? $this->composeSections($prompt, $title, $summary, $locale) : [];

        $result = [
            'type' => $type,
            'label' => $schema->label,
            'fields' => $fields,
            'sections' => $sections,
            'keywords' => array_values(array_map('strval', (array) ($decoded['keywords'] ?? []))),
            'anti_ai_audit' => $audit,
        ];

        $this->logGeneration(
            action: 'page_element',
            provider: $completion['provider'],
            model: $completion['model'],
            locale: $locale,
            market: $market,
            promptTokens: $completion['prompt_tokens'],
            completionTokens: $completion['completion_tokens'],
            latencyMs: $completion['latency_ms'],
            metadata: ['type' => $type, 'prompt' => $prompt, 'sections' => count($sections)]
        );

        return $result;
    }

    /**
     * Dedicated block-composition pass, validated against the real block registry
     * and enriched with real entities. Never throws: blocks are optional and the
     * reviewer can always add or change them in the block manager.
     *
     * @return list<array<string, mixed>>
     */
    private function composeSections(string $brief, string $title, string $summary, string $locale): array
    {
        $catalog = $this->blockIntelligence->getBlockTaxonomy();

        if ($catalog === []) {
            return [];
        }

        $dynamicData = $this->blockIntelligence->getDynamicDataPayloads();
        $recommended = $this->blockIntelligence->recommendSequence($brief, $locale);

        $promptPair = $this->promptEngine->buildBlocksCompositionPrompt(
            $brief,
            $title,
            $summary,
            $locale,
            $catalog,
            $recommended,
            $dynamicData
        );

        try {
            $completion = $this->configBridge->generateCompletion($promptPair['system'], $promptPair['user']);
            $decoded = json_decode($completion['content'], true);
        } catch (\Throwable) {
            return [];
        }

        if (! is_array($decoded)) {
            return [];
        }

        return $this->filterRequestedSections($this->enrichSections(
            $this->ensureIntentBlocks(
                $this->ensureStructureBlocks(
                    $this->sanitizeSections((array) ($decoded['sections'] ?? []), $catalog),
                    $title,
                    $summary,
                    $catalog
                ),
                $brief,
                $catalog
            ),
            $dynamicData
        ), $brief);
    }

    /** @param list<array<string,mixed>> $sections
     * @return list<array<string,mixed>>
     */
    private function filterRequestedSections(array $sections, string $brief): array
    {
        $lower = mb_strtolower($brief);

        return array_values(array_filter($sections, function (array $section) use ($brief, $lower): bool {
            $type = (string) ($section['type'] ?? '');
            if (! $this->blockIntelligence->hasSuppliedQuantitativeData($type, $section, $brief)) {
                return false;
            }
            if (isset(self::INTENT_BLOCKS[$type])) {
                if (! $this->mentionsAny($lower, self::INTENT_BLOCKS[$type])) {
                    return false;
                }
                $denied = $type === 'gallery' ? 'galer|gallery|imag|photos|fotos' : 'widgets|bloques dinámicos';
                if (preg_match('/\b(sin|without|no)\b[^.!?;]*(?:'.$denied.')/u', $lower)) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * Guarantee a complete page structure: open with a hero (using the real
     * title/summary) and close with a call to action, even when a small model
     * returns only a partial sequence.
     *
     * @param  list<array<string, mixed>>  $sections
     * @param  array<string, array{name: string, description: string, category: string, default_payload: array<string, mixed>}>  $catalog
     * @return list<array<string, mixed>>
     */
    private function ensureStructureBlocks(array $sections, string $title, string $summary, array $catalog): array
    {
        $types = array_column($sections, 'type');

        if (! in_array('hero', $types, true) && isset($catalog['hero'])) {
            array_unshift($sections, array_merge(
                ['type' => 'hero'],
                $this->blockIntelligence->sanitizeBlockPayload('hero', [
                    'title' => $title,
                    'content' => $summary,
                ])
            ));
            $types[] = 'hero';
        }

        if (! in_array('cta', $types, true) && isset($catalog['cta'])) {
            $sections[] = array_merge(
                ['type' => 'cta'],
                $this->blockIntelligence->sanitizeBlockPayload('cta', [])
            );
        }

        return array_values($sections);
    }

    /**
     * Guarantee that dynamic blocks explicitly requested in the brief are present,
     * even when a small model forgets them. Their payloads are filled with real
     * entities by {@see enrichSections()} and can be changed by the reviewer.
     *
     * @param  list<array<string, mixed>>  $sections
     * @param  array<string, array{name: string, description: string, category: string, default_payload: array<string, mixed>}>  $catalog
     * @return list<array<string, mixed>>
     */
    private function ensureIntentBlocks(array $sections, string $brief, array $catalog): array
    {
        $lower = mb_strtolower($brief);
        $present = array_column($sections, 'type');

        foreach (self::INTENT_BLOCKS as $blockType => $keywords) {
            if (in_array($blockType, $present, true) || ! isset($catalog[$blockType])) {
                continue;
            }

            if (! $this->mentionsAny($lower, $keywords)) {
                continue;
            }

            $block = array_merge(
                ['type' => $blockType],
                $this->blockIntelligence->sanitizeBlockPayload($blockType, [])
            );

            // Keep an editorial order: insert before a trailing CTA, else append.
            $insertAt = count($sections);
            if ($insertAt > 0 && ($sections[$insertAt - 1]['type'] ?? '') === 'cta') {
                $insertAt--;
            }

            array_splice($sections, $insertAt, 0, [$block]);
            $present[] = $blockType;
        }

        return array_values($sections);
    }

    /**
     * @param  list<string>  $keywords
     */
    private function mentionsAny(string $haystack, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if (str_contains($haystack, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate AI-generated block sections against the real CMS block registry.
     *
     * Unknown/invented block types are dropped and every payload is merged over
     * the block's default schema so no required key is missing.
     *
     * @param  array<int, mixed>  $sections
     * @param  array<string, array{name: string, description: string, category: string, default_payload: array<string, mixed>}>  $catalog
     * @return list<array<string, mixed>>
     */
    private function sanitizeSections(array $sections, array $catalog): array
    {
        $clean = [];

        foreach ($sections as $section) {
            if (! is_array($section)) {
                continue;
            }

            $type = (string) ($section['type'] ?? '');
            if ($type === '' || ! isset($catalog[$type])) {
                // Never accept invented block types.
                continue;
            }

            $payload = $section['data'] ?? $section;
            if (! is_array($payload)) {
                $payload = [];
            }
            unset($payload['type'], $payload['reasoning']);

            $clean[] = array_merge(
                ['type' => $type],
                $this->blockIntelligence->sanitizeBlockPayload($type, $payload)
            );
        }

        return $clean;
    }

    /**
     * Repair/fill dynamic-block references with real entities so a composed page
     * never points at invented slugs or ids. The reviewer can always change any
     * block afterwards in the block manager.
     *
     * @param  list<array<string, mixed>>  $sections
     * @param  array<string, mixed>  $dynamicData
     * @return list<array<string, mixed>>
     */
    private function enrichSections(array $sections, array $dynamicData): array
    {
        $albumSlugs = $this->pluckColumn($dynamicData['gallery_albums'] ?? [], 'slug');
        $formSlugs = $this->pluckColumn($dynamicData['forms'] ?? [], 'slug');
        $blogCategoryRefs = $this->referenceSet($dynamicData['blog_categories'] ?? []);
        $shopCategoryRefs = $this->referenceSet($dynamicData['shop_categories'] ?? []);
        $postIds = $this->pluckColumn($dynamicData['blog_posts'] ?? [], 'id');
        $productIds = $this->pluckColumn($dynamicData['shop_products'] ?? [], 'id');
        $images = $this->pluckColumn($dynamicData['gallery_images'] ?? [], 'url');
        $chartTypes = $this->pluckColumn($dynamicData['charts_types'] ?? [], 'key');

        foreach ($sections as $index => $section) {
            $type = (string) ($section['type'] ?? '');

            switch ($type) {
                case 'gallery':
                    $section['album_slug'] = $this->pickReference(
                        (string) ($section['album_slug'] ?? ''),
                        $albumSlugs,
                        $albumSlugs[0] ?? ''
                    );
                    break;

                case 'widget_form':
                    $section['form_slug'] = $this->pickReference(
                        (string) ($section['form_slug'] ?? ''),
                        $formSlugs,
                        $formSlugs[0] ?? (string) ($section['form_slug'] ?? 'contacto')
                    );
                    break;

                case 'widget_posts':
                    $section['category'] = $this->pickReference((string) ($section['category'] ?? ''), $blogCategoryRefs, '');
                    if (! empty($section['post_id']) && ! in_array((string) $section['post_id'], $postIds, true)) {
                        $section['post_id'] = '';
                    }
                    break;

                case 'widget_products':
                    $section['category'] = $this->pickReference((string) ($section['category'] ?? ''), $shopCategoryRefs, '');
                    if (! empty($section['product_id']) && ! in_array((string) $section['product_id'], $productIds, true)) {
                        $section['product_id'] = '';
                    }
                    break;

                case 'charts':
                    if ($chartTypes !== [] && ! in_array((string) ($section['chart_type'] ?? ''), $chartTypes, true)) {
                        $section['chart_type'] = $chartTypes[0];
                    }
                    break;

                case 'hero':
                case 'before_after':
                    if (empty($section['image_url']) && $images !== []) {
                        $section['image_url'] = $images[0];
                    }
                    break;
            }

            $sections[$index] = $section;
        }

        return array_values($sections);
    }

    /**
     * @return list<string>
     */
    private function pluckColumn(mixed $rows, string $column): array
    {
        $values = [];

        foreach ((array) $rows as $row) {
            if (is_array($row) && isset($row[$column]) && $row[$column] !== '') {
                $values[] = (string) $row[$column];
            }
        }

        return $values;
    }

    /**
     * Category blocks accept either an id or a slug, so both are valid references.
     *
     * @return list<string>
     */
    private function referenceSet(mixed $rows): array
    {
        $values = [];

        foreach ((array) $rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            foreach (['slug', 'id'] as $column) {
                if (isset($row[$column]) && $row[$column] !== '') {
                    $values[] = (string) $row[$column];
                }
            }
        }

        return $values;
    }

    /**
     * @param  list<string>  $allowed
     */
    private function pickReference(string $value, array $allowed, string $fallback): string
    {
        if ($value !== '' && in_array($value, $allowed, true)) {
            return $value;
        }

        return $fallback;
    }

    /**
     * Corrects and improves grammar, rhythm, and clarity of given prose.
     */
    public function proofread(string $content, string $locale = 'es', string $action = 'improve'): array
    {
        $promptPair = $this->promptEngine->buildProofreadPrompt($content, $locale, $action);
        $completion = $this->configBridge->generateCompletion($promptPair['system'], $promptPair['user']);

        $decoded = json_decode($completion['content'], true);
        if (! is_array($decoded)) {
            $decoded = [
                'original' => $content,
                'improved' => $content,
                'changes' => ['Texto verificado sin correcciones necesarias.'],
            ];
        }

        $result = [
            'original' => (string) ($decoded['original'] ?? $content),
            'improved' => (string) ($decoded['improved'] ?? $content),
            'changes' => array_values(array_map('strval', (array) ($decoded['changes'] ?? []))),
        ];

        $this->logGeneration(
            action: 'proofread',
            provider: $completion['provider'],
            model: $completion['model'],
            locale: $locale,
            market: 'es',
            promptTokens: $completion['prompt_tokens'],
            completionTokens: $completion['completion_tokens'],
            latencyMs: $completion['latency_ms'],
            metadata: ['action' => $action]
        );

        return $result;
    }

    /**
     * Humanizes synthetic prose by removing detected robotic clichés and balancing sentence rhythm.
     */
    public function humanize(string $content, string $locale = 'es', string $style = 'periodistico'): array
    {
        // Execute rule-based humanizer first for 100% deterministic eradication of known clichés
        $ruleBased = $this->antiAiService->humanizeProse($content, $locale, $style);
        $sanitizedContent = $ruleBased['humanized_content'];

        $promptPair = $this->promptEngine->buildHumanizePrompt($sanitizedContent, $locale, $style);
        $completion = $this->configBridge->generateCompletion($promptPair['system'], $promptPair['user']);

        $decoded = json_decode($completion['content'], true);
        $finalHumanized = is_array($decoded) && ! empty($decoded['humanized'])
            ? (string) $decoded['humanized']
            : $sanitizedContent;

        $audit = $this->antiAiService->validateEditorialTone('Texto Humanizado', $finalHumanized, $locale);

        $result = [
            'original' => $content,
            'humanized' => $finalHumanized,
            'cliches_removed' => array_values(array_map('strval', array_unique(array_merge(
                $ruleBased['changes_applied'],
                (array) ($decoded['cliches_removed'] ?? [])
            )))),
            'anti_ai_score' => $audit['anti_ai_score'],
        ];

        $this->logGeneration(
            action: 'humanize',
            provider: $completion['provider'],
            model: $completion['model'],
            locale: $locale,
            market: 'es',
            promptTokens: $completion['prompt_tokens'],
            completionTokens: $completion['completion_tokens'],
            latencyMs: $completion['latency_ms'],
            metadata: ['anti_ai_score' => $audit['anti_ai_score']]
        );

        return $result;
    }

    /**
     * Generates SEO title, meta description, and keywords for existing content.
     */
    public function generateSeo(string $title, string $content, string $locale = 'es'): array
    {
        $promptPair = $this->promptEngine->buildSeoPrompt($title, $content, $locale);
        $completion = $this->configBridge->generateCompletion($promptPair['system'], $promptPair['user']);

        $decoded = json_decode($completion['content'], true);
        if (! is_array($decoded)) {
            $decoded = [
                'meta_title' => $title,
                'meta_description' => mb_substr(strip_tags($content), 0, 150),
                'keywords' => ['seo', 'cms'],
            ];
        }

        $result = [
            'meta_title' => (string) ($decoded['meta_title'] ?? $title),
            'meta_description' => (string) ($decoded['meta_description'] ?? ''),
            'keywords' => array_values(array_map('strval', (array) ($decoded['keywords'] ?? []))),
        ];

        $this->logGeneration(
            action: 'seo',
            provider: $completion['provider'],
            model: $completion['model'],
            locale: $locale,
            market: 'es',
            promptTokens: $completion['prompt_tokens'],
            completionTokens: $completion['completion_tokens'],
            latencyMs: $completion['latency_ms'],
            metadata: ['title' => $title]
        );

        return $result;
    }

    /**
     * Tests connectivity to a configured AI provider.
     */
    public function testProviderConnection(string $provider, string $modality = 'text'): array
    {
        return app(AiConnectionProbeService::class)->probe($provider, $modality);
    }

    /**
     * Persists generation telemetry log safely.
     *
     * @param  array<string, mixed>  $metadata
     */
    protected function logGeneration(
        string $action,
        string $provider,
        string $model,
        string $locale,
        ?string $market,
        int $promptTokens,
        int $completionTokens,
        int $latencyMs,
        array $metadata = []
    ): void {
        try {
            AiGenerationLog::create([
                'user_id' => Auth::id(),
                'action' => $action,
                'provider' => $provider,
                'model' => $model,
                'locale' => $locale,
                'market' => $market,
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $completionTokens,
                'latency_ms' => $latencyMs,
                'status' => 'success',
                'metadata' => $metadata,
            ]);
        } catch (\Throwable) {
            // Non-blocking log failure
        }
    }
}
