<?php

declare(strict_types=1);

use App\Models\FilterPage;
use App\Models\Market;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Blog\Models\Post;
use Modules\Blog\Models\Tag;
use Tests\Support\BlogShopComposition;
use Tests\Support\CmsFixtures;
use Tests\Support\FilterFixtures;

uses(RefreshDatabase::class, BlogShopComposition::class);

beforeEach(function (): void {
    CmsFixtures::localization();
    CmsFixtures::identities();
    FilterFixtures::blog();
    FilterFixtures::shop();
    $this->admin = User::where('email', 'admin@db-cms.io')->firstOrFail();
    config(['cms.theme' => 'db-cyber', 'cms.admin_theme' => 'dbcms', 'cms.page_cache.enabled' => false]);
});

test('the curation screen lists the filterable modules and the curated landings', function (): void {
    $response = $this->actingAs($this->admin, 'web')->get('/admin/seo/filters');

    $response->assertOk()
        ->assertSee('blog', false)
        ->assertSee('shop', false);
});

test('an admin can curate a filter landing from its friendly path', function (): void {
    $tag = Tag::create(['name' => 'Laravel', 'slug' => 'laravel']);

    $response = $this->actingAs($this->admin, 'web')->post('/admin/seo/filters', [
        'module' => 'blog',
        'market_code' => 'es',
        'locale' => 'es',
        'path' => 'laravel',
        'title' => 'Artículos sobre Laravel',
        'heading' => 'Laravel',
        'meta_description' => 'Todos nuestros artículos sobre Laravel.',
        'is_indexable' => '1',
    ]);

    $response->assertRedirect(route('admin.seo.filters.index'));

    $page = FilterPage::firstOrFail();

    expect($page->module)->toBe('blog')
        ->and($page->path)->toBe('laravel')
        ->and($page->signature)->toBe('tag=laravel')
        ->and($page->is_indexable)->toBeTrue();
});

test('an unknown intermediate filter segment is rejected instead of becoming a search', function (): void {
    $this->actingAs($this->admin, 'web')->post('/admin/seo/filters', [
        'module' => 'blog',
        'market_code' => 'es',
        'locale' => 'es',
        'path' => 'etiqueta-no-existe/recientes',
        'is_indexable' => '1',
    ])->assertSessionHasErrors(['path']);

    expect(FilterPage::count())->toBe(0);
});

test('a curated landing becomes indexable with its own text and canonical', function (): void {
    $tag = Tag::create(['name' => 'Laravel', 'slug' => 'laravel']);
    foreach (Post::published()->get() as $post) {
        $post->tags()->attach($tag->getKey());
    }

    $this->actingAs($this->admin, 'web')->post('/admin/seo/filters', [
        'module' => 'blog',
        'market_code' => 'es',
        'locale' => 'es',
        'path' => 'laravel',
        'title' => 'Artículos sobre Laravel',
        'heading' => 'Laravel',
        'meta_description' => 'Todos nuestros artículos sobre Laravel.',
        'is_indexable' => '1',
    ]);

    $html = (string) $this->get('/es/es/blog/laravel')->assertOk()->getContent();

    expect($html)->toContain('index, follow')
        ->and($html)->toContain('Artículos sobre Laravel')
        ->and($html)->toContain('rel="canonical" href="'.url('/es/blog/laravel').'"');
});

test('an uncurated filter landing stays noindex', function (): void {
    $tag = Tag::create(['name' => 'Laravel', 'slug' => 'laravel']);
    foreach (Post::published()->get() as $post) {
        $post->tags()->attach($tag->getKey());
    }

    $html = (string) $this->get('/es/es/blog/laravel')->assertOk()->getContent();

    expect($html)->toContain('noindex, follow');
});

