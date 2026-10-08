<?php

declare(strict_types=1);

use App\Core\Content\Contracts\PublicSemanticContributorInterface;
use App\Core\Content\PublicSemanticRegistry;
use App\Core\Modules\ModuleManager;
use App\Models\CmsModule;
use App\Models\Language;
use App\Models\Market;
use App\Models\User;
use App\Services\Cms\PageCacheService;
use App\Services\Seo\PublicContentModifiedService;
use App\Services\Seo\SitemapDocumentPartitioner;
use Modules\Blog\Blocks\BlogPostsBlock;
use Modules\Blog\Models\Post;
use Modules\Pages\Models\Page;
use Modules\Shop\Blocks\ShopProductsBlock;
use Modules\Shop\Models\Product;

beforeEach(function (): void {
    app(ModuleManager::class)->discoverModules();
    CmsModule::query()->update(['is_active' => true]);
    $language = Language::firstOrCreate(['code' => 'es'], ['name' => 'Spanish', 'native_name' => 'Español', 'is_active' => true]);
    $this->market = Market::firstOrCreate(['code' => 'es'], ['name' => 'Spain', 'default_locale' => 'es', 'currency' => 'EUR', 'is_default' => true, 'is_active' => true]);
    $this->market->languages()->sync([$language->getKey()]);
});

test('public modified time includes translated published sections and ignores draft sections', function (): void {
    $this->travelTo(now()->startOfSecond());
    $page = Page::create(['is_published' => true, 'template' => 'default']);
    $translation = $page->translations()->create(['locale' => 'es', 'title' => 'Source', 'slug' => 'source']);
    $this->travel(2)->minutes();
    $translation->update(['title' => 'Updated source']);
    expect(app(PublicContentModifiedService::class)->forEntity($page->fresh()))->toEqual(now());
    $this->travel(2)->minutes();
    $section = $page->sections()->create(['type' => 'text', 'is_active' => true, 'status' => 'published', 'order' => 0]);
    $section->translations()->create(['locale' => 'es', 'content_json' => ['content' => 'Updated public block']]);
    expect(app(PublicContentModifiedService::class)->forEntity($page->fresh()))->toEqual(now());
    $publicTime = now();
    $this->travel(2)->minutes();
    $draft = $page->sections()->create(['type' => 'text', 'is_active' => true, 'status' => 'draft', 'order' => 1]);
    $draft->translations()->create(['locale' => 'es', 'content_json' => ['content' => 'Private']]);
    expect(app(PublicContentModifiedService::class)->forEntity($page->fresh()))->toEqual($publicTime);
});

test('sitemap partitioning obeys both count and uncompressed byte limits', function (): void {
    $partitioner = app(SitemapDocumentPartitioner::class);
    $entries = ['<url><loc>https://example.test/one</loc></url>', '<url><loc>https://example.test/two</loc></url>', '<url><loc>https://example.test/three</loc></url>'];
    $documents = $partitioner->partition($entries, 2, 1024);
    expect($documents)->toHaveCount(2);
    expect(substr_count($documents[0], '<url>'))->toBe(2);
    $bytes = strlen($partitioner->partition([], 2, 1024)[0]) + max(array_map('strlen', $entries)) + 1;
    expect($partitioner->partition($entries, 50000, $bytes))->toHaveCount(3);
    expect($partitioner->partition(array_fill(0, 50001, $entries[0]), PHP_INT_MAX))->toHaveCount(2);
    expect(fn () => $partitioner->partition([str_repeat('x', 1024)], 50000, 1024))->toThrow(LengthException::class);
});

test('scheduled publication changes the HTML and discovery cache namespace without waiting for ttl', function (): void {
    $this->travelTo(now()->startOfSecond());
    $page = Page::create(['is_published' => true, 'published_at' => now()->addSeconds(10), 'template' => 'default']);
    $page->translations()->create(['locale' => 'es', 'title' => 'Scheduled source', 'slug' => 'scheduled-source']);
    $post = Post::create(['user_id' => User::factory()->create()->getKey(), 'is_published' => true, 'published_at' => now()->addSeconds(10)]);
    $post->translations()->create(['locale' => 'es', 'title' => 'Scheduled RSS source', 'slug' => 'scheduled-rss-source', 'content' => 'Public at boundary']);
    app()->setLocale('es');
    config(['cms.page_cache.ttl' => 300]);
    $cache = app(PageCacheService::class);
    $before = $cache->key('es', 'es', '/es/es/');
    expect($cache->ttl())->toBeLessThanOrEqual(10);
    $this->get('/sitemaps/pages.xml')->assertOk()->assertDontSee('scheduled-source');
    $this->get('/feed.xml')->assertOk()->assertDontSee('scheduled-rss-source');
    $this->travel(10)->seconds();
    expect($cache->key('es', 'es', '/es/es/'))->not->toBe($before);
    $this->get('/sitemaps/pages.xml')->assertOk()->assertSee('scheduled-source');
    $this->get('/feed.xml')->assertOk()->assertSee('scheduled-rss-source');
});

