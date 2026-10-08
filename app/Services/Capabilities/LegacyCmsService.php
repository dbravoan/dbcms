<?php

declare(strict_types=1);

namespace App\Services\Capabilities;

use App\Core\Content\Contracts\EditorialWorkflowInterface;
use App\Core\Modules\ModuleManager;
use App\Events\PagePublished;
use App\Events\PostPublished;
use App\Events\ProductSaved;
use App\Models\CmsModule;
use App\Models\Language;
use App\Models\Market;
use App\Models\User;
use App\Services\Ai\AntiAiEditorialService;
use App\Services\Seo\ContentQualityAuditorService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\AiAssistant\Services\AiActionRunner;
use Modules\AiAssistant\Services\AiPromptTemplateEngine;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\Post;
use Modules\Forms\Models\FormSubmission;
use Modules\Pages\Models\Page;
use Modules\Shop\Models\Product;

class LegacyCmsService
{
    public function __construct(
        protected EntityCrudService $entities,
        protected ContentQualityAuditorService $auditorService,
        protected AntiAiEditorialService $antiAiService,
    ) {}

    public const SUPPORTED_TOOLS = [
        'cms_inspect_system',
        'cms_search_content',
        'cms_get_entity',
        'cms_research_topic_context',
        'cms_get_editorial_prompt',
        'cms_validate_editorial_tone',
        'cms_humanize_prose',
        'cms_ai_generate_block',
        'cms_ai_draft_article',
        'cms_ai_humanize',
        'cms_ai_page_element',
        'cms_ai_proofread',
        'cms_ai_seo',
        'cms_ai_recommend_blocks',
        'cms_ai_compose_blocks',
        'cms_ai_generate_image',
        'cms_ai_generate_video',
        'cms_ai_research_and_draft',
        'cms_ai_orchestrate_content',
        'cms_publish_post',

        'cms_publish_page',
        'cms_publish_product',
        'cms_list_modules',
        'cms_list_posts',
        'cms_get_post',
        'cms_create_post',
        'cms_update_post',
        'cms_delete_post',
        'cms_list_categories',
        'cms_create_category',
        'cms_update_category',
        'cms_delete_category',
        'cms_list_pages',
        'cms_create_page',
        'cms_delete_page',
        'cms_list_leads',
        'cms_audit_seo_geo',
        'cms_list_filter_dimensions',
        'cms_resolve_filter_url',
        'cms_browse_content',
        'cms_search',
    ];