test('curated indexable landings are published in the sitemap and llms.txt', function (): void {
    // The landing must pass the quality gate (min_results) to be published.
    $tag = Tag::create(['name' => 'Laravel', 'slug' => 'laravel']);

    foreach (Post::published()->get() as $published) {
        $published->tags()->attach($tag->getKey());
    }

    $this->actingAs($this->admin, 'web')->post('/admin/seo/filters', [
        'module' => 'blog',
        'market_code' => 'es',
        'locale' => 'es',
        'path' => 'laravel',
        'heading' => 'Laravel',
        'meta_description' => 'Artículos sobre Laravel.',
        'is_indexable' => '1',
    ]);

    $sitemap = (string) $this->get('/es/es/sitemap.xml')->getContent();
    expect($sitemap)->toContain('/es/blog/laravel');

    // The segmented sitemap index (what crawlers actually read) exposes it too.
    expect((string) $this->get('/sitemap.xml')->getContent())->toContain('sitemaps/filters.xml');
    expect((string) $this->get('/sitemaps/filters.xml')->getContent())->toContain('/es/blog/laravel');

    $llms = (string) $this->get('/es/es/llms.txt')->getContent();
    expect($llms)->toContain('Curated Filters & Collections')
        ->and($llms)->toContain('/es/blog/laravel');
});

test('a curated landing can be updated and removed', function (): void {
    $page = FilterPage::create([
        'module' => 'blog',
        'market_code' => 'es',
        'locale' => 'es',
        'signature' => 'tag=laravel',
        'path' => 'laravel',
        'is_indexable' => false,
    ]);

    $this->actingAs($this->admin, 'web')->put('/admin/seo/filters/'.$page->id, [
        'title' => 'Nuevo título',
        'is_indexable' => '1',
    ])->assertRedirect(route('admin.seo.filters.index'));

    expect($page->fresh()->title)->toBe('Nuevo título')
        ->and($page->fresh()->is_indexable)->toBeTrue();

    $this->actingAs($this->admin, 'web')->delete('/admin/seo/filters/'.$page->id)
        ->assertRedirect(route('admin.seo.filters.index'));

    expect(FilterPage::find($page->id))->toBeNull();
});

test('the resolved canonical path is stored for a hierarchical category', function (): void {
    $market = Market::where('code', 'es')->firstOrFail();

    $this->actingAs($this->admin, 'web')->post('/admin/seo/filters', [
        'module' => 'blog',
        'market_code' => $market->code,
        'locale' => 'es',
        'path' => 'arquitectura',
        'title' => 'Arquitectura',
        'is_indexable' => '1',
    ]);

    // Persist the resolved path of the declared category.
    expect(FilterPage::firstOrFail()->path)->toBe('arquitectura');
});

test('the screen renders the curated rows with their google state and site link', function (): void {
    FilterPage::create([
        'module' => 'blog',
        'market_code' => 'es',
        'locale' => 'es',
        'signature' => 'sort=recientes',
        'path' => 'recientes',
        'title' => 'Artículos recientes',
        'is_indexable' => true,
    ]);

    $html = (string) $this->actingAs($this->admin, 'web')->get('/admin/seo/filters')->assertOk()->getContent();

    expect($html)->toContain('recientes')
        ->toContain('Artículos recientes')
        ->toContain('Sí')
        // Search/sort rows carry Google's recommendation notice.
        ->toContain('Google recomienda no indexar')
        ->toContain('/es/es/blog/recientes');
});

test('the screen speaks plain spanish and drops the old wording', function (): void {
    // The form (and therefore the field labels) is shown on demand.
    $html = (string) $this->actingAs($this->admin, 'web')->get('/admin/seo/filters?new=1')->assertOk()->getContent();

    expect($html)->toContain('Filtros y búsquedas')
        ->toContain('Indexar un filtro')
        ->toContain('Dirección del filtro')
        ->toContain('Título en Google')
        ->toContain('Descripción en Google')
        ->toContain('En Google');

    foreach (['Curar una landing', 'Editar landing', 'Eliminar landing', 'landings curadas', 'Ruta canónica', 'Título SEO', 'Encabezado H1', 'Meta descripción'] as $oldWording) {
        expect($html)->not->toContain($oldWording);
    }
});

