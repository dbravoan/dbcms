<?php

declare(strict_types=1);

namespace Modules\Shop;

use App\Contracts\Services\LocalizationServiceInterface;
use App\Core\Blocks\Contracts\BlockRegistryInterface;
use App\Core\Capabilities\Resources\CapabilityResourceRegistry;
use App\Core\Content\Contracts\EntitySchemaRegistryInterface;
use App\Core\Content\PublicSemanticRegistry;
use App\Core\Filters\FilterModuleRegistry;
use App\Core\Modules\BaseModuleServiceProvider;
use App\Core\Routing\FrontRoutePatterns;
use App\Core\Search\Contracts\SearchSourceRegistryInterface;
use App\Core\Search\SearchIndexer;
use App\Http\Middleware\CachePublicPage;
use App\Http\Middleware\CanonicalModulePrefix;
use App\Http\Middleware\EnsureSeoMeta;
use App\Http\Middleware\ResolveMarketAndLocaleMiddleware;
use App\Services\Geo\PublicEntityWidgetContributor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Modules\Shop\Blocks\ShopProductsBlock;
use Modules\Shop\Capabilities\ShopResources;
use Modules\Shop\Contracts\CartApplicationInterface;
use Modules\Shop\Contracts\InquiryServiceInterface;
use Modules\Shop\Contracts\ShopAdministrationServiceInterface;
use Modules\Shop\Contracts\ShopCatalogueServiceInterface;
use Modules\Shop\Contracts\ShopEditorialServiceInterface;
use Modules\Shop\Contracts\ShopPurchaseWorkflowInterface;
use Modules\Shop\Filters\ShopFilters;
use Modules\Shop\Http\Controllers\CartApiController;
use Modules\Shop\Http\Controllers\Front\CartGetController;
use Modules\Shop\Http\Controllers\Front\CheckoutGetController;
use Modules\Shop\Http\Controllers\Front\CheckoutPostController;
use Modules\Shop\Http\Controllers\Front\InquiryPostController;
use Modules\Shop\Http\Controllers\Front\OrderSuccessGetController;
use Modules\Shop\Http\Controllers\ShopFrontController;
use Modules\Shop\Models\Product;
use Modules\Shop\Models\ProductMarket;
use Modules\Shop\Models\ProductTranslation;
use Modules\Shop\Observers\ProductMarketCacheObserver;
use Modules\Shop\Schema\ShopEntitySchemas;
use Modules\Shop\Search\ProductSearchSource;
use Modules\Shop\Services\CartApplicationService;
use Modules\Shop\Services\CartService;
use Modules\Shop\Services\InquiryService;
use Modules\Shop\Services\ShopAdministrationService;
use Modules\Shop\Services\ShopCatalogueService;
use Modules\Shop\Services\ShopDomainService;
use Modules\Shop\Services\ShopEditorialService;
use Modules\Shop\Services\ShopPurchaseWorkflow;
use Modules\Shop\Widgets\FeaturedProductsWidget;

class ShopModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $identifier = 'shop';

    protected string $name = 'Product Catalog & E-Commerce';

    protected string $version = '1.0.0';

    protected bool $isPageable = true;

    public function register(): void
    {
        $this->app->bind(CartApplicationInterface::class, CartApplicationService::class);
        $this->app->singleton(ShopDomainService::class);
        $this->app->bind(ShopPurchaseWorkflowInterface::class, ShopPurchaseWorkflow::class);
        $this->app->bind(ShopCatalogueServiceInterface::class, ShopCatalogueService::class);
        $this->app->bind(ShopEditorialServiceInterface::class, ShopEditorialService::class);
        $this->app->singleton(CartService::class);
        $this->app->bind(InquiryServiceInterface::class, InquiryService::class);
        $this->app->bind(ShopAdministrationServiceInterface::class, ShopAdministrationService::class);
        $this->app->bind('widget.shop.featured_products', fn () => FeaturedProductsWidget::class);
    }

    public function boot(): void
    {
        $this->app->make(PublicSemanticRegistry::class)->registerWidget(
            new PublicEntityWidgetContributor('shop', 'product', ['shop.featured_products', 'widget_products', 'products'])
        );
        $this->app->make(CapabilityResourceRegistry::class)->register(ShopResources::descriptor());
        ProductMarket::observe(ProductMarketCacheObserver::class);
        $this->loadViewsFrom(__DIR__.'/Resources/views', 'shop');
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        if ($this->app->bound(BlockRegistryInterface::class)) {
            $this->app->make(BlockRegistryInterface::class)->register(new ShopProductsBlock);
        }

        if ($this->app->bound(EntitySchemaRegistryInterface::class)) {
            foreach (ShopEntitySchemas::all() as $schema) {
                $this->app->make(EntitySchemaRegistryInterface::class)->register($schema);
            }
        }

        // Friendly filter URLs: declare the shop filter dimensions.
        if ($this->app->bound(FilterModuleRegistry::class)) {
            $this->app->make(FilterModuleRegistry::class)->register(new ShopFilters);
        }

        // Cross-entity search: declare the shop search source.
        if ($this->app->bound(SearchSourceRegistryInterface::class)) {
            $this->app->make(SearchSourceRegistryInterface::class)->register(
                $this->app->make(ProductSearchSource::class)
            );
        }

        // Keep the search index in sync with catalogue edits.
        if ($this->app->bound(SearchIndexer::class)) {
            $syncProduct = static function (Model $model): void {
                $productId = $model instanceof ProductTranslation ? $model->getAttribute('product_id') : $model->getKey();

                if ($productId !== null) {
                    app(SearchIndexer::class)->indexEntity('product', (int) $productId);
                }
            };

            Product::saved($syncProduct);
            Product::deleted($syncProduct);
            ProductTranslation::saved($syncProduct);
            ProductTranslation::deleted($syncProduct);
        }

        parent::boot();
    }

    public function registerPageableRoutes(): void
    {
        Route::middleware(['web', 'module.enabled:shop', ResolveMarketAndLocaleMiddleware::class, CanonicalModulePrefix::class, EnsureSeoMeta::class, CachePublicPage::class])->group(function (): void {
            /** @var LocalizationServiceInterface $localization */
            $localization = app(LocalizationServiceInterface::class);
            $shopPattern = implode('|', $localization->getAllModuleSlugs('shop'));
            $adminPath = (string) config('cms.admin_path', 'admin');
            $blocked = "admin|{$adminPath}|api|forms|geo|install|media|theme-assets|up";

            // 1. Localized routes with {market}/{locale}
            Route::prefix('{market}/{locale}')
                ->where(['market' => "^(?!{$blocked})[a-zA-Z0-9_\-]+$", 'locale' => FrontRoutePatterns::locale()])
                ->group(function () use ($shopPattern): void {
                    Route::get('/{shopPrefix}', [ShopFrontController::class, 'index'])->where(['shopPrefix' => $shopPattern])->name('shop.index');

                    Route::get('/cart', CartGetController::class)->defaults('_cms_cache_public', false)->name('shop.cart');
                    Route::get('/{shopPrefix}/cart', CartGetController::class)->defaults('_cms_cache_public', false)->where(['shopPrefix' => $shopPattern]);

                    Route::get('/checkout', CheckoutGetController::class)->defaults('_cms_cache_public', false)->name('shop.checkout');
                    Route::get('/{shopPrefix}/checkout', CheckoutGetController::class)->defaults('_cms_cache_public', false)->where(['shopPrefix' => $shopPattern]);

                    Route::post('/checkout/process', CheckoutPostController::class)->name('shop.checkout.process');
                    Route::post('/{shopPrefix}/checkout/process', CheckoutPostController::class)->where(['shopPrefix' => $shopPattern]);

                    Route::get('/order/{orderNumber}', OrderSuccessGetController::class)->defaults('_cms_cache_public', false)->name('shop.order.success');
                    Route::get('/{shopPrefix}/order/{orderNumber}', OrderSuccessGetController::class)->defaults('_cms_cache_public', false)->where(['shopPrefix' => $shopPattern]);

                    Route::post('/inquiry', InquiryPostController::class)->name('shop.inquiry');
                    Route::post('/{shopPrefix}/inquiry', InquiryPostController::class)->where(['shopPrefix' => $shopPattern]);

                    Route::get('/{shopPrefix}/{slug}', [ShopFrontController::class, 'resolveSlug'])->where(['shopPrefix' => $shopPattern, 'slug' => '^(?!cart|checkout|order|inquiry).*$'])->name('shop.show');
                });

            // 2. Localized routes with {locale} only (for single-market setups)
            Route::prefix('{locale}')
                ->where(['locale' => "^(?!{$blocked}|{$shopPattern}|blog|galeria|gallery|page)[a-zA-Z]{2,5}$"])
                ->group(function () use ($shopPattern): void {
                    Route::get('/{shopPrefix}', [ShopFrontController::class, 'index'])->where(['shopPrefix' => $shopPattern]);
                    Route::get('/{shopPrefix}/cart', CartGetController::class)->defaults('_cms_cache_public', false)->where(['shopPrefix' => $shopPattern]);
                    Route::get('/{shopPrefix}/checkout', CheckoutGetController::class)->defaults('_cms_cache_public', false)->where(['shopPrefix' => $shopPattern]);
                    Route::post('/{shopPrefix}/checkout/process', CheckoutPostController::class)->where(['shopPrefix' => $shopPattern]);
                    Route::get('/{shopPrefix}/order/{orderNumber}', OrderSuccessGetController::class)->defaults('_cms_cache_public', false)->where(['shopPrefix' => $shopPattern]);
                    Route::post('/{shopPrefix}/inquiry', InquiryPostController::class)->where(['shopPrefix' => $shopPattern]);
                    Route::get('/{shopPrefix}/{slug}', [ShopFrontController::class, 'resolveSlug'])->where(['shopPrefix' => $shopPattern, 'slug' => '^(?!cart|checkout|order|inquiry).*$']);
                });

            // 3. Unprefixed Fallbacks (for single market / single language)
            Route::get('/{shopPrefix}', [ShopFrontController::class, 'index'])->where(['shopPrefix' => $shopPattern]);
            Route::get('/{shopPrefix}/cart', CartGetController::class)->defaults('_cms_cache_public', false)->where(['shopPrefix' => $shopPattern]);
            Route::get('/{shopPrefix}/checkout', CheckoutGetController::class)->defaults('_cms_cache_public', false)->where(['shopPrefix' => $shopPattern]);
            Route::post('/{shopPrefix}/checkout/process', CheckoutPostController::class)->where(['shopPrefix' => $shopPattern]);
            Route::get('/{shopPrefix}/order/{orderNumber}', OrderSuccessGetController::class)->defaults('_cms_cache_public', false)->where(['shopPrefix' => $shopPattern]);
            Route::post('/{shopPrefix}/inquiry', InquiryPostController::class)->where(['shopPrefix' => $shopPattern]);
            Route::get('/{shopPrefix}/{slug}', [ShopFrontController::class, 'resolveSlug'])->where(['shopPrefix' => $shopPattern, 'slug' => '^(?!cart|checkout|order|inquiry).*$']);
        });

        // AJAX Cart API Routes
        Route::middleware(['web', 'module.enabled:shop', ResolveMarketAndLocaleMiddleware::class])->prefix('api/cart')->name('api.cart.')->group(function (): void {
            Route::get('/', [CartApiController::class, 'getCart'])->name('get');
            Route::post('/add', [CartApiController::class, 'add'])->name('add');
            Route::post('/update', [CartApiController::class, 'update'])->name('update');
            Route::post('/remove', [CartApiController::class, 'remove'])->name('remove');
            Route::post('/clear', [CartApiController::class, 'clear'])->name('clear');
        });
    }

    public function getWidgets(): array
    {
        return [
            FeaturedProductsWidget::getKey() => FeaturedProductsWidget::class,
        ];
    }

    public function getAdminNavigation(): array
    {
        return [
            [
                'title' => 'Catálogo & Tienda',
                'route' => 'admin.shop.products.index',
                'icon' => 'shopping-bag',
                'permission' => 'manage:shop',
            ],
            ['title' => 'Solicitudes de presupuesto', 'route' => 'admin.shop.inquiries.index', 'icon' => 'envelope', 'permission' => 'manage:shop'],
            ['title' => 'Conciliación de pagos históricos', 'route' => 'admin.shop.orders.legacy-payments', 'icon' => 'credit-card', 'permission' => 'manage:shop'],
        ];
    }
}
