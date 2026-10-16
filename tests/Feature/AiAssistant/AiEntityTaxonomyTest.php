<?php

declare(strict_types=1);

use App\Content\Pages\Models\Page;
use App\Core\Content\Contracts\EntitySchemaRegistryInterface;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Modules\AiAssistant\Contracts\AiAssistantDomainServiceInterface;
use Modules\AiAssistant\Services\AiTaxonomyService;
use Modules\AiAssistant\Services\BlockIntelligenceService;
use Modules\Blog\Models\Post;
use Tests\Support\AiCompletionFixture;
use Tests\Support\AiVisualModulesComposition;
use Tests\Support\CmsFixtures;
use Tests\Support\VisualContentFixtures;

uses(RefreshDatabase::class, AiVisualModulesComposition::class);

beforeEach(function (): void {
    AiCompletionFixture::configure();
    CmsFixtures::localization();
    CmsFixtures::identities();
    VisualContentFixtures::home();
    VisualContentFixtures::form();
    VisualContentFixtures::post();
    VisualContentFixtures::product();
    VisualContentFixtures::gallery();
    $this->admin = User::where('email', 'admin@db-cms.io')->firstOrFail();
});

test('active modules register their entity schemas in the core registry', function (): void {
    $registry = app(EntitySchemaRegistryInterface::class);

    expect($registry->types())->toContain(
        'post',
        'blog_category',
        'product',
        'shop_category',
        'page',
        'gallery',
        'form',
    );

    expect($registry->get('post')?->sectionable)->toBeTrue()
        ->and($registry->get('blog_category')?->sectionable)->toBeFalse()
        ->and($registry->get('post')?->fieldRoles())->toContain('title', 'summary', 'meta_title');
});

test('the AI taxonomy exposes registered entities and rejects unknown ones', function (): void {
    $taxonomy = app(AiTaxonomyService::class);

    expect($taxonomy->supports('post'))->toBeTrue()
        ->and($taxonomy->supports('does_not_exist'))->toBeFalse()
        ->and($taxonomy->schema('page')?->createView)->toBe('admin.pages.create');
});

test('the assistant resolves the invoked entity from the current view', function (): void {
    $taxonomy = app(AiTaxonomyService::class);

    expect($taxonomy->entityForView('admin.pages.edit')?->type)->toBe('page')
        ->and($taxonomy->entityForView('admin.blog.posts.create')?->type)->toBe('post')
        ->and($taxonomy->entityForView('admin.shop.products.edit')?->type)->toBe('product')
        ->and($taxonomy->entityForView('admin.does.not.exist'))->toBeNull();
});

test('the SEO/GEO block exposes a generic AI extension point without coupling', function (): void {
    $source = (string) file_get_contents(resource_path('views/components/admin/seo-meta-box.blade.php'));

    expect($source)->toContain('seo_block_actions')
        ->and($source)->not->toContain('ai_assistant');

    // The AI module injects its SEO generator into that stack.
    $rendered = Blade::render('<x-admin.seo-meta-box />');
    expect($rendered)->toContain("mode: 'seo'");
});

test('the entity form assistant knows its entity, drops the generic SEO button and resumes', function (): void {
    $page = Page::firstOrFail();

    $response = $this->actingAs($this->admin, 'web')->get(route('admin.pages.edit', $page->id));

    $response->assertOk();
    $response->assertSee("entity: 'page'", false);
    $response->assertDontSee('fillMetadata', false);
    $response->assertSee('resumePendingTask', false);
});

test('publishing a draft stamps its publish date so the front can resolve it', function (): void {
    $post = Post::create([
        'user_id' => $this->admin->id,
        'is_published' => false,
    ]);

    expect($post->published_at)->toBeNull();

    $post->update(['is_published' => true]);

    expect($post->fresh()->published_at)->not->toBeNull();
});

test('the supported allow-list can restrict the exposed entities', function (): void {
    config(['ai_taxonomy.supported' => ['post']]);

    expect(app(AiTaxonomyService::class)->types())->toBe(['post']);
});