    /**
     * Execute a specific MCP tool by name.
     *
     * @param  array<string, mixed>  $args
     */
    public function execute(string $toolName, array $args): mixed
    {
        return match ($toolName) {
            // ==========================================
            // High-Level Topology & State Inspection
            // ==========================================
            'cms_inspect_system' => $this->inspectSystem(),

            // ==========================================
            // Cross-Entity Unified Search
            // ==========================================
            'cms_search_content' => $this->searchContent($args),

            // ==========================================
            // Unified Entity Inspection
            // ==========================================
            'cms_get_entity' => $this->getEntity($args),

            // ==========================================
            // Topic Research & Internal Link Analysis
            // ==========================================
            'cms_research_topic_context' => $this->researchTopicContext($args),

            // ==========================================
            // Editorial Guidelines & Anti-AI Humanization
            // ==========================================
            'cms_get_editorial_prompt' => $this->getEditorialPrompt($args),
            'cms_validate_editorial_tone' => $this->antiAiService->validateEditorialTone(
                (string) ($args['title'] ?? ''),
                (string) ($args['content'] ?? ''),
                (string) ($args['locale'] ?? 'es')
            ),
            'cms_humanize_prose' => $this->antiAiService->humanizeProse(
                (string) ($args['content'] ?? ''),
                (string) ($args['locale'] ?? 'es'),
                (string) ($args['style'] ?? 'periodistico')
            ),

            // ==========================================
            // Intelligent AI Copilot Tools (Brother Module)
            // ==========================================
            'cms_ai_generate_block',
            'cms_ai_draft_article',
            'cms_ai_humanize',
            'cms_ai_page_element',
            'cms_ai_proofread',
            'cms_ai_seo',
            'cms_ai_recommend_blocks',
            'cms_ai_compose_blocks',
            'cms_ai_generate_image',
            'cms_ai_generate_video',
            'cms_ai_research_and_draft',
            'cms_ai_orchestrate_content' => $this->executeAiTool($toolName, $args),

            // ==========================================
            // Publishing Pipeline
            // ==========================================
            'cms_publish_post' => $this->publishPost($args),
            'cms_publish_page' => $this->publishPage($args),
            'cms_publish_product' => $this->publishProduct($args),

            // ==========================================
            // Modules Discovery (Legacy Parity)
            // ==========================================
            'cms_list_modules' => CmsModule::query()->get(['id', 'identifier', 'name', 'version', 'description', 'is_active', 'is_core', 'is_pageable', 'is_installed', 'created_at', 'updated_at'])->toArray(),

            // ==========================================
            // Blog Posts CRUD (Legacy Parity)
            // ==========================================
            'cms_list_posts' => $this->listPosts($args),
            'cms_get_post' => $this->getPost($args),
            'cms_create_post' => $this->createPost($args),
            'cms_update_post' => $this->updatePost($args),
            'cms_delete_post' => $this->deletePost($args),

            // ==========================================
            // Categories CRUD (Legacy Parity)
            // ==========================================
            'cms_list_categories' => Category::with(['translations', 'parent.translations', 'children.translations'])->get()->toArray(),
            'cms_create_category' => $this->createCategory($args),
            'cms_update_category' => $this->updateCategory($args),
            'cms_delete_category' => $this->deleteCategory($args),

            // ==========================================
            // Pages CRUD (Legacy Parity)
            // ==========================================
            'cms_list_pages' => Page::with(['translations', 'sections'])->get()->toArray(),
            'cms_create_page' => $this->createPage($args),
            'cms_delete_page' => $this->deletePage($args),

            // ==========================================
            // Leads & Forms (Legacy Parity)
            // ==========================================
            'cms_list_leads' => FormSubmission::with('form')->latest()->take((int) ($args['limit'] ?? 10))->get()->toArray(),

            // ==========================================
            // GEO & SEO Content Auditor
            // ==========================================
            'cms_audit_seo_geo' => $this->auditorService->audit(
                (string) ($args['title'] ?? ''),
                (string) ($args['content'] ?? ''),
                (string) ($args['meta_description'] ?? ''),
                (string) ($args['primary_keyword'] ?? '')
            ),

            // ==========================================
            // WebMCP: navigate the dynamic filter space
            // ==========================================
            'cms_list_filter_dimensions' => app(FilterNavigationService::class)->dimensions(
                isset($args['module']) ? (string) $args['module'] : null,
                isset($args['market']) ? (string) $args['market'] : null,
                isset($args['locale']) ? (string) $args['locale'] : null
            ),
            'cms_resolve_filter_url' => app(FilterNavigationService::class)->resolve(
                (string) ($args['module'] ?? 'blog'),
                (string) ($args['path'] ?? ''),
                isset($args['market']) ? (string) $args['market'] : null,
                isset($args['locale']) ? (string) $args['locale'] : null
            ),
            'cms_browse_content' => app(FilterNavigationService::class)->browse(
                (string) ($args['module'] ?? 'blog'),
                $args
            ),
            'cms_search' => app(SearchNavigationService::class)->search($args),

            default => throw new \InvalidArgumentException("Unknown tool: {$toolName}"),
        };
    }