test('the screen opens pre-filled when the editor presses index in google on the site', function (): void {
    $tag = Tag::create(['name' => 'Laravel', 'slug' => 'laravel']);

    foreach (Post::published()->get() as $published) {
        $published->tags()->attach($tag->getKey());
    }

    $html = (string) $this->actingAs($this->admin, 'web')
        ->get('/admin/seo/filters?module=blog&market_code=es&locale=es&path=laravel')
        ->assertOk()
        ->getContent();

    // Address, composed title/description, the prefill notice and the intro
    // block manager — all server-rendered, so there is no dialog to open.
    expect($html)->toContain('value="laravel"')
        ->toContain('Laravel')
        ->toContain('Hemos rellenado el título')
        ->toContain('name="intro_blocks_json"')
        ->toContain('blockManager(');
});

test('indexing a search or sort page shows the google recommendation notice', function (): void {
    $html = (string) $this->actingAs($this->admin, 'web')
        ->get('/admin/seo/filters?module=blog&market_code=es&locale=es&path=recientes')
        ->assertOk()
        ->getContent();

    expect($html)->toContain('Google recomienda no indexar');
});

test('the intro of a curated filter is composed with blocks and rendered on the site', function (): void {
    $tag = Tag::create(['name' => 'Laravel', 'slug' => 'laravel']);

    foreach (Post::published()->get() as $published) {
        $published->tags()->attach($tag->getKey());
    }

    $blocks = (string) json_encode([
        'es' => [[
            'id' => 'sec_1',
            'type' => 'text',
            'title' => 'Todo sobre Laravel',
            'content' => 'Recopilamos aquí nuestras guías de Laravel.',
            'format' => 'markdown',
            'container_style' => 'transparent',
        ]],
    ]);

    $this->actingAs($this->admin, 'web')->post('/admin/seo/filters', [
        'module' => 'blog',
        'market_code' => 'es',
        'locale' => 'es',
        'path' => 'laravel',
        'title' => 'Laravel',
        'heading' => 'Laravel',
        'meta_description' => 'Guías de Laravel.',
        'is_indexable' => '1',
        'intro_blocks_json' => $blocks,
    ])->assertRedirect(route('admin.seo.filters.index'));

    $page = FilterPage::firstOrFail();

    // The blocks are persisted as sections + translations (CMS shape).
    expect($page->sections()->count())->toBe(1)
        ->and($page->sections()->firstOrFail()->type)->toBe('text');

    // And rendered above the results on the site.
    $html = (string) $this->get('/es/es/blog/laravel')->assertOk()->getContent();

    expect($html)->toContain('cms-filter-intro')
        ->toContain('Todo sobre Laravel')
        ->toContain('Recopilamos aquí nuestras guías de Laravel.');
});

test('the intro blocks of a curated filter can be edited from the panel', function (): void {
    $page = FilterPage::create([
        'module' => 'blog',
        'market_code' => 'es',
        'locale' => 'es',
        'signature' => 'tag=laravel',
        'path' => 'laravel',
        'is_indexable' => false,
    ]);

    $this->actingAs($this->admin, 'web')
        ->get('/admin/seo/filters?edit='.$page->id)
        ->assertOk()
        ->assertSee('name="intro_blocks_json"', false)
        ->assertSee('value="laravel"', false);
});

test('an admin sees the index in google button on a filter page of the site', function (): void {
    $tag = Tag::create(['name' => 'Laravel', 'slug' => 'laravel']);

    // El mercado heredado `es` existe en la matriz: el tag debe asociarse a
    // posts asignados a ese mercado para que la landing resuelva.
    $market = Market::where('code', 'es')->firstOrFail();

    foreach (Post::published()->whereHas('markets', fn ($q) => $q->where('markets.id', $market->id))->get() as $published) {
        $published->tags()->attach($tag->getKey());
    }

    $html = (string) $this->actingAs($this->admin, 'web')
        ->get('/es/es/blog/laravel')
        ->assertOk()
        ->getContent();

    expect($html)->toContain('frontoffice-bar-google-link')
        ->toContain('Indexar en Google')
        ->toContain('admin/seo/filters?module=blog');
});
