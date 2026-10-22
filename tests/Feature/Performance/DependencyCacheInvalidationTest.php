<?php

declare(strict_types=1);

use App\Content\Pages\Models\Page;
use App\Http\Middleware\CachePublicPage;
use App\Models\Market;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Services\Cms\PageCacheService;
use Illuminate\Support\Facades\Cache;
use Modules\Blog\Models\Post;
use Modules\Shop\Models\Product;
use Tests\Support\BlogFixtures;
use Tests\Support\BlogShopComposition;
use Tests\Support\CmsFixtures;
use Tests\Support\ShopFixtures;

uses(BlogShopComposition::class);

beforeEach(function (): void {
    CmsFixtures::localization();
    CmsFixtures::identities();
    $home = CmsFixtures::home();
    BlogFixtures::post();
    ShopFixtures::product();
    $products = $home->sections()->create(['type' => 'widget_products', 'order' => 1, 'is_active' => true]);
    $products->translations()->create(['locale' => 'es', 'content_json' => ['title' => 'Products', 'limit' => 4]]);
    $legal = Page::create(['template' => 'default', 'is_published' => true]);
    $legal->translations()->create(['locale' => 'es', 'title' => 'Privacy', 'slug' => 'privacidad']);
    $legal->markets()->sync(Market::pluck('id')->all());
    $menu = Menu::create(['slug' => 'header', 'name' => 'Header', 'location' => 'header', 'is_active' => true]);
    $menu->markets()->sync(Market::pluck('id')->all());
    $item = $menu->items()->create(['type' => 'custom', 'url' => '/privacidad', 'is_active' => true, 'order' => 1]);
    $item->translations()->create(['locale' => 'es', 'title' => 'Privacy']);
    config([
        'cms.theme' => 'db-cyber',
        'cms.admin_theme' => 'dbcms',
        'cms.page_cache.enabled' => true,
    ]);
    app(PageCacheService::class)->invalidate();
});

afterEach(function (): void {
    Cache::flush();
});

test('editing a blog post does not invalidate pages that do not read it', function (): void {
    $this->get('/es/es/privacidad')->assertHeader(CachePublicPage::HIT_HEADER, 'MISS');
    $this->get('/es/es/privacidad')->assertHeader(CachePublicPage::HIT_HEADER, 'HIT');

    $version = app(PageCacheService::class)->version();
    Post::query()->firstOrFail()->touch();
    expect(app(PageCacheService::class)->version())->toBe($version);

    // The legal page does not read posts → it must remain cached.
    $this->get('/es/es/privacidad')->assertHeader(CachePublicPage::HIT_HEADER, 'HIT');
});

test('editing a post invalidates the blog index that lists it', function (): void {
    $this->get('/es/es/blog')->assertHeader(CachePublicPage::HIT_HEADER, 'MISS');
    $this->get('/es/es/blog')->assertHeader(CachePublicPage::HIT_HEADER, 'HIT');

    Post::query()->firstOrFail()->touch();

    $this->get('/es/es/blog')->assertHeader(CachePublicPage::HIT_HEADER, 'MISS');
});

test('editing the menu invalidates the cached home page', function (): void {
    $this->get('/es/es/')->assertHeader(CachePublicPage::HIT_HEADER, 'MISS');
    $this->get('/es/es/')->assertHeader(CachePublicPage::HIT_HEADER, 'HIT');

    MenuItem::query()->firstOrFail()->touch();

    $this->get('/es/es/')->assertHeader(CachePublicPage::HIT_HEADER, 'MISS');
});

test('the public page cache can be purged from the console', function (): void {
    $before = app(PageCacheService::class)->version();

    $this->artisan('cms:cache:purge')->assertSuccessful();

    expect(app(PageCacheService::class)->version())->toBeGreaterThan($before);
});

it('invalidates collection dependencies when a product price changes', function (): void {
    $this->get('/es/es/')->assertOk();
    $this->get('/es/es/')->assertHeader(CachePublicPage::HIT_HEADER, 'HIT');
    $product = Product::query()->firstOrFail();
    $product->update(['price' => $product->price + 1]);
    $this->get('/es/es/')->assertHeader(CachePublicPage::HIT_HEADER, 'MISS');
});