    /**
     * Inspects high-level CMS topology, modules, markets, languages, and entity counts.
     *
     * @return array<string, mixed>
     */
    protected function inspectSystem(): array
    {
        return [
            'status' => 'operational',
            'cms_version' => '1.2.0',
            'modules' => CmsModule::all()->toArray(),
            'markets' => Market::with('languages')->get()->toArray(),
            'languages' => Language::where('is_active', true)->get()->toArray(),
            'statistics' => [
                'posts_count' => Post::count(),
                'pages_count' => Page::count(),
                'categories_count' => Category::count(),
                'products_count' => class_exists(Product::class) ? Product::count() : 0,
            ],
            'editorial_rules' => [
                'language' => 'Spanish (Spain)',
                'tone' => 'Journalistic / Senior Essayist',
                'anti_ai_enforced' => $this->editorialEnforcementEnabled(),
                'max_title_chars' => 60,
                'min_words' => 450,
            ],
        ];
    }

    /**
     * Cross-entity search across posts, pages, products, and categories.
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    protected function searchContent(array $args): array
    {
        $queryText = trim((string) ($args['query'] ?? ''));
        $entityType = (string) ($args['entity_type'] ?? 'all');
        $limit = max(1, min(50, (int) ($args['limit'] ?? 10)));
        $results = [];

        if (empty($queryText)) {
            return ['results' => [], 'total_count' => 0];
        }

        // Search Posts
        if (in_array($entityType, ['all', 'post'], true)) {
            $posts = Post::with(['translations', 'category.translations'])
                ->whereHas('translations', function ($q) use ($queryText): void {
                    $q->where('title', 'LIKE', "%{$queryText}%")
                        ->orWhere('content', 'LIKE', "%{$queryText}%")
                        ->orWhere('summary', 'LIKE', "%{$queryText}%");
                })
                ->take($limit)
                ->get();

            foreach ($posts as $post) {
                $trans = $post->translations->first();
                $results[] = [
                    'type' => 'post',
                    'id' => $post->id,
                    'title' => $trans ? $trans->title : 'Sin título',
                    'slug' => $trans ? $trans->slug : '',
                    'url' => '/blog/'.($trans ? $trans->slug : ''),
                    'summary' => Str::limit((string) ($trans ? $trans->summary : ''), 120),
                    'is_published' => $post->is_published,
                ];
            }
        }

        // Search Pages
        if (in_array($entityType, ['all', 'page'], true)) {
            $pages = Page::with('translations')
                ->whereHas('translations', function ($q) use ($queryText): void {
                    $q->where('title', 'LIKE', "%{$queryText}%")
                        ->orWhere('slug', 'LIKE', "%{$queryText}%");
                })
                ->take($limit)
                ->get();

            foreach ($pages as $page) {
                $trans = $page->translations->first();
                $results[] = [
                    'type' => 'page',
                    'id' => $page->id,
                    'title' => $trans ? $trans->title : 'Página',
                    'slug' => $trans ? $trans->slug : '',
                    'url' => '/'.($trans ? $trans->slug : ''),
                    'summary' => 'Página',
                    'is_published' => $page->is_published,
                ];
            }
        }

        // Search Products (Shop)
        if (in_array($entityType, ['all', 'product'], true) && class_exists(Product::class)) {
            $products = Product::with('translations')
                ->whereHas('translations', function ($q) use ($queryText): void {
                    $q->where('name', 'LIKE', "%{$queryText}%")
                        ->orWhere('description', 'LIKE', "%{$queryText}%");
                })
                ->take($limit)
                ->get();

            foreach ($products as $prod) {
                $trans = $prod->translations->first();
                $results[] = [
                    'type' => 'product',
                    'id' => $prod->id,
                    'title' => $trans ? $trans->name : 'Producto',
                    'slug' => $trans ? $trans->slug : '',
                    'url' => '/tienda/'.($trans ? $trans->slug : ''),
                    'summary' => "Precio: {$prod->price} EUR",
                    'is_published' => $prod->is_active,
                ];
            }
        }

        return [
            'query' => $queryText,
            'total_count' => count($results),
            'results' => array_slice($results, 0, $limit),
        ];
    }

    /**
     * Unified entity getter by type and ID.
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    protected function getEntity(array $args): array
    {
        $type = (string) ($args['type'] ?? '');
        $id = (int) ($args['id'] ?? 0);

        return match ($type) {
            'post' => Post::with(['translations', 'author', 'category.translations', 'markets', 'sections.translations'])->findOrFail($id)->toArray(),
            'page' => Page::with(['translations', 'sections'])->findOrFail($id)->toArray(),
            'product' => class_exists(Product::class) ? Product::with(['translations', 'markets'])->findOrFail($id)->toArray() : [],
            'category' => Category::with(['translations', 'parent.translations'])->findOrFail($id)->toArray(),
            default => throw new \InvalidArgumentException("Tipo de entidad no válido: {$type}"),
        };
    }

    /**
     * Topic research against existing corpus for internal linking and context.
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    protected function researchTopicContext(array $args): array
    {
        $topic = trim((string) ($args['topic'] ?? ''));
        $words = array_filter(explode(' ', $topic), fn ($w) => mb_strlen($w) > 3);

        $relatedPosts = [];
        if (! empty($words)) {
            $query = Post::with('translations');
            foreach ($words as $word) {
                $query->orWhereHas('translations', function ($q) use ($word): void {
                    $q->where('title', 'LIKE', "%{$word}%");
                });
            }
            $relatedPosts = $query->take(5)->get();
        }

        $links = [];
        foreach ($relatedPosts as $p) {
            $trans = $p->translations->first();
            if ($trans) {
                $links[] = [
                    'title' => $trans->title,
                    'url' => "/blog/{$trans->slug}",
                    'anchor_suggestion' => $trans->title,
                ];
            }
        }

        $categories = Category::with('translations')->take(6)->get()->map(function ($c) {
            $trans = $c->translations->first();

            return [
                'id' => $c->id,
                'name' => $trans ? $trans->name : 'Categoría',
            ];
        })->all();

        return [
            'topic' => $topic,
            'existing_coverage_count' => count($links),
            'suggested_internal_links' => $links,
            'candidate_categories' => $categories,
            'editorial_notes' => 'Utiliza los enlaces sugeridos para crear enlaces internos naturales en el cuerpo del artículo y transferir autoridad de página.',
        ];
    }

    /**
     * Returns the official Human+AI editorial system prompt.
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    protected function getEditorialPrompt(array $args): array
    {
        $locale = (string) ($args['locale'] ?? 'es');
        $fileName = $locale === 'en'
            ? 'EDITORIAL_HUMAN_AI_SYSTEM_PROMPT.md'
            : 'PROMPT_EDITORIAL_HUMAN_IA.md';
        $filePath = base_path("docs/prompts/{$fileName}");
        $fileContent = File::exists($filePath) ? (string) File::get($filePath) : '';

        if (class_exists(AiPromptTemplateEngine::class)
            && app(ModuleManager::class)->isModuleEnabled('ai_assistant')
        ) {
            try {
                $dynamicPrompt = app(AiPromptTemplateEngine::class)->buildSystemPrompt($locale, $locale);
                $content = ! empty($fileContent)
                    ? $dynamicPrompt."\n\n================================================================================\nGUÍA EDITORIAL COMPLETA Y REGLAS DE REDACCIÓN\n================================================================================\n\n".$fileContent
                    : $dynamicPrompt;
            } catch (\Throwable) {
                $content = $fileContent;
            }
        } else {
            $content = $fileContent;
        }

        return [
            'locale' => $locale,
            'prompt_file' => $fileName,
            'system_prompt' => $content,
        ];
    }

    /**
     * Whether the quality module enforces the editorial bar (configurable).
     */
    protected function editorialEnforcementEnabled(): bool
    {
        try {
            return app(EditorialWorkflowInterface::class)->isEditorialEnforcementEnabled();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Minimum anti-AI score required when enforcement is active.
     */
    protected function editorialMinScore(): int
    {
        try {
            return app(EditorialWorkflowInterface::class)->minEditorialScore();
        } catch (\Throwable) {
            return 85;
        }
    }

    /**
     * Resolves the quality-module audit status of an entity without a
     * compile-time dependency on the extension module (Zero-Knowledge safe).
     */
    protected function resolveAuditStatus(Model $entity): ?string
    {
        return app(EditorialWorkflowInterface::class)->status($entity);
    }

    /**
     * Enriched atomic publisher for blog posts with events and quality audit.
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    protected function publishPost(array $args): array
    {
        $title = (string) $args['title'];
        $content = (string) $args['content'];
        $toneAudit = $this->antiAiService->validateEditorialTone($title, $content);
        if ($this->editorialEnforcementEnabled()) {
            $minScore = $this->editorialMinScore();
            if ($toneAudit['anti_ai_score'] < $minScore || $toneAudit['cliches_found'] !== []) {
                return ['success' => false, 'error' => 'editorial_quality_rejected', 'anti_ai_score' => $toneAudit['anti_ai_score'], 'minimum_score' => $minScore,
                    'cliches_found' => $toneAudit['cliches_found'], 'recommendations' => $toneAudit['recommendations'],
                    'message' => 'El contenido no supera el control de calidad editorial exigido.'];
            }
        }
        $result = $this->createPost($args + ['is_published' => true]);
        $post = $this->entities->resolveEntity('post', $result['post_id']);
        if ($post instanceof Post && (bool) $post->getAttribute('is_published')) {
            event(new PostPublished($post));
        }

        return $result + ['url' => '/blog/'.$result['slug'], 'published' => (bool) $post->getAttribute('is_published'),
            'audit_status' => $this->resolveAuditStatus($post), 'editorial_quality' => [
                'anti_ai_score' => $toneAudit['anti_ai_score'], 'is_human_grade' => $toneAudit['is_human_grade'],
                'burstiness' => $toneAudit['burstiness']['std_dev_words'],
            ]];
    }

    /**
     * Atomic publisher for modular pages.
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    protected function publishPage(array $args): array
    {
        $result = $this->createPage($args + ['is_published' => true]);
        $page = $this->entities->resolveEntity('page', $result['page_id']);
        if ($page instanceof Page && (bool) $page->getAttribute('is_published')) {
            event(new PagePublished($page));
        }

        return $result + ['published' => (bool) $page->getAttribute('is_published'), 'audit_status' => $this->resolveAuditStatus($page)];
    }

    /**
     * Atomic publisher for shop products.
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    protected function publishProduct(array $args): array
    {
        $title = (string) $args['title'];
        $locale = (string) ($args['locale'] ?? 'es');
        $payload = $this->entities->create('product', [
            'attributes' => ['sku' => (string) $args['sku'], 'price' => (float) $args['price'],
                'stock_quantity' => (int) ($args['stock'] ?? 0), 'track_stock' => true, 'publish' => true,
                'category_id' => ! empty($args['category_id']) ? (int) $args['category_id'] : null],
            'translations' => [$locale => ['name' => $title, 'slug' => (string) ($args['slug'] ?? Str::slug($title)),
                'description' => (string) ($args['description'] ?? ''), 'summary' => (string) ($args['summary'] ?? '')]],
        ] + $this->relations($args), $this->actor());

        $product = $this->entities->resolveEntity('product', $payload['id']);
        if ($product instanceof Product) {
            event(new ProductSaved($product));
        }

        return ['success' => true, 'product_id' => $payload['id'], 'sku' => $payload['attributes']['sku'],
            'price' => $payload['attributes']['price'], 'url' => $payload['url'], 'published' => $payload['status'] === 'published',
            'message' => 'Producto guardado.'];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<int, mixed>
     */
    protected function listPosts(array $args): array
    {
        $query = Post::with(['translations', 'author', 'category.translations', 'markets']);
        if (! empty($args['search'])) {
            $search = (string) $args['search'];
            $query->whereHas('translations', function ($q) use ($search): void {
                $q->where('title', 'LIKE', "%{$search}%")->orWhere('summary', 'LIKE', "%{$search}%");
            });
        }
        if (! empty($args['market'])) {
            $mCode = (string) $args['market'];
            $query->whereHas('markets', fn ($m) => $m->where('code', $mCode));
        }
        $limit = (int) ($args['limit'] ?? 10);

        return $query->latest()->take($limit)->get()->toArray();
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    protected function getPost(array $args): array
    {
        $id = (int) ($args['id'] ?? 0);
        $post = Post::with(['translations', 'author', 'category.translations', 'comments', 'markets'])->findOrFail($id);

        return $post->toArray();
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    protected function createPost(array $args): array
    {
        $locale = (string) ($args['locale'] ?? 'es');
        $title = (string) $args['title'];
        $content = (string) $args['content'];
        $summary = (string) ($args['summary'] ?? Str::limit(strip_tags($content), 160));
        $payload = $this->entities->create('post', [
            'attributes' => ['category_id' => ! empty($args['category_id']) ? (int) $args['category_id'] : null,
                'is_featured' => (bool) ($args['is_featured'] ?? false), 'publish' => (bool) ($args['is_published'] ?? false)],
            'translations' => [$locale => ['title' => $title, 'slug' => (string) ($args['slug'] ?? ''),
                'content' => $content, 'summary' => $summary, 'meta_title' => $title, 'meta_description' => $summary]],
        ] + $this->relations($args), $this->actor());

        return ['success' => true, 'message' => 'Artículo guardado.', 'post_id' => $payload['id'], 'slug' => $payload['translations'][$locale]['slug'] ?? null];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    protected function updatePost(array $args): array
    {
        $id = (int) ($args['id'] ?? 0);
        $attributes = array_intersect_key($args, array_flip(['category_id', 'is_featured']));
        if (array_key_exists('is_published', $args)) {
            $attributes['publish'] = (bool) $args['is_published'];
        }
        $locale = (string) ($args['locale'] ?? 'es');
        $translation = array_intersect_key($args, array_flip(['title', 'slug', 'content', 'summary', 'meta_title', 'meta_description']));
        if (isset($translation['title'])) {
            $translation['meta_title'] ??= $translation['title'];
        }
        if (isset($translation['summary'])) {
            $translation['meta_description'] ??= $translation['summary'];
        }
        $this->entities->update('post', $id, ['attributes' => $attributes, 'translations' => $translation === [] ? [] : [$locale => $translation]] + $this->relations($args));

        return ['success' => true, 'message' => "Post #{$id} updated.", 'post' => $this->entities->resolveEntity('post', $id)->toArray()];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    protected function deletePost(array $args): array
    {
        $id = (int) ($args['id'] ?? 0);
        $this->entities->delete('post', $id);

        return ['success' => true, 'message' => "Post #{$id} deleted successfully."];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    protected function createCategory(array $args): array
    {
        $translations = (array) ($args['translations'] ?? []);
        foreach (['es', 'en'] as $locale) {
            if (isset($args['name_'.$locale])) {
                $translations[$locale] = ['name' => (string) $args['name_'.$locale], 'slug' => (string) ($args['slug_'.$locale] ?? Str::slug((string) $args['name_'.$locale]))];
            }
        }
        $hasName = false;
        foreach ($translations as $values) {
            if (is_array($values) && ! empty($values['name'])) {
                $hasName = true;
            }
        }
        if (! $hasName) {
            throw ValidationException::withMessages(['translations' => 'At least one translation needs a name.']);
        }
        $payload = $this->entities->create('blog_category', ['attributes' => ['parent_id' => ! empty($args['parent_id']) ? (int) $args['parent_id'] : null,
            'publish' => (bool) ($args['is_active'] ?? false)], 'translations' => $translations], $this->actor());

        return ['success' => true, 'message' => 'Categoría guardada.', 'category_id' => $payload['id']];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    protected function updateCategory(array $args): array
    {
        $id = (int) ($args['id'] ?? 0);
        $translations = (array) ($args['translations'] ?? []);
        if (array_key_exists('name', $args)) {
            $translations[(string) ($args['locale'] ?? 'es')] = ['name' => (string) $args['name'], 'slug' => Str::slug((string) $args['name'])];
        }
        $attributes = array_intersect_key($args, array_flip(['parent_id', 'is_active']));
        $this->entities->update('blog_category', $id, ['attributes' => $attributes, 'translations' => $translations]);

        return ['success' => true, 'message' => "Category #{$id} updated.", 'category' => $this->entities->resolveEntity('blog_category', $id)->toArray()];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    protected function deleteCategory(array $args): array
    {
        $id = (int) ($args['id'] ?? 0);
        $this->entities->delete('blog_category', $id);

        return ['success' => true, 'message' => "Category #{$id} deleted."];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    protected function createPage(array $args): array
    {
        $locale = (string) ($args['locale'] ?? 'es');
        $title = (string) $args['title'];
        $payload = $this->entities->create('page', [
            'attributes' => ['template' => (string) ($args['template'] ?? 'default'), 'layout' => (string) ($args['layout'] ?? 'app'),
                'publish' => (bool) ($args['is_published'] ?? false)],
            'translations' => [$locale => ['title' => $title, 'slug' => (string) $args['slug'],
                'meta_title' => (string) ($args['meta_title'] ?? $title), 'meta_description' => (string) ($args['meta_description'] ?? $title)]],
        ] + $this->relations($args), $this->actor());

        return ['success' => true, 'message' => 'Página guardada.', 'page_id' => $payload['id'], 'url' => $payload['url']];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    protected function deletePage(array $args): array
    {
        $id = (int) ($args['id'] ?? 0);
        $this->entities->delete('page', $id);

        return ['success' => true, 'message' => "Page #{$id} deleted successfully."];
    }

    private function actor(): User
    {
        $actor = Auth::user();
        if (! $actor instanceof User) {
            throw new AuthenticationException;
        }

        return $actor;
    }

    /**
     * @param  array<string,mixed>  $args
     * @return array<string,mixed>
     */
    private function relations(array $args): array
    {
        $result = [];
        if (array_key_exists('market_codes', $args)) {
            $codes = (array) $args['market_codes'];
            $ids = Market::query()->whereIn('code', $codes)->pluck('id')->all();
            if (count($ids) !== count(array_unique($codes))) {
                throw ValidationException::withMessages(['market_codes' => 'Select existing markets.']);
            }
            $result['market_ids'] = $ids;
        }
        if (isset($args['sections_json'])) {
            $result['sections_json'] = $args['sections_json'];
        }

        return $result;
    }

    /** @param array<string,mixed> $args */
    protected function executeAiTool(string $toolName, array $args): mixed
    {
        $moduleManager = app(ModuleManager::class);
        if (! $moduleManager->isModuleEnabled('ai_assistant')) {
            throw new \RuntimeException('AI Assistant module is not enabled on this CMS instance.');
        }

        $runnerClass = 'Modules\\AiAssistant\\Services\\AiActionRunner';
        if (! class_exists($runnerClass)) {
            throw new \RuntimeException('AI Assistant action runner is not installed.');
        }

        /** @var AiActionRunner $runner */
        $runner = app($runnerClass);

        $action = $runner->actionForMcpTool($toolName);
        if ($action === null) {
            throw new \InvalidArgumentException("Unsupported AI tool: {$toolName}");
        }

        // Same runner the backoffice uses: guaranteed behavioural parity.
        return $runner->run($action, $args);
    }
}
