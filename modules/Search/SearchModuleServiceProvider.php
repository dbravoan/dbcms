<?php

declare(strict_types=1);

namespace Modules\Search;

use App\Contracts\Services\LocalizationServiceInterface;
use App\Contracts\Services\SearchAnalyticsInterface;
use App\Core\Blocks\Contracts\BlockRegistryInterface;
use App\Core\Capabilities\CapabilityRegistry;
use App\Core\Content\PublicSemanticRegistry;
use App\Core\Modules\BaseModuleServiceProvider;
use App\Core\Modules\ModuleManager;
use App\Core\Routing\FrontRoutePatterns;
use App\Core\Search\Contracts\SearchSourceInterface;
use App\Core\Search\SearchIndexer;
use App\Http\Middleware\CachePublicPage;
use App\Http\Middleware\CanonicalModulePrefix;
use App\Http\Middleware\EnsureSeoMeta;
use App\Http\Middleware\ResolveMarketAndLocaleMiddleware;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;
use Modules\Search\Blocks\SearchBoxBlock;
use Modules\Search\Capabilities\SearchActionCatalog;
use Modules\Search\Capabilities\SearchActions;
use Modules\Search\Capabilities\SearchNavigationCapability;
use Modules\Search\Contracts\SearchFrontPageServiceInterface;
use Modules\Search\Http\Controllers\SearchFrontController;
use Modules\Search\Services\SearchAnalyticsService;
use Modules\Search\Services\SearchCapabilityAnalyticsAdapter;
use Modules\Search\Services\SearchFrontPageService;
use Modules\Search\Services\SearchPublicUrls;
use Modules\Search\Services\SearchSettings;

/**
 * General front-office search.
 *
 * The module owns no content: it serves the hub (`/{prefix}`) and the results
 * (`/{prefix}/{term}`) over every entity the installed modules indexed through
 * their {@see SearchSourceInterface} declarations.
 */
class SearchModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $identifier = 'search';

    protected string $name = 'General Search';

    protected string $version = '1.0.0';

    protected bool $isPageable = true;

    public function register(): void
    {
        $this->app->bind(SearchAnalyticsInterface::class, SearchCapabilityAnalyticsAdapter::class);
        $this->app->bind(SearchFrontPageServiceInterface::class, SearchFrontPageService::class);
    }

    public function boot(): void
    {
        $this->app->make(BlockRegistryInterface::class)->register($this->app->make(SearchBoxBlock::class));
        $this->app->make(PublicSemanticRegistry::class)->registerUrls($this->app->make(SearchPublicUrls::class));
        $this->app->booted(function (): void {
            $this->app->make(SearchNavigationCapability::class)->register($this->app->make(CapabilityRegistry::class));
            SearchActionCatalog::register(
                $this->app->make(CapabilityRegistry::class),
                $this->app->make(SearchActions::class),
            );
        });
        $this->loadViewsFrom(__DIR__.'/Resources/views', 'search');
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        \Illuminate\Support\Facades\View::composer('themes::components.search-box', function (View $view): void {
            $view->with('searchEnabled', $this->app->make(ModuleManager::class)->isModuleEnabled('search'));
        });
        $this->composeFiltersInbox();

        parent::boot();
    }

    public function registerPageableRoutes(): void
    {
        Route::middleware(['web', ResolveMarketAndLocaleMiddleware::class, CanonicalModulePrefix::class, EnsureSeoMeta::class, CachePublicPage::class])->group(function (): void {
            /** @var LocalizationServiceInterface $localization */
            $localization = app(LocalizationServiceInterface::class);
            $pattern = implode('|', $localization->getAllModuleSlugs('search'));

            $adminPath = (string) config('cms.admin_path', 'admin');
            $blocked = "admin|{$adminPath}|api|forms|geo|install|media|theme-assets|up|blog|tienda|shop|galeria|gallery|page";

            $routes = function (bool $named) use ($pattern): void {
                $index = Route::get('/{searchPrefix}', [SearchFrontController::class, 'hub'])
                    ->where(['searchPrefix' => $pattern]);

                $results = Route::get('/{searchPrefix}/{term}', [SearchFrontController::class, 'results'])
                    ->where(['searchPrefix' => $pattern, 'term' => '[^/]+']);

                if ($named) {
                    $index->name('search.index');
                    $results->name('search.results');
                }

                // The entity type segment is validated in the controller: the
                // registered sources depend on module boot order.
                $typed = Route::get('/{searchPrefix}/{term}/{entityType}', [SearchFrontController::class, 'results'])
                    ->where(['searchPrefix' => $pattern, 'term' => '[^/]+', 'entityType' => '[a-z0-9\-]{1,40}']);

                if ($named) {
                    $typed->name('search.type');
                }
            };

            // 1. Localized routes with {market}/{locale} prefix.
            Route::prefix('{market}/{locale}')
                ->where(['market' => "^(?!{$blocked})[a-zA-Z0-9_\-]+$", 'locale' => FrontRoutePatterns::locale()])
                ->group(fn () => $routes(true));

            // 2. Localized routes with {locale} only (single-market setups).
            Route::prefix('{locale}')
                ->where(['locale' => "^(?!{$blocked}|{$pattern})[a-zA-Z]{2,5}$"])
                ->group(fn () => $routes(false));

            // 3. Fallback non-prefixed routes (1 market / 1 language).
            $routes(false);
        });
    }

    /**
     * Feeds the "Filtros y búsquedas" inbox with the activation state and the
     * real usage of the search, without the core knowing about this module.
     */
    private function composeFiltersInbox(): void
    {
        $this->app->make('view')->composer('admin.filters.index', function (View $view): void {
            try {
                $locale = (string) app()->getLocale();
                $settings = app(SearchSettings::class);
                $indexer = app(SearchIndexer::class);
                $analytics = app(SearchAnalyticsService::class);
                $coverage = $indexer->stats($locale);

                $view->with('searchPanel', [
                    'url' => app(LocalizationServiceInterface::class)->frontUrl('search', '', $locale),
                    'indexable' => $settings->isIndexable(),
                    'tracks' => $settings->tracksTerms(),
                    'perPage' => $settings->resultsPerPage(),
                    'minResults' => $settings->minResultsToIndex(),
                    'coverage' => $coverage,
                    'total' => array_sum(array_column($coverage, 'indexed')),
                    'top' => $analytics->topTerms($locale, 8),
                    'unsatisfied' => $analytics->unsatisfiedTerms($locale, 8),
                ]);
            } catch (\Throwable) {
                // The inbox must render even before the module is installed.
            }
        });
    }

    public function getAdminNavigation(): array
    {
        return [
            [
                'title' => __('admin.nav.items.search'),
                'route' => 'admin.seo.filters.index',
                'icon' => 'magnifying-glass',
            ],
        ];
    }
}
