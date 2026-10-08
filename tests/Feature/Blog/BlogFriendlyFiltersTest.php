<?php

declare(strict_types=1);

use App\Core\Filters\FilterSet;
use App\Models\FilterPage;
use Database\Seeders\PlatformShowcaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Modules\Blog\Models\Post;
use Modules\Blog\Models\Tag;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(PlatformShowcaseSeeder::class);
    config(['cms.theme' => 'db-cyber', 'cms.admin_theme' => 'dbcms', 'cms.page_cache.enabled' => false]);
});

afterEach(function (): void {
    Cache::flush();
});

function taggedPost(): Tag
{
    $post = Post::published()->firstOrFail();
    $tag = Tag::create(['name' => 'Laravel', 'slug' => 'laravel']);
    $post->tags()->attach($tag->getKey());

    return $tag;
}

test('a friendly tag filter renders a dynamic filter page', function (): void {
    taggedPost();

    $response = $this->get('/es/es/blog/laravel');

    $response->assertOk();

    // Not curated → dynamic page, noindex.
    expect((string) $response->getContent())->toContain('noindex, follow');
});

test('an unknown filter value is a 404', function (): void {
    taggedPost();

    $this->get('/es/es/blog/etiqueta/does-not-exist')->assertNotFound();
});

test('a non-canonical permutation 301s to the canonical URL', function (): void {
    taggedPost();

    $this->get('/es/es/blog/orden/recientes/etiqueta/laravel')
        ->assertStatus(301)
        ->assertRedirect('/es/es/blog/laravel/recientes');
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

    $response = $this->get('/es/es/blog/laravel')->assertOk();

    expect((string) $response->getContent())->toContain('index, follow');
});
