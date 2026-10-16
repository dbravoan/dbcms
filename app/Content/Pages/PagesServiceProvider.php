<?php

declare(strict_types=1);

namespace App\Content\Pages;

use App\Content\Pages\Contracts\PageDomainServiceInterface;
use App\Content\Pages\Contracts\PagePublicReaderInterface;
use App\Content\Pages\Http\Controllers\FrontPageController;
use App\Content\Pages\Models\Page;
use App\Content\Pages\Models\PageSection;
use App\Content\Pages\Models\PageTranslation;
use App\Content\Pages\Models\SectionTranslation;
use App\Content\Pages\Observers\PageSearchObserver;
use App\Content\Pages\Schema\PageEntitySchemas;
use App\Content\Pages\Search\PageSearchSource;
use App\Content\Pages\Services\PageDomainService;
use App\Content\Pages\Services\PagePublicReader;
use App\Contracts\Services\AdminThemeServiceInterface;
use App\Contracts\Services\LocalizationServiceInterface;
use App\Core\Content\Contracts\EntitySchemaRegistryInterface;
use App\Core\Routing\FrontRoutePatterns;
use App\Core\Search\Contracts\SearchSourceRegistryInterface;
use App\Http\Middleware\CachePublicPage;
use App\Http\Middleware\CanonicalModulePrefix;
use App\Http\Middleware\EnsureAdminAuthenticated;
use App\Http\Middleware\EnsureSeoMeta;
use App\Http\Middleware\ResolveMarketAndLocaleMiddleware;
use App\Http\Middleware\SetBackofficeLocale;
use App\Observers\ContentTranslationCacheObserver;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class PagesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PagePublicReaderInterface::class, PagePublicReader::class);
        $this->app->bind(PageDomainServiceInterface::class, PageDomainService::class);
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

        PageTranslation::observe(ContentTranslationCacheObserver::class);
        SectionTranslation::observe(ContentTranslationCacheObserver::class);
        $this->app->make(AdminThemeServiceInterface::class)->registerModuleThemePaths('pages', __DIR__.'/Resources/views');
        if (! $this->app->routesAreCached()) {
            Route::prefix((string) config('cms.admin_path', 'admin'))->name('admin.')
                ->middleware(['web', EnsureAdminAuthenticated::class, SetBackofficeLocale::class])
                ->group(__DIR__.'/Routes/admin.php');
        }
    }

    public static function registerPublicRoutes(): void
    {
        Route::middleware(['web', ResolveMarketAndLocaleMiddleware::class, CanonicalModulePrefix::class, EnsureSeoMeta::class, CachePublicPage::class])->group(function (): void {
            /** @var LocalizationServiceInterface $localization */
            $localization = app(LocalizationServiceInterface::class);
            $adminPath = (string) config('cms.admin_path', 'admin');
            $blocked = "admin|{$adminPath}|api|forms|geo|install|media|theme-assets|up";
            $reserved = implode('|', array_map(static fn (string $part): string => preg_quote($part, '#'), [$adminPath, 'admin', 'api', 'geo', 'install', 'media', 'theme-assets', 'up', 'llms', 'llms-full', 'sitemap', 'sitemaps']));

            // 1. Localized page routes with market and locale prefix ({market}/{locale})
            Route::prefix('{market}/{locale}')
                ->where(['market' => "^(?!{$blocked}|(?:{$reserved})(?=/|$))[a-zA-Z0-9_\-]+$", 'locale' => FrontRoutePatterns::locale()])
                ->group(function () use ($reserved): void {
                    Route::get('/', [FrontPageController::class, 'show'])->name('pages.home');
                    Route::get('', [FrontPageController::class, 'show']);

                    // Clean SEO Page URL: /{market}/{locale}/{slug}
                    Route::get('/{slug}', [FrontPageController::class, 'show'])
                        ->where(['slug' => "^(?!{$reserved}$)[a-zA-Z0-9_\-]+$"])
                        ->name('pages.show');
                });

            // 2. Localized page routes with locale only ({locale})
            Route::prefix('{locale}')
                ->where(['locale' => "^(?!{$blocked}|{$reserved})[a-zA-Z]{2,5}$"])
                ->group(function () use ($reserved): void {
                    Route::get('/', [FrontPageController::class, 'show']);
                    Route::get('', [FrontPageController::class, 'show']);

                    Route::get('/{slug}', [FrontPageController::class, 'show'])
                        ->where(['slug' => "^(?!{$reserved}$)[a-zA-Z0-9_\-]+$"]);
                });

            // 3. Fallback non-prefixed routes
            Route::get('/{slug}', [FrontPageController::class, 'show'])
                ->where(['slug' => "^(?!{$reserved}$)[a-zA-Z0-9_\-]+$"]);
        });
    }
}
