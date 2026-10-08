<?php

declare(strict_types=1);

use App\Core\Modules\ModuleManager;
use App\Core\Search\SearchEngine;
use App\Core\Search\SearchIndexer;
use App\Models\CmsModule;
use App\Models\Language;
use App\Models\Market;
use App\Models\User;
use Modules\Blog\Models\Post;
use Modules\Gallery\Models\Gallery;
use Modules\Pages\Models\Page;
use Modules\Shop\Models\Product;

beforeEach(function (): void {
    app(ModuleManager::class)->discoverModules();
    CmsModule::query()->update(['is_active' => true]);
    config(['cms.page_cache.enabled' => false]);
    $es = Language::firstOrCreate(['code' => 'es'], ['name' => 'Spanish', 'native_name' => 'Español', 'is_default' => true, 'is_active' => true]);
    $en = Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'native_name' => 'English', 'is_active' => true]);
    $this->market = Market::firstOrCreate(['code' => 'es'], ['name' => 'Spain', 'default_locale' => 'es', 'is_default' => true, 'is_active' => true]);
    $this->other = Market::firstOrCreate(['code' => 'uk'], ['name' => 'United Kingdom', 'default_locale' => 'en', 'is_active' => true]);
    $this->market->languages()->sync([$es->getKey(), $en->getKey()]);
    $this->other->languages()->sync([$en->getKey()]);
    $this->page = Page::withoutEvents(fn () => Page::create(['template' => 'default', 'layout' => 'app', 'is_published' => true, 'published_at' => now()]));
    $this->page->translations()->createMany([
        ['locale' => 'es', 'title' => 'Global Spain page', 'slug' => 'global-spain-page', 'meta_description' => 'Public translated page'],
        ['locale' => 'en', 'title' => 'Global English page', 'slug' => 'global-english-page', 'meta_description' => 'Public translated page'],
    ]);
    $this->gallery = Gallery::withoutEvents(fn () => Gallery::create(['title' => 'Live Gallery', 'slug' => 'root-album', 'is_active' => true]));
    $this->gallery->markets()->sync([$this->market->getKey()]);
    $this->gallery->translations()->createMany([
        ['locale' => 'es', 'title' => 'Álbum público', 'slug' => 'album-publico', 'description' => 'Gallery visible semantic body'],
        ['locale' => 'en', 'title' => 'Public album', 'slug' => 'public-album', 'description' => 'English gallery semantic body'],
    ]);
});

test('discovery uses public markets translations and localized routes instead of defaults', function (): void {
    $product = Product::withoutEvents(fn () => Product::create(['sku' => 'DISCOVERY-TEST', 'price' => 10, 'is_active' => true]));
    $product->markets()->sync([$this->market->getKey()]);
    $product->translations()->createMany([['locale' => 'es', 'name' => 'Artículo público', 'slug' => 'articulo-publico'], ['locale' => 'en', 'name' => 'Public product', 'slug' => 'public-product']]);
    $pages = $this->get('/sitemaps/pages.xml')->assertOk()->getContent();
    expect($pages)->toContain('/es/es/global-spain-page', '/es/en/global-english-page', '/uk/en/global-english-page')->not->toContain('/uk/es/');
    $galleryXml = $this->get('/sitemaps/galleries.xml')->assertOk()->getContent();
    expect($galleryXml)->toContain('/es/es/galeria/album-publico', '/es/en/gallery/public-album')->not->toContain('/uk/en/gallery/public-album', '/gallery/root-album');
    $products = $this->get('/sitemaps/products.xml')->assertOk()->getContent();
    expect($products)->toContain('/es/es/tienda/articulo-publico')->not->toContain('/es/es/shop/');
    $this->get('/es/es/sitemap.xml')->assertOk()->assertSee('global-spain-page')->assertDontSee('/es/es/global-english-page');
    $this->get('/es/es/llms.txt')->assertOk()->assertSee('global-spain-page')->assertDontSee('/es/es/global-english-page');
    $this->get('/es/es/llms-full.txt')->assertOk()->assertSee('Gallery visible semantic body');
    $this->get('/llms-full.txt')->assertOk()->assertSee('Gallery visible semantic body');
});

test('gallery public pages isolate markets and never substitute an absent translation', function (): void {
    $this->get('/es/es/galeria/album-publico')->assertOk()->assertSee('Álbum público')
        ->assertSee('href="'.url('/es/en/gallery/public-album').'"', false)
        ->assertDontSee('hreflang="en-UK"', false);
    $this->get('/uk/en/gallery/public-album')->assertNotFound();
    $this->get('/uk/en/gallery/root-album')->assertNotFound();
    $this->gallery->translations()->where('locale', 'en')->delete();
    $this->get('/es/en/gallery/root-album')->assertNotFound();
});

test('one-market language URLs are reachable and hreflang targets resolve', function (): void {
    $this->other->update(['is_active' => false]);
    $this->get('/es/global-spain-page')->assertOk()->assertSee('Global Spain page');
    $this->get('/en/global-english-page')->assertOk()->assertSee('Global English page');
    $this->get('/es/galeria/album-publico')->assertOk();
    $this->get('/en/gallery/public-album')->assertOk();
});

