<?php

declare(strict_types=1);

namespace Modules\Blog;

use App\Contracts\Services\LocalizationServiceInterface;
use App\Core\Blocks\Contracts\BlockRegistryInterface;
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
use App\Models\User;
use App\Observers\ContentTranslationCacheObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Modules\Blog\Blocks\BlogPostsBlock;
use Modules\Blog\Contracts\BlogArticleReaderInterface;
use Modules\Blog\Contracts\BlogCatalogueReaderInterface;
use Modules\Blog\Contracts\BlogPublicReaderInterface;
use Modules\Blog\Contracts\CategoryDomainServiceInterface;
use Modules\Blog\Contracts\CommentPublicationServiceInterface;
use Modules\Blog\Contracts\PostDomainServiceInterface;
use Modules\Blog\Filters\BlogFilters;
use Modules\Blog\Http\Controllers\BlogFrontController;
use Modules\Blog\Http\Controllers\RssController;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\CategoryTranslation;
use Modules\Blog\Models\Post;
use Modules\Blog\Models\PostSectionTranslation;
use Modules\Blog\Models\PostTranslation;
use Modules\Blog\Schema\BlogEntitySchemas;
use Modules\Blog\Search\PostSearchSource;
use Modules\Blog\Services\BlogArticleReader;
use Modules\Blog\Services\BlogCatalogueReader;
use Modules\Blog\Services\BlogDashboard;
use Modules\Blog\Services\BlogPublicReader;
use Modules\Blog\Services\BlogPublicSemanticContributor;
use Modules\Blog\Services\CategoryDomainService;
use Modules\Blog\Services\CommentPublicationService;
use Modules\Blog\Services\PostDomainService;

class BlogModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $identifier = 'blog';

    protected string $name = 'Blog Engine';

    protected string $version = '1.0.0';

    protected bool $isPageable = true;

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/Config/entity_search.php', 'entity_search.sources');
        config(['cms.page_cache_models' => array_values(array_unique(array_merge((array) config('cms.page_cache_models', []), [Post::class, Category::class])))]);
        $this->app->bind(BlogCatalogueReaderInterface::class, BlogCatalogueReader::class);
        $this->app->bind(BlogArticleReaderInterface::class, BlogArticleReader::class);
        $this->app->bind(BlogPublicReaderInterface::class, BlogPublicReader::class);
        $this->app->bind(CommentPublicationServiceInterface::class, CommentPublicationService::class);
        $this->app->bind(PostDomainServiceInterface::class, PostDomainService::class);
        $this->app->bind(CategoryDomainServiceInterface::class, CategoryDomainService::class);
    }

    public function boot(): void
    {
        $this->app->make(PublicSemanticRegistry::class)->register($this->app->make(BlogPublicSemanticContributor::class));
        PostTranslation::observe(ContentTranslationCacheObserver::class);
        PostSectionTranslation::observe(ContentTranslationCacheObserver::class);
        CategoryTranslation::observe(ContentTranslationCacheObserver::class);
        $this->loadViewsFrom(__DIR__.'/Resources/views', 'blog');
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        if ($this->app->bound(BlockRegistryInterface::class)) {
            $block = $this->app->make(BlogPostsBlock::class);
            $this->app->make(BlockRegistryInterface::class)->register($block);
            $this->app->make(PublicSemanticRegistry::class)->registerWidget($block);
        }

        if ($this->app->bound(EntitySchemaRegistryInterface::class)) {
            foreach (BlogEntitySchemas::all() as $schema) {
                $this->app->make(EntitySchemaRegistryInterface::class)->register($schema);
            }
        }

        // Cross-entity search: declare the blog search source.
        if ($this->app->bound(SearchSourceRegistryInterface::class)) {
            $this->app->make(SearchSourceRegistryInterface::class)->register(
                $this->app->make(PostSearchSource::class)
            );
        }

        // Keep the search index in sync with content edits: saving or removing a
        // post (or any of its translations) reindexes that post only.
        if ($this->app->bound(SearchIndexer::class)) {
            $syncPost = static function (Model $model): void {
                $postId = $model instanceof PostTranslation ? $model->getAttribute('post_id') : $model->getKey();

                if ($postId !== null) {
                    app(SearchIndexer::class)->indexEntity('post', (int) $postId);
                }
            };

            Post::saved($syncPost);
            Post::deleted($syncPost);
            PostTranslation::saved($syncPost);
            PostTranslation::deleted($syncPost);
        }

        // Friendly filter URLs: declare the blog filter dimensions.
        if ($this->app->bound(FilterModuleRegistry::class)) {
            $this->app->make(FilterModuleRegistry::class)->register(new BlogFilters);
        }

        parent::boot();
    }

    public function registerPageableRoutes(): void
    {
        Route::get('/feed.xml', RssController::class)->middleware(['web', 'module.enabled:blog'])->name('seo.feed');
        Route::middleware(['web', ResolveMarketAndLocaleMiddleware::class, CanonicalModulePrefix::class, EnsureSeoMeta::class, CachePublicPage::class])->group(function (): void {
            /** @var LocalizationServiceInterface $localization */
            $localization = app(LocalizationServiceInterface::class);
            $blogPattern = implode('|', $localization->getAllModuleSlugs('blog'));
            $adminPath = (string) config('cms.admin_path', 'admin');
            $blocked = "admin|{$adminPath}|api|forms|geo|install|media|theme-assets|up";

            // 1. Localized routes with {market}/{locale} prefix
            Route::prefix('{market}/{locale}')
                ->where(['market' => "^(?!{$blocked})[a-zA-Z0-9_\-]+$", 'locale' => FrontRoutePatterns::locale()])
                ->group(function () use ($blogPattern): void {
                    Route::get('/{blogPrefix}', [BlogFrontController::class, 'index'])
                        ->where(['blogPrefix' => $blogPattern])
                        ->name('blog.index');
                    Route::get('/{blogPrefix}/{slug}', [BlogFrontController::class, 'resolveSlug'])
                        ->where(['blogPrefix' => $blogPattern, 'slug' => '.*'])
                        ->name('blog.show');
                    Route::post('/{blogPrefix}/{slug}/comments', [BlogFrontController::class, 'storeComment'])
                        ->where(['blogPrefix' => $blogPattern])
                        ->name('blog.comments.store');
                });

            // 2. Localized routes with {locale} prefix only (for single-market setups)
            Route::prefix('{locale}')
                ->where(['locale' => "^(?!{$blocked}|{$blogPattern}|galeria|gallery|page)[a-zA-Z]{2,5}$"])
                ->group(function () use ($blogPattern): void {
                    Route::get('/{blogPrefix}', [BlogFrontController::class, 'index'])->where(['blogPrefix' => $blogPattern]);
                    Route::get('/{blogPrefix}/{slug}', [BlogFrontController::class, 'resolveSlug'])->where(['blogPrefix' => $blogPattern, 'slug' => '.*']);
                    Route::post('/{blogPrefix}/{slug}/comments', [BlogFrontController::class, 'storeComment'])->where(['blogPrefix' => $blogPattern]);
                });

            // 3. Default non-prefixed fallback routes (for 1-market / 1-lang setups)
            Route::get('/{blogPrefix}', [BlogFrontController::class, 'index'])->where(['blogPrefix' => $blogPattern]);
            Route::get('/{blogPrefix}/{slug}', [BlogFrontController::class, 'resolveSlug'])->where(['blogPrefix' => $blogPattern, 'slug' => '.*']);
            Route::post('/{blogPrefix}/{slug}/comments', [BlogFrontController::class, 'storeComment'])->where(['blogPrefix' => $blogPattern]);
        });
    }

    public function getAdminDashboard(User $user): ?array
    {
        return $this->app->make(BlogDashboard::class)->data($user);
    }

    public function getAdminNavigation(): array
    {
        return [[
            'id' => 'blog',
            'title' => __('admin.nav.items.blog'),
            'icon' => 'pen-to-square',
            'children' => [
                [
                    'title' => __('admin.nav.items.blog_posts'),
                    'route' => 'admin.blog.posts.index',
                    'createRoute' => 'admin.blog.posts.create',
                    'createTooltip' => __('admin.nav.create.post'),
                    'icon' => 'pen-to-square',
                    'permission' => 'manage:posts',
                ],
                [
                    'title' => __('admin.nav.items.categories'),
                    'route' => 'admin.blog.categories.index',
                    'icon' => 'tag',
                    'permission' => 'manage:posts',
                ],
            ],
        ]];
    }
}
