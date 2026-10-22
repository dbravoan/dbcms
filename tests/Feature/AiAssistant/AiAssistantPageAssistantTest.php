<?php

declare(strict_types=1);

use App\Content\Pages\Models\Page;
use App\Core\Blocks\Contracts\BlockRegistryInterface;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\AiAssistant\Contracts\AiAssistantDomainServiceInterface;
use Modules\AiAssistant\Services\BlockIntelligenceService;
use Modules\Blog\Models\Post;
use Modules\Gallery\Models\Gallery;
use Modules\Shop\Models\Product;
use Tests\Support\AiCompletionFixture;
use Tests\Support\AiVisualModulesComposition;
use Tests\Support\CmsFixtures;
use Tests\Support\VisualContentFixtures;

uses(RefreshDatabase::class, AiVisualModulesComposition::class);

beforeEach(function (): void {
    config(['services.gemini.key' => 'fake-gemini-key']);
    Http::preventStrayRequests();
    CmsFixtures::localization();
    CmsFixtures::identities();
    VisualContentFixtures::home();
    VisualContentFixtures::form();
    VisualContentFixtures::post();
    VisualContentFixtures::product();
    VisualContentFixtures::gallery();
    AiCompletionFixture::configure();
    $this->admin = User::where('email', 'admin@db-cms.io')->firstOrFail();
});

test('page_element generates a complete element payload', function (): void {
    $response = $this->actingAs($this->admin, 'web')->postJson(route('admin.ai_assistant.generate'), [
        'action' => 'page_element',
        'element_type' => 'post',
        'prompt' => 'Buenas prácticas de arquitectura modular en CMS multi-mercado',
        'locale' => 'es',
    ]);

    $response->assertOk()->assertJsonPath('success', true);
    expect($response->json('data.fields.title'))->not->toBeEmpty()
        ->and($response->json('data.fields.summary'))->not->toBeEmpty()
        ->and($response->json('data.fields.meta_title'))->not->toBeEmpty()
        ->and($response->json('data.type'))->toBe('post');
});

test('create_element always creates a DRAFT and never publishes', function (string $type, string $flag): void {
    $response = $this->actingAs($this->admin, 'web')->postJson(route('admin.ai_assistant.create_element'), [
        'type' => $type,
        'fields' => [
            'title' => 'Elemento IA '.$type,
            'slug' => 'elemento-ia-'.$type,
            'summary' => 'Resumen generado por IA.',
            'body' => 'Contenido generado por IA.',
            'meta_title' => 'Meta título IA',
            'meta_description' => 'Meta descripción IA',
        ],
        'locale' => 'es',
    ]);

    $response->assertOk()->assertJsonPath('success', true);

    $id = (int) $response->json('id');
    $entity = match ($type) {
        'post' => Post::find($id),
        'page' => Page::find($id),
        'product' => Product::find($id),
        'gallery' => Gallery::find($id),
    };

    expect($entity)->not->toBeNull();
    expect((bool) $entity->getAttribute($flag))->toBeFalse();
    expect($response->json('edit_url'))->toContain((string) $id);
})->with([
    ['post', 'is_published'],
    ['page', 'is_published'],
    ['product', 'is_active'],
    ['gallery', 'is_active'],
]);

test('page_element composes real CMS blocks for sectionable types', function (string $type): void {
    $taxonomy = app(BlockIntelligenceService::class)->getBlockTaxonomy();

    $response = $this->actingAs($this->admin, 'web')->postJson(route('admin.ai_assistant.generate'), [
        'action' => 'page_element',
        'element_type' => $type,
        'prompt' => 'Landing de servicios de consultoría',
        'locale' => 'es',
    ]);

    $response->assertOk()->assertJsonPath('success', true);

    $sections = $response->json('data.sections');
    expect($sections)->toBeArray()->not->toBeEmpty();

    // Every composed block must exist in the real block registry.
    foreach ($sections as $section) {
        expect($section)->toHaveKey('type');
        expect(array_key_exists($section['type'], $taxonomy))->toBeTrue();
    }
})->with(['page', 'post', 'product']);