test('the create-with-ai trigger is injected into module create forms', function (string $routeName): void {
    $response = $this->actingAs($this->admin, 'web')->get(route($routeName));

    $response->assertOk();
    $response->assertSee('aiCreateFill', false);
    $response->assertSee('admin/ai-assistant/generate/async', false);
})->with([
    'blog posts' => ['admin.blog.posts.create'],
    'pages' => ['admin.pages.create'],
    'shop products' => ['admin.shop.products.create'],
    'galleries' => ['admin.galleries.create'],
    'forms' => ['admin.forms.create'],
]);

test('the block manager delegates AI actions through a generic extension point', function (): void {
    $source = (string) file_get_contents(resource_path('views/components/admin/block-manager.blade.php'));

    // The manager itself stays fully AI-agnostic.
    expect($source)->not->toContain('ai_assistant')
        ->and($source)->not->toContain('cms-open-ai-blocks')
        // Generic extension point in both toolbars (normal + fullscreen).
        ->and(substr_count($source, 'block_manager_actions'))->toBe(2);

    // The AI module injects its trigger into that stack.
    $rendered = Blade::render('<x-admin.block-manager :blocks="[]" :languages="[]" :currentLocale="\'es\'" />');
    expect($rendered)->toContain('cms-open-ai-blocks');
});

test('compose_blocks returns real, content-filled blocks (same engine as the creator)', function (): void {
    $result = app(AiAssistantDomainServiceInterface::class)
        ->composeBlocks('Landing con galería y formulario', 'es');

    expect($result)->toHaveKey('sections');
    expect($result['sections'])->toBeArray()->not->toBeEmpty();

    foreach ($result['sections'] as $section) {
        expect($section)->toHaveKey('type');
        // Real payload: every block carries more than just its type.
        expect(count($section))->toBeGreaterThan(1);
    }
});

test('the AI block generator offers every active block from the registry', function (): void {
    $blocks = app(BlockIntelligenceService::class)->getBlockOptions();

    // More than the old hardcoded 4, including dynamic widgets.
    expect(count($blocks))->toBeGreaterThan(4)
        ->and(array_column($blocks, 'type'))->toContain('widget_products', 'gallery', 'charts');

    $page = Page::firstOrFail();
    $response = $this->actingAs($this->admin, 'web')->get(route('admin.pages.edit', $page->id));

    $response->assertOk();
    $response->assertSee('value="auto"', false);
    $response->assertSee('value="widget_products"', false);
    $response->assertSee('value="charts"', false);
});

test('recommendSequence only emits registered block types', function (): void {
    $service = app(BlockIntelligenceService::class);
    $registered = array_keys($service->getBlockTaxonomy());

    foreach (['landing de servicios', 'contacto', 'página corporativa'] as $intent) {
        foreach ($service->recommendSequence($intent, 'es') as $block) {
            expect($registered)->toContain($block['type']);
        }
    }
});

test('the fixed admin option enums live in config, not hardcoded in views', function (): void {
    expect(config('cms.options.columns'))->not->toBeEmpty()
        ->and(config('cms.options.code_languages'))->not->toBeEmpty()
        ->and(config('cms.options.schema_types'))->not->toBeEmpty()
        ->and(config('cms.options.menu_zones'))->not->toBeEmpty()
        ->and(config('cms.options.per_page'))->toBe([10, 15, 25, 50, 100]);

    $blockForm = (string) file_get_contents(resource_path('views/components/admin/blocks/_block_form_body.blade.php'));

    expect($blockForm)->not->toContain('admin.block_form.col_1')
        ->and($blockForm)->toContain('schemaFields(');
});

test('the workbench exposes an entity-specific brief placeholder', function (): void {
    $response = $this->actingAs($this->admin, 'web')->get(route('admin.ai_assistant.workbench'));

    $response->assertOk();
    $response->assertSee('pagePlaceholders', false);

    // The post placeholder guides the user and includes an example.
    $response->assertSee(__('admin.ai.entity_placeholder_post'), false);
});

test('the create-with-ai form shows a placeholder tailored to the entity', function (string $routeName, string $key): void {
    $response = $this->actingAs($this->admin, 'web')->get(route($routeName));

    $response->assertOk();
    $response->assertSee(__($key), false);
})->with([
    'pages' => ['admin.pages.create', 'admin.ai.entity_placeholder_page'],
    'shop products' => ['admin.shop.products.create', 'admin.ai.entity_placeholder_product'],
    'forms' => ['admin.forms.create', 'admin.ai.entity_placeholder_form'],
]);