test('page URLs never replace a requested locale with a different translation', function (): void {
    $this->get('/es/es/global-english-page')->assertNotFound();
    $this->page->translations()->where('locale', 'es')->delete();
    $this->get('/es/es/global-english-page')->assertNotFound();
    $this->get('/es/en/global-english-page')->assertOk()->assertSee('Global English page');
});

test('real article and gallery routes describe published entities in their json graphs', function (): void {
    $author = User::factory()->create(['name' => 'Public Author']);
    $post = Post::withoutEvents(fn () => Post::create(['user_id' => $author->getKey(), 'is_published' => true, 'published_at' => now()]));
    $post->translations()->create(['locale' => 'es', 'title' => 'Título del artículo', 'slug' => 'semantic-article', 'content' => 'Texto público', 'summary' => 'Resumen público', 'schema_type' => 'Article']);
    $this->gallery->items()->create(['image_url' => '/storage/fixture.webp', 'title' => 'Public image', 'alt_text' => 'Verified image caption']);
    foreach ([['/es/es/blog/semantic-article', 'Article', 'headline', 'Título del artículo'], ['/es/es/galeria/album-publico', 'ImageGallery', 'name', 'Álbum público']] as [$path, $type, $field, $value]) {
        $html = $this->get($path)->assertOk()->getContent();
        preg_match_all('~<script type="application/ld\\+json">(.*?)</script>~s', $html, $matches);
        $nodes = collect($matches[1])->flatMap(fn ($json) => json_decode($json, true, flags: JSON_THROW_ON_ERROR)['@graph'] ?? []);
        $entity = $nodes->firstWhere('@type', $type);
        expect($entity)->toBeArray()->and($entity[$field])->toBe($value);
        if ($type === 'Article') {
            expect($entity['author']['name'])->toBe('Public Author')->and($entity)->toHaveKey('datePublished');
        } else {
            expect($entity['image'][0]['contentUrl'])->toBe(url('/storage/fixture.webp'));
        }
        expect($entity)->not->toHaveKey('aggregateRating')->not->toHaveKey('offers');
    }
});

test('public search scopes stale global index entries before totals facets and suggestions', function (): void {
    $author = User::factory()->create();
    $post = Post::withoutEvents(fn () => Post::create(['user_id' => $author->getKey(), 'is_published' => true, 'published_at' => now()]));
    $post->markets()->sync([$this->market->getKey()]);
    $post->translations()->create(['locale' => 'en', 'title' => 'Boundary search phrase', 'slug' => 'boundary-search', 'content' => 'Boundary public body']);
    app(SearchIndexer::class)->indexEntity('post', $post->getKey());
    $engine = app(SearchEngine::class);
    $visible = $engine->search('boundary', 'en', market: 'es');
    expect($visible->total)->toBe(1)->and($visible->facets['post']['count'])->toBe(1);
    $hidden = $engine->search('boundary', 'en', market: 'uk');
    expect($hidden->total)->toBe(0)->and($hidden->hits)->toBeEmpty()->and($hidden->facets)->toBeEmpty()->and($hidden->suggestions)->toBeEmpty();
    $this->get('/uk/en/search/boundary?no_cache=1')->assertOk()->assertDontSee('Boundary search phrase')->assertDontSee('/post');
    $post->markets()->detach();
    $global = $engine->search('boundary', 'en', market: 'uk');
    expect($global->total)->toBe(1)->and($global->hits[0]->url)->toContain('/uk/en/blog/boundary-search');
});

test('feeds and discovery never expose private foreign-market or untranslated content', function (): void {
    $author = User::factory()->create();
    foreach ([['visible', true, $this->market], ['foreign', true, $this->other], ['draft', false, $this->market]] as [$slug, $published, $market]) {
        $post = Post::withoutEvents(fn () => Post::create(['user_id' => $author->getKey(), 'is_published' => $published, 'published_at' => now()]));
        $post->markets()->sync([$market->getKey()]);
        $post->translations()->create(['locale' => 'es', 'title' => $slug, 'slug' => $slug, 'content' => 'Public article body']);
    }
    $this->get('/feed.xml')->assertOk()->assertSee('/es/es/blog/visible')->assertDontSee('/blog/foreign')->assertDontSee('/blog/draft');
    $this->get('/sitemaps/posts.xml')->assertOk()->assertDontSee('/blog/foreign')->assertDontSee('/blog/draft');
    $this->get('/es/es/llms-full.txt')->assertOk()->assertSee('visible')->assertDontSee('foreign')->assertDontSee('draft');
    app(ModuleManager::class)->disable('blog');
    $this->get('/sitemaps/posts.xml')->assertOk()->assertDontSee('/blog/visible');
    $this->get('/es/es/llms-full.txt')->assertOk()->assertDontSee('## Article: visible');
});