test('page_element does not compose blocks for non-sectionable types', function (): void {
    $response = $this->actingAs($this->admin, 'web')->postJson(route('admin.ai_assistant.generate'), [
        'action' => 'page_element',
        'element_type' => 'gallery',
        'prompt' => 'Álbum de fotos del equipo',
        'locale' => 'es',
    ]);

    $response->assertOk();
    expect($response->json('data.sections'))->toBe([]);
});

test('create_element persists the composed blocks into the block manager', function (): void {
    $service = app(AiAssistantDomainServiceInterface::class);
    $element = $service->buildPageElement('page', 'Landing de servicios', 'es', 'es');

    expect($element['sections'])->not->toBeEmpty();

    $response = $this->actingAs($this->admin, 'web')->postJson(route('admin.ai_assistant.create_element'), [
        'type' => 'page',
        'fields' => $element['fields'],
        'sections' => $element['sections'],
        'locale' => 'es',
    ]);

    $response->assertOk()->assertJsonPath('success', true);
    expect($response->json('sections'))->toBe(count($element['sections']));

    $page = Page::with('sections')->find((int) $response->json('id'));
    expect($page)->not->toBeNull();
    expect($page->sections)->not->toBeEmpty();
});

test('composed dynamic blocks reference real entities, not invented ones', function (): void {
    $dynamic = app(BlockRegistryInterface::class)->getDynamicDataPayloads();

    $formSlugs = array_column($dynamic['forms'], 'slug');
    $albumSlugs = array_column($dynamic['gallery_albums'], 'slug');
    $imageUrls = array_column($dynamic['gallery_images'], 'url');

    $sections = app(AiAssistantDomainServiceInterface::class)
        ->buildPageElement('page', 'Landing de servicios con formulario y galería', 'es', 'es')['sections'];

    $types = array_column($sections, 'type');
    expect($types)->toContain('gallery')->toContain('widget_form');

    foreach ($sections as $section) {
        if ($section['type'] === 'gallery') {
            // Invented album slug must be repaired with a real one.
            expect($albumSlugs)->toContain($section['album_slug']);
        }
        if ($section['type'] === 'widget_form') {
            expect($formSlugs)->toContain($section['form_slug']);
        }
        if ($section['type'] === 'hero') {
            // Missing image is filled with a real gallery image.
            expect($imageUrls)->toContain($section['image_url']);
        }
    }
});

test('brief-requested dynamic blocks are always present with real references', function (): void {
    $dynamic = app(BlockRegistryInterface::class)->getDynamicDataPayloads();
    $albumSlugs = array_column($dynamic['gallery_albums'], 'slug');
    $formSlugs = array_column($dynamic['forms'], 'slug');

    $sections = app(AiAssistantDomainServiceInterface::class)
        ->buildPageElement(
            'page',
            'Landing con galería de proyectos, formulario de contacto, últimos artículos del blog y productos de la tienda',
            'es',
            'es'
        )['sections'];

    $types = array_column($sections, 'type');

    // The AI omitted widget_posts/widget_products: the safety net adds them.
    expect($types)->toContain('gallery', 'widget_form', 'widget_posts', 'widget_products');

    foreach ($sections as $section) {
        if ($section['type'] === 'gallery') {
            expect($albumSlugs)->toContain($section['album_slug']);
        }
        if ($section['type'] === 'widget_form') {
            expect($formSlugs)->toContain($section['form_slug']);
        }
    }
});

test('composed pages open with a hero and close with a call to action', function (): void {
    $types = array_column(
        app(AiAssistantDomainServiceInterface::class)
            ->buildPageElement('page', 'Página corporativa de servicios', 'es', 'es')['sections'],
        'type'
    );

    expect($types)->not->toBeEmpty();
    expect($types[0])->toBe('hero');
    expect($types[count($types) - 1])->toBe('cta');
});
