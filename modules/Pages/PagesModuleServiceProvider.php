<?php

declare(strict_types=1);

namespace Modules\Pages;

use App\Contracts\Services\LocalizationServiceInterface;
use App\Core\Content\Contracts\EntitySchemaRegistryInterface;
use App\Core\Modules\BaseModuleServiceProvider;
use App\Core\Routing\FrontRoutePatterns;
use App\Core\Search\Contracts\SearchSourceRegistryInterface;
use App\Http\Middleware\CachePublicPage;
use App\Http\Middleware\CanonicalModulePrefix;
use App\Http\Middleware\EnsureSeoMeta;
use App\Http\Middleware\ResolveMarketAndLocaleMiddleware;
use Illuminate\Support\Facades\Route;
use Modules\Pages\Contracts\PageDomainServiceInterface;
use Modules\Pages\Contracts\PagePublicReaderInterface;
use Modules\Pages\Http\Controllers\FrontPageController;
use Modules\Pages\Models\Page;
use Modules\Pages\Models\PageSection;
use Modules\Pages\Models\PageTranslation;
use Modules\Pages\Models\SectionTranslation;
use Modules\Pages\Observers\PageSearchObserver;
use Modules\Pages\Schema\PageEntitySchemas;
use Modules\Pages\Search\PageSearchSource;
use Modules\Pages\Services\PageDomainService;
use Modules\Pages\Services\PagePublicReader;

class PagesModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $identifier = 'pages';

    protected string $name = 'Composite Page Builder';

    protected string $version = '1.0.0';

    protected bool $isPageable = true;

    public function register(): void
    {
        $this->app->bind(PagePublicReaderInterface::class, PagePublicReader::class);
        $this->app->bind(PageDomainServiceInterface::class, PageDomainService::class);
        //
    }

    public function boot(): void
    {
        $this->app->make(SearchSourceRegistryInterface::class)->register($this->app->make(PageSearchSource::class));
        Page::observe(PageSearchObserver::class);
        PageTranslation::observe(PageSearchObserver::class);
        PageSection::observe(PageSearchObserver::class);
        SectionTranslation::observe(PageSearchObserver::class);
        $this->loadViewsFrom(__DIR__.'/Resources/views', 'pages');
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        if ($this->app->bound(EntitySchemaRegistryInterface::class)) {
            foreach (PageEntitySchemas::all() as $schema) {
                $this->app->make(EntitySchemaRegistryInterface::class)->register($schema);
            }
        }

        parent::boot();
    }

    public function registerPageableRoutes(): void
    {
        Route::middleware(['web', ResolveMarketAndLocaleMiddleware::class, CanonicalModulePrefix::class, EnsureSeoMeta::class, CachePublicPage::class])->group(function (): void {
            /** @var LocalizationServiceInterface $localization */
            $localization = app(LocalizationServiceInterface::class);
            $blogSlugs = $localization->getAllModuleSlugs('blog');
            $shopSlugs = $localization->getAllModuleSlugs('shop');
            $adminPath = (string) config('cms.admin_path', 'admin');
            $blocked = "admin|{$adminPath}|api|forms|geo|install|media|theme-assets|up";
            $allModuleSlugs = array_merge($blogSlugs, $shopSlugs, ['cart', 'checkout', 'galeria', 'gallery', 'llms', 'llms-full', 'sitemap', 'sitemaps']);
            $reserved = implode('|', array_unique(array_filter(array_merge([$adminPath, 'admin', 'api', 'forms', 'geo', 'install', 'media', 'up'], $allModuleSlugs))));

            // 1. Localized page routes with market and locale prefix ({market}/{locale})
            Route::prefix('{market}/{locale}')
                ->where(['market' => "^(?!{$blocked}|(?:{$reserved})(?=/|$))[a-zA-Z0-9_\-]+$", 'locale' => FrontRoutePatterns::locale()])
                ->group(function () use ($reserved): void {
                    Route::get('/', [FrontPageController::class, 'show'])->withoutMiddleware('module.enabled:pages')->name('pages.home');
                    Route::get('', [FrontPageController::class, 'show'])->withoutMiddleware('module.enabled:pages');

                    // Clean SEO Page URL: /{market}/{locale}/{slug}
                    Route::get('/{slug}', [FrontPageController::class, 'show'])
                        ->where(['slug' => "^(?!{$reserved}$)[a-zA-Z0-9_\-]+$"])
                        ->name('pages.show');
                });

            // 2. Localized page routes with locale only ({locale})
            Route::prefix('{locale}')
                ->where(['locale' => "^(?!{$blocked}|{$reserved})[a-zA-Z]{2,5}$"])
                ->group(function () use ($reserved): void {
                    Route::get('/', [FrontPageController::class, 'show'])->withoutMiddleware('module.enabled:pages');
                    Route::get('', [FrontPageController::class, 'show'])->withoutMiddleware('module.enabled:pages');

                    Route::get('/{slug}', [FrontPageController::class, 'show'])
                        ->where(['slug' => "^(?!{$reserved}$)[a-zA-Z0-9_\-]+$"]);
                });

            // 3. Fallback non-prefixed routes
            Route::get('/{slug}', [FrontPageController::class, 'show'])
                ->where(['slug' => "^(?!{$reserved}$)[a-zA-Z0-9_\-]+$"]);
        });
    }

    public function getAdminNavigation(): array
    {
        return [
            [
                'title' => 'Pages & Builder',
                'route' => 'admin.pages.index',
                'icon' => 'document-duplicate',
                'permission' => 'manage:pages',
            ],
        ];
    }
}
