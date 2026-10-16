<?php

declare(strict_types=1);

use App\Core\Filters\FilterSet;
use App\Models\FilterPage;
use App\Models\Market;
use App\Services\Capabilities\FilterNavigationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\Post;
use Modules\Blog\Models\Tag;
use Tests\Support\BlogComposition;
use Tests\Support\CmsFixtures;
use Tests\Support\FilterFixtures;

uses(RefreshDatabase::class, BlogComposition::class);

beforeEach(function (): void {
    CmsFixtures::localization();
    CmsFixtures::identities();
    FilterFixtures::blog();
    config(['cms.theme' => 'db-cyber', 'cms.admin_theme' => 'dbcms', 'cms.page_cache.enabled' => false]);
});

afterEach(function (): void {
    Cache::flush();
});

function taggedPost(): Tag
{
    $tag = Tag::create(['name' => 'Laravel', 'slug' => 'laravel']);
    foreach (Post::published()->get() as $post) {
        $post->tags()->attach($tag->getKey());
    }

    return $tag;
}

test('a friendly tag filter renders a dynamic filter page', function (): void {
    taggedPost();

    $response = $this->get('/es/blog/laravel');

    $response->assertOk();

    // Not curated → dynamic page, noindex.
    expect((string) $response->getContent())->toContain('noindex, follow');
});

test('an unknown filter value is a 404', function (): void {
    taggedPost();

    $this->get('/es/blog/etiqueta-does-not-exist')->assertNotFound();
});

test('a non-canonical permutation 301s to the canonical URL', function (): void {
    taggedPost();

    $this->get('/es/blog/recientes/laravel')
        ->assertStatus(301)
        ->assertRedirect('/es/blog/laravel/recientes');
});

test('a curated filter page becomes indexable when it passes the quality gate', function (): void {
    taggedPost();

    $set = new FilterSet('blog', 'es', 'es', ['tag' => ['laravel']]);

    FilterPage::create([
        'module' => 'blog',
        'market_code' => 'es',
        'locale' => 'es',
        'signature' => $set->signature(),
        'title' => 'Laravel',
        'heading' => 'Laravel',
        'meta_description' => 'Contenido sobre Laravel',
        'is_indexable' => true,
    ]);

    $response = $this->get('/es/blog/laravel')->assertOk();

    expect((string) $response->getContent())->toContain('index, follow');
});

test('editorial article cards only expose public categories without lazy loading', function (string $path, bool $active, bool $otherMarket): void {
    config(['cms.theme' => 'ia-practica']);
    taggedPost();
    $category = Category::query()->sole();
    $category->update(['is_active' => $active]);
    if ($otherMarket) {
        $market = Market::create(['code' => 'fr', 'name' => 'France', 'currency' => 'EUR', 'default_locale' => 'es', 'is_active' => true]);
        $category->markets()->sync([$market->getKey()]);
    }

    $response = $this->get($path)->assertOk()->assertSee('Artículo 1');
    if ($active && ! $otherMarket) {
        $response->assertSee('Arquitectura');
    } else {
        $response->assertDontSee('Arquitectura');
        $this->get('/es/es/blog/arquitectura')->assertNotFound();
    }
})->with(['/es/es/blog', '/es/blog/laravel'])->with([
    'public local category' => [true, false],
    'inactive category' => [false, false],
    'other market category' => [true, true],
]);

test('filter vocabularies reject an unavailable market or language context', function (string $market, string $locale): void {
    expect(app(FilterNavigationService::class)->dimensions('blog', $market, $locale))->toHaveKey('error');
})->with([['unknown', 'es'], ['es', 'fr']]);
