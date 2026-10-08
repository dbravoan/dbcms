<?php

declare(strict_types=1);

use App\Contracts\Services\LocalizationServiceInterface;
use App\Core\Filters\FilterSet;
use App\Http\Adapters\FrontPageResponseMapper;
use App\Http\Adapters\PublicContentContextFactory;
use App\Models\Market;
use App\Services\Cms\FrontViewResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Pages\Services\SectionRendererService;
use Modules\Shop\Filters\ShopFilters;
use Modules\Shop\Http\Controllers\ShopFrontController;
use Modules\Shop\Models\ProductCategory;
use Modules\Shop\Services\ShopCatalogueService;
use Modules\Shop\Services\ShopDomainService;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(TestCase::class);

beforeEach(function (): void {
    config(['database.default' => 'shop_category_parity', 'database.connections.shop_category_parity' => ['driver' => 'sqlite', 'database' => ':memory:']]);
    Schema::create('markets', static fn (Blueprint $table) => $table->id());
    Schema::create('product_categories', function (Blueprint $table): void {
        $table->id();
        $table->boolean('is_active');
        $table->integer('order')->default(0);
    });
    Schema::create('product_category_translations', function (Blueprint $table): void {
        $table->id();
        $table->integer('category_id');
        $table->string('locale');
        $table->string('name');
        $table->string('slug');
    });
    Schema::create('market_product_category', function (Blueprint $table): void {
        $table->integer('category_id');
        $table->integer('market_id');
    });
    Schema::create('products', function (Blueprint $table): void {
        $table->id();
        $table->boolean('is_active');
        $table->integer('category_id');
        $table->timestamps();
    });
    Schema::create('market_product', function (Blueprint $table): void {
        $table->integer('product_id');
        $table->integer('market_id');
        $table->boolean('is_active');
    });
    Schema::create('product_translations', function (Blueprint $table): void {
        $table->id();
        $table->integer('product_id');
        $table->string('locale');
    });
    DB::table('markets')->insert([['id' => 1], ['id' => 2]]);
    DB::table('product_categories')->insert([['id' => 1, 'is_active' => true], ['id' => 2, 'is_active' => true]]);
    DB::table('product_category_translations')->insert([['category_id' => 1, 'locale' => 'en', 'name' => 'ES only QA', 'slug' => 'es-only-qa'], ['category_id' => 2, 'locale' => 'en', 'name' => 'Global QA', 'slug' => 'global-qa']]);
    DB::table('market_product_category')->insert(['category_id' => 1, 'market_id' => 1]);
    $this->market = Market::findOrFail(2);
    app()->instance('current.market', $this->market);
    app()->setLocale('en');
});

it('keeps global categories while excluding categories assigned to another market', function (): void {
    expect(ProductCategory::forMarket($this->market)->pluck('id')->all())->toBe([2]);
});

it('does not resolve foreign categories in catalogue filters', function (): void {
    $set = new FilterSet('shop', 'uk', 'en', ['category' => ['es-only-qa']]);
    expect((new ShopFilters)->listQuery($set, $this->market, 'en'))->toBeNull();
});

it('rejects an explicit foreign category passed to the storefront controller', function (): void {
    $shop = Mockery::mock(ShopDomainService::class);
    $shop->shouldReceive('getPublishedProducts')->andReturn(new LengthAwarePaginator([], 0, 12));
    $shop->shouldReceive('isShopEnabled', 'shouldShowPrices')->andReturn(true);
    $views = app(FrontViewResolver::class);
    $localization = Mockery::mock(LocalizationServiceInterface::class);
    $catalogue = new ShopCatalogueService($shop, $views, $localization, app(SectionRendererService::class));
    $controller = new ShopFrontController($catalogue, app(PublicContentContextFactory::class), app(FrontPageResponseMapper::class));

    expect(fn () => $controller->category(Request::create('/uk/en/shop/es-only-qa'), $localization, ProductCategory::findOrFail(1)))->toThrow(HttpException::class);
});
