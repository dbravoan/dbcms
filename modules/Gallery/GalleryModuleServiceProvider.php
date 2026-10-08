<?php

declare(strict_types=1);

namespace Modules\Gallery;

use App\Contracts\Services\LocalizationServiceInterface;
use App\Core\Blocks\Contracts\BlockRegistryInterface;
use App\Core\Content\Contracts\EntitySchemaRegistryInterface;
use App\Core\Content\PublicSemanticRegistry;
use App\Core\Modules\BaseModuleServiceProvider;
use App\Core\Routing\FrontRoutePatterns;
use App\Core\Search\Contracts\SearchSourceRegistryInterface;
use App\Http\Middleware\CachePublicPage;
use App\Http\Middleware\CanonicalModulePrefix;
use App\Http\Middleware\EnsureSeoMeta;
use App\Http\Middleware\ResolveMarketAndLocaleMiddleware;
use App\Services\Geo\PublicEntityWidgetContributor;
use Illuminate\Support\Facades\Route;
use Modules\Gallery\Blocks\GalleryAlbumBlock;
use Modules\Gallery\Contracts\GalleryEditorialServiceInterface;
use Modules\Gallery\Contracts\GalleryFrontPageServiceInterface;
use Modules\Gallery\Http\Controllers\GalleryFrontController;
use Modules\Gallery\Models\Gallery;
use Modules\Gallery\Models\GalleryItem;
use Modules\Gallery\Models\GalleryTranslation;
use Modules\Gallery\Observers\GallerySearchObserver;
use Modules\Gallery\Schema\GalleryEntitySchemas;
use Modules\Gallery\Search\GallerySearchSource;
use Modules\Gallery\Services\GalleryEditorialService;
use Modules\Gallery\Services\GalleryFrontPageService;
use Modules\Gallery\Widgets\GalleryGridWidget;

class GalleryModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $identifier = 'gallery';

    protected string $name = 'Media Gallery';

    protected string $version = '1.0.0';

    protected bool $isPageable = true;

    public function register(): void
    {
        $this->app->bind(GalleryFrontPageServiceInterface::class, GalleryFrontPageService::class);
        $this->app->bind(GalleryEditorialServiceInterface::class, GalleryEditorialService::class);
        $this->app->bind('widget.gallery.grid', fn () => GalleryGridWidget::class);
    }

    public function boot(): void
    {
        $this->app->make(SearchSourceRegistryInterface::class)->register($this->app->make(GallerySearchSource::class));
        Gallery::observe(GallerySearchObserver::class);
        GalleryTranslation::observe(GallerySearchObserver::class);
        GalleryItem::observe(GallerySearchObserver::class);
        $this->app->make(PublicSemanticRegistry::class)->registerWidget(
            new PublicEntityWidgetContributor('gallery', 'gallery', ['gallery.grid', 'widget_gallery', 'gallery'])
        );
        $this->loadViewsFrom(__DIR__.'/Resources/views', 'gallery');
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        if ($this->app->bound(BlockRegistryInterface::class)) {
            $this->app->make(BlockRegistryInterface::class)->register(new GalleryAlbumBlock);
        }

        if ($this->app->bound(EntitySchemaRegistryInterface::class)) {
            foreach (GalleryEntitySchemas::all() as $schema) {
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
            $galPattern = implode('|', $localization->getAllModuleSlugs('gallery'));
            $adminPath = (string) config('cms.admin_path', 'admin');
            $blocked = "admin|{$adminPath}|api|forms|geo|install|media|theme-assets|up";

            // 1. Localized routes with {market}/{locale}
            Route::prefix('{market}/{locale}')
                ->where(['market' => "^(?!{$blocked})[a-zA-Z0-9_\-]+$", 'locale' => FrontRoutePatterns::locale()])
                ->group(function () use ($galPattern): void {
                    Route::get('/{galleryPrefix}', [GalleryFrontController::class, 'index'])
                        ->where(['galleryPrefix' => $galPattern])
                        ->name('gallery.index');
                    Route::get('/{galleryPrefix}/{slug}', [GalleryFrontController::class, 'show'])
                        ->where(['galleryPrefix' => $galPattern, 'slug' => '.*'])
                        ->name('gallery.show');
                });

            // 2. Localized routes with {locale} only
            Route::prefix('{locale}')
                ->where(['locale' => "^(?!{$blocked}|{$galPattern}|page)[a-zA-Z]{2,5}$"])
                ->group(function () use ($galPattern): void {
                    Route::get('/{galleryPrefix}', [GalleryFrontController::class, 'index'])
                        ->where(['galleryPrefix' => $galPattern]);
                    Route::get('/{galleryPrefix}/{slug}', [GalleryFrontController::class, 'show'])
                        ->where(['galleryPrefix' => $galPattern, 'slug' => '.*']);
                });

            // 3. Fallback un-prefixed routes
            Route::get('/{galleryPrefix}', [GalleryFrontController::class, 'index'])
                ->where(['galleryPrefix' => $galPattern]);
            Route::get('/{galleryPrefix}/{slug}', [GalleryFrontController::class, 'show'])
                ->where(['galleryPrefix' => $galPattern, 'slug' => '.*']);
        });
    }

    public function getWidgets(): array
    {
        return [
            GalleryGridWidget::getKey() => GalleryGridWidget::class,
        ];
    }

    public function getAdminNavigation(): array
    {
        return [
            [
                'title' => 'Galleries',
                'route' => 'admin.galleries.index',
                'icon' => 'photograph',
                'permission' => 'manage:galleries',
            ],
        ];
    }
}