test('dynamic blocks cannot leak future articles or products restricted to another market', function (): void {
    $post = Post::create(['user_id' => User::factory()->create()->getKey(), 'is_published' => true, 'published_at' => now()->addDay()]);
    $post->translations()->create(['locale' => 'es', 'title' => 'Embargoed widget source', 'slug' => 'embargoed-widget', 'content' => 'Private']);
    $product = Product::create(['sku' => 'OTHER-MARKET', 'price' => 20, 'is_active' => true]);
    $product->translations()->create(['locale' => 'es', 'name' => 'Restricted widget product', 'slug' => 'restricted-widget']);
    $other = Market::create(['code' => 'fr', 'name' => 'France', 'default_locale' => 'es', 'currency' => 'EUR', 'is_active' => true]);
    $product->markets()->sync([$other->getKey() => ['is_active' => true]]);
    expect((new BlogPostsBlock)->render([], $this->market, 'es'))->not->toContain('Embargoed widget source');
    expect((new ShopProductsBlock)->render([], $this->market, 'es'))->not->toContain('Restricted widget product');
});

test('registered public semantic contributors honour active modules and widget targets', function (): void {
    $registry = app(PublicSemanticRegistry::class);
    $registry->register(new class implements PublicSemanticContributorInterface
    {
        public function key(): string
        {
            return 'qa-public-source';
        }

        public function ownerModule(): string
        {
            return 'blog';
        }

        public function index(Market $market, string $locale): string
        {
            return "QA public source index\n";
        }

        public function corpus(Market $market, string $locale): string
        {
            return "QA verified public source\n";
        }
    });
    $post = Post::create(['user_id' => User::factory()->create()->getKey(), 'is_published' => true, 'published_at' => now()]);
    $post->translations()->create(['locale' => 'es', 'title' => 'Widget public selection', 'slug' => 'widget-public-selection', 'content' => 'Public']);
    expect($registry->widgetText(['type' => 'widget_posts', 'post_id' => $post->getKey()], $this->market, 'es'))->toContain('widget-public-selection');
    expect($registry->widgetText(['type' => 'widget_posts', 'post_id' => 999999], $this->market, 'es'))->toBe('');
    $this->get('/es/es/llms-full.txt')->assertOk()->assertSee('QA verified public source');
    CmsModule::where('identifier', 'blog')->update(['is_active' => false]);
    expect($registry->corpus($this->market, 'es'))->toBe('');
    expect($registry->widgetText(['type' => 'widget_posts'], $this->market, 'es'))->toBe('');
});

test('page metadata follows a direct translation edit and numbered sitemap children are reachable', function (): void {
    $this->travelTo(now()->startOfSecond());
    $page = Page::create(['is_published' => true, 'template' => 'default']);
    $translation = $page->translations()->create(['locale' => 'es', 'title' => 'Timestamp source', 'slug' => 'timestamp-source']);
    $this->travel(2)->minutes();
    $translation->update(['title' => 'Updated metadata source']);
    expect($this->get('/es/es/timestamp-source')->assertOk()->viewData('seoMeta')->schema['dateModified'] ?? null)->toBe(now()->toIso8601String());
    foreach (['second-source', 'third-source'] as $slug) {
        $additional = Page::create(['is_published' => true, 'template' => 'default']);
        $additional->translations()->create(['locale' => 'es', 'title' => $slug, 'slug' => $slug]);
    }
    config(['seo.sitemap.max_urls' => 2]);
    $this->get('/es/es/sitemap.xml')->assertOk()->assertSee('<sitemapindex', false)->assertSee('sitemap-part-2.xml');
    $child = $this->get('/es/es/sitemap-part-2.xml')->assertOk();
    expect(substr_count($child->getContent(), '<url>'))->toBeLessThanOrEqual(2);
    $this->get('/sitemap.xml')->assertOk()->assertSee('/sitemaps/pages-2.xml');
    expect(substr_count($this->get('/sitemaps/pages-2.xml')->assertOk()->getContent(), '<url>'))->toBe(1);
    $this->get('/sitemaps/pages-0.xml')->assertNotFound();
});
