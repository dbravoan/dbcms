<?php

declare(strict_types=1);

use App\Core\Filters\FilterSet;
use App\Http\Middleware\CachePublicPage;
use App\Models\Market;
use App\Services\Cms\PageCacheService;
use Illuminate\Support\Facades\Cache;
use Modules\Shop\Filters\ShopFilters;
use Modules\Shop\Models\Product;
use Modules\Shop\Services\CartService;
use Tests\Support\CmsFixtures;
use Tests\Support\ShopComposition;
use Tests\Support\ShopFixtures;

uses(ShopComposition::class);

beforeEach(function (): void {
    CmsFixtures::localization();
    ShopFixtures::product();
    config(['cms.theme' => 'db-cyber', 'cms.page_cache.enabled' => false]);
    Cache::flush();
    $this->market = Market::where('code', 'es')->firstOrFail();
});

it('filters and projects market prices consistently with the cart including zero and null fallback', function (): void {
    Product::query()->update(['is_active' => false]);
    $products = [];
    foreach (['free' => [100, 0], 'discounted' => [1, 10], 'fallback' => [25, null]] as $name => [$base, $custom]) {
        $product = Product::create(['sku' => 'market-price-'.$name, 'price' => $base, 'is_active' => true, 'track_stock' => false]);
        $product->translations()->create(['locale' => 'es', 'name' => $name, 'slug' => $name]);
        $product->markets()->attach($this->market, ['custom_price' => $custom, 'is_active' => true]);
        $products[$name] = $product;
    }
    $filters = app(ShopFilters::class);
    $range = new FilterSet('shop', 'es', 'es', ['price' => '0-15', 'sort' => 'precio-asc']);
    $result = $filters->listQuery($range, $this->market, 'es');
    expect($result['paginator']->getCollection()->modelKeys())->toBe([$products['free']->id, $products['discounted']->id]);
    $browse = $filters->browse($range, $this->market, 'es');
    expect(array_column($browse['items'], 'price'))->toBe([0.0, 10.0]);
    $fallback = $filters->listQuery(new FilterSet('shop', 'es', 'es', ['price' => '20-30']), $this->market, 'es');
    expect($fallback['paginator']->getCollection()->modelKeys())->toBe([$products['fallback']->id]);
    $descending = $filters->listQuery(new FilterSet('shop', 'es', 'es', ['sort' => 'precio-desc']), $this->market, 'es');
    expect($descending['paginator']->getCollection()->modelKeys())->toBe([$products['fallback']->id, $products['discounted']->id, $products['free']->id]);
    app(CartService::class)->add($products['free']->id);
    app(CartService::class)->add($products['discounted']->id);
    expect(array_column(app(CartService::class)->getItems($this->market), 'unit_price'))->toBe([0.0, 10.0]);
    $this->get('/es/es/tienda/free')->assertOk()->assertSee('"price":"0"', false);
});

it('invalidates public collection snapshots and indexes for market price and assignment changes', function (): void {
    config(['cms.page_cache.enabled' => true]);
    $product = Product::where('sku', 'PRD-EDGE-1000')->firstOrFail();
    $this->get('/es/es/tienda')->assertOk();
    $this->get('/es/es/tienda')->assertHeader(CachePublicPage::HIT_HEADER, 'HIT');
    $version = app(PageCacheService::class)->indexVersion();
    $product->markets()->updateExistingPivot($this->market->id, ['custom_price' => 0]);
    expect(app(PageCacheService::class)->indexVersion())->toBeGreaterThan($version);
    $this->get('/es/es/tienda')->assertHeader(CachePublicPage::HIT_HEADER, 'MISS')->assertSee('0,00 €');
    $product->markets()->detach($this->market->id);
    $this->get('/es/es/tienda')->assertHeader(CachePublicPage::HIT_HEADER, 'MISS');
});
