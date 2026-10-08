<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Services\AdminDashboardServiceInterface;
use App\Contracts\Services\AdminNavigationServiceInterface;
use App\Contracts\Services\AdminThemeServiceInterface;
use App\Contracts\Services\BrandServiceInterface;
use App\Contracts\Services\DatatableQueryServiceInterface;
use App\Contracts\Services\EntitySearchServiceInterface;
use App\Contracts\Services\GeneralSettingsServiceInterface;
use App\Contracts\Services\InstallationAdminServiceInterface;
use App\Contracts\Services\InstallationDatabaseServiceInterface;
use App\Contracts\Services\InstallationEnvironmentServiceInterface;
use App\Contracts\Services\InstallationProvisioningServiceInterface;
use App\Contracts\Services\InstallationViewServiceInterface;
use App\Contracts\Services\LocalizationServiceInterface;
use App\Contracts\Services\MenuEditorServiceInterface;
use App\Contracts\Services\ModuleNotificationServiceInterface;
use App\Contracts\Services\ModulePackageInstallerInterface;
use App\Contracts\Services\ModulePackageUploadServiceInterface;
use App\Contracts\Services\ModuleSettingsAdministrationInterface;
use App\Contracts\Services\ModuleSettingsServiceInterface;
use App\Contracts\Services\NotificationRecipientResolverInterface;
use App\Contracts\Services\PublishedSectionTextInterface;
use App\Contracts\Services\SearchAnalyticsInterface;
use App\Core\Blocks\BlockRegistry;
use App\Core\Blocks\Contracts\BlockRegistryInterface;
use App\Core\Blocks\Types\BeforeAfterBlock;
use App\Core\Blocks\Types\CarouselBlock;
use App\Core\Blocks\Types\CodeEmbedBlock;
use App\Core\Blocks\Types\CtaBlock;
use App\Core\Blocks\Types\FaqBlock;
use App\Core\Blocks\Types\FeaturesBlock;
use App\Core\Blocks\Types\HeroBlock;
use App\Core\Blocks\Types\KpiBlock;
use App\Core\Blocks\Types\PricingBlock;
use App\Core\Blocks\Types\RichTextBlock;
use App\Core\Blocks\Types\RoiBlock;
use App\Core\Blocks\Types\SearchBoxBlock;
use App\Core\Blocks\Types\TestimonialsBlock;
use App\Core\Blocks\Types\VideoBlock;
use App\Core\Content\Contracts\EditorialWorkflowInterface;
use App\Core\Content\Contracts\EntitySchemaRegistryInterface;
use App\Core\Content\EntitySchemaRegistry;
use App\Core\Content\PublicSemanticRegistry;
use App\Core\Modules\Contracts\MaintenanceModeInterface;
use App\Core\Modules\ModuleManager;
use App\Core\Modules\ModuleSettingsExtensionRegistry;
use App\Core\Modules\NullMaintenanceModeService;
use App\Core\Notifications\NotificationRecipientPolicyRegistry;
use App\Http\ViewComposers\AdminLayoutComposer;
use App\Http\ViewComposers\FrontendLayoutComposer;
use App\Models\User;
use App\Observers\ContentTranslationCacheObserver;
use App\Observers\PageCacheObserver;
use App\Observers\UserSecurityObserver;
use App\Services\Admin\AdminDashboardService;
use App\Services\Admin\AdminNavigationService;
use App\Services\Admin\DatatableQueryService;
use App\Services\Admin\MenuEditorService;
use App\Services\Cms\AdminThemeService;
use App\Services\Cms\BrandService;
use App\Services\Cms\CacheDependencyCollector;
use App\Services\Cms\GeneralSettingsService;
use App\Services\Cms\LocalizationService;
use App\Services\Cms\ModuleNotificationService;
use App\Services\Cms\ModulePackageInstallerService;
use App\Services\Cms\ModuleSettingsAdministrationService;
use App\Services\Cms\ModuleSettingsService;
use App\Services\Cms\NotificationRecipientResolverService;
use App\Services\Cms\PublicationBoundaryService;
use App\Services\Cms\ThemeManagerService;
use App\Services\Content\NoEditorialWorkflow;
use App\Services\Content\PublishedSectionTextService;
use App\Services\Install\InstallationAdminService;
use App\Services\Install\InstallationDatabaseService;
use App\Services\Install\InstallationEnvironmentService;
use App\Services\Install\InstallationProvisioningService;
use App\Services\Install\InstallationViewService;
use App\Services\Search\EntitySearchService;
use App\Services\Search\NoSearchAnalytics;
use App\Services\Storage\ExternalStorageService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
use Modules\Blog\Models\CategoryTranslation;
use Modules\Blog\Models\PostSectionTranslation;
use Modules\Blog\Models\PostTranslation;
use Modules\Gallery\Models\GalleryTranslation;
use Modules\Pages\Models\PageTranslation;
use Modules\Pages\Models\SectionTranslation;
use Modules\Shop\Models\ProductCategoryTranslation;
use Modules\Shop\Models\ProductSectionTranslation;
use Modules\Shop\Models\ProductTranslation;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(EditorialWorkflowInterface::class, NoEditorialWorkflow::class);
        $this->app->singleton(MaintenanceModeInterface::class, NullMaintenanceModeService::class);
        $this->app->bind(SearchAnalyticsInterface::class, NoSearchAnalytics::class);
        $this->app->bind(DatatableQueryServiceInterface::class, DatatableQueryService::class);
        $this->app->bind(MenuEditorServiceInterface::class, MenuEditorService::class);
        $this->app->bind(PublishedSectionTextInterface::class, PublishedSectionTextService::class);
        $this->app->bind(ModuleSettingsAdministrationInterface::class, ModuleSettingsAdministrationService::class);
        $this->app->singleton(ModuleSettingsExtensionRegistry::class);
        $this->app->bind(InstallationEnvironmentServiceInterface::class, InstallationEnvironmentService::class);
        $this->app->bind(InstallationDatabaseServiceInterface::class, InstallationDatabaseService::class);
        $this->app->bind(InstallationProvisioningServiceInterface::class, InstallationProvisioningService::class);
        $this->app->bind(InstallationAdminServiceInterface::class, InstallationAdminService::class);
        $this->app->bind(InstallationViewServiceInterface::class, InstallationViewService::class);
        $this->app->singleton(PublicSemanticRegistry::class);
        $this->app->singleton(PublicationBoundaryService::class);
        $this->app->singleton(NotificationRecipientPolicyRegistry::class);
        $this->app->bind(ModulePackageInstallerInterface::class, ModulePackageInstallerService::class);
        $this->app->bind(NotificationRecipientResolverInterface::class, NotificationRecipientResolverService::class);
        $this->app->bind(AdminDashboardServiceInterface::class, AdminDashboardService::class);
        $this->app->singleton(LocalizationServiceInterface::class, LocalizationService::class);
        $this->app->singleton(GeneralSettingsServiceInterface::class, GeneralSettingsService::class);
        $this->app->singleton(ModuleSettingsServiceInterface::class, ModuleSettingsService::class);
        $this->app->singleton(ModuleNotificationServiceInterface::class, ModuleNotificationService::class);
        $this->app->singleton(ModuleManager::class);
        $this->app->alias(ModuleManager::class, ModulePackageUploadServiceInterface::class);
        $this->app->singleton(AdminNavigationServiceInterface::class, AdminNavigationService::class);
        $this->app->singleton(AdminThemeServiceInterface::class, AdminThemeService::class);
        $this->app->singleton(BrandServiceInterface::class, BrandService::class);
        $this->app->singleton(BlockRegistryInterface::class, BlockRegistry::class);
        $this->app->singleton(EntitySchemaRegistryInterface::class, EntitySchemaRegistry::class);
        $this->app->singleton(EntitySearchServiceInterface::class, EntitySearchService::class);
        $this->app->singleton(ExternalStorageService::class);
        $this->app->singleton(CacheDependencyCollector::class);
    }

    public function boot(): void
    {
        User::observe(UserSecurityObserver::class);
        foreach ([
            PageTranslation::class, SectionTranslation::class,
            PostTranslation::class, PostSectionTranslation::class, CategoryTranslation::class,
            ProductTranslation::class, ProductSectionTranslation::class, ProductCategoryTranslation::class,
            GalleryTranslation::class, 'Modules\\Gallery\\Models\\GalleryItemTranslation',
        ] as $translationModel) {
            if (class_exists($translationModel)) {
                $translationModel::observe(ContentTranslationCacheObserver::class);
            }
        }
        // Public (unauthenticated) write endpoints — forms, comments, shop
        // checkout/inquiry/cart and geo preference — are throttled per IP.
        RateLimiter::for('public-writes', fn (Request $request): Limit => Limit::perMinute(
            max(1, (int) config('cms.public_writes_per_minute', 30))
        )->by($request->ip()));

        // Compiled assets resolve with root-relative URLs so the public page
        // cache stays host-agnostic: the same cached HTML works behind any
        // domain, port mapping or reverse proxy without leaking the origin
        // host of whichever request rendered the page first.
        Vite::createAssetPathsUsing(
            static fn (string $path, ?bool $secure = null): string => '/'.ltrim($path, '/')
        );

        // Enforce strict Eloquent behavior to prevent N+1 issues and unfillable attributes
        Model::shouldBeStrict(! $this->app->isProduction());

        // Safety net: a production deployment must never leak stack traces, even if
        // APP_DEBUG was left enabled by mistake in .env.
        if ($this->app->isProduction()) {
            config([
                'app.debug' => false,
                'logging.channels.stack.level' => config('logging.channels.stack.level', 'warning'),
            ]);
        }

        // Register the optional external (S3-compatible) disk from the backoffice
        // settings so documents can be stored on AWS / OVH / Cloudflare R2.
        try {
            $this->app->make(ExternalStorageService::class)->registerDisk();
        } catch (\Throwable) {
            // Settings not available yet (install / migrate): keep the default disk.
        }

        URL::defaults([
            'market' => 'es',
            'locale' => 'es',
            'shopPrefix' => 'tienda',
            'blogPrefix' => 'blog',
        ]);

        if (! $this->app->isProduction()) {
            Passport::$validateKeyPermissions = false;
        }

        // Load Passport migrations
        $passportMigrations = base_path('vendor/laravel/passport/database/migrations');
        if (File::exists($passportMigrations)) {
            $this->loadMigrationsFrom($passportMigrations);
        }

        // Load all module migrations
        $modulesPath = base_path('modules');
        if (File::exists($modulesPath)) {
            foreach (File::directories($modulesPath) as $moduleDir) {
                $migrationDir = $moduleDir.'/Database/Migrations';
                if (File::exists($migrationDir)) {
                    $this->loadMigrationsFrom($migrationDir);
                }
            }
        }

        // Register themes view path
        $themesPath = base_path('themes');
        if (File::exists($themesPath)) {
            View::addLocation(base_path());
            View::addNamespace('themes', $themesPath);
        }

        // The front theme is selected at runtime via `cms.theme`; long-lived
        // processes (queue workers, tests) may switch it, so drop the cached view
        // resolution whenever the value changes (see ThemeManagerService::syncActiveTheme).
        if (class_exists(ThemeManagerService::class)) {
            $this->app->make(ThemeManagerService::class)->syncActiveTheme();
        }

        // Register isolated backoffice theme paths & component overrides
        /** @var AdminThemeServiceInterface $adminThemeService */
        $adminThemeService = $this->app->make(AdminThemeServiceInterface::class);
        $adminThemeService->registerThemeViewPaths();

        // Invalidate the public page cache whenever cacheable content changes.
        // Content is edited continuously, so freshness is event-driven (see
        // App\Services\Cms\PageCacheService for the namespace-version strategy).
        $pageCacheObserver = $this->app->make(PageCacheObserver::class);
        $collector = $this->app->make(CacheDependencyCollector::class);

        foreach ((array) config('cms.page_cache_models', []) as $model) {
            if (! class_exists($model)) {
                continue;
            }

            $model::observe($pageCacheObserver);

            // While a public page is rendered, record which cacheable entities it
            // reads so PageCacheService can invalidate only the affected pages.
            $model::retrieved(function (Model $retrieved) use ($collector): void {
                // Read the key from the raw attributes: partial selects (and
                // Model::shouldBeStrict) must not throw MissingAttributeException.
                $key = $retrieved->getAttributes()[$retrieved->getKeyName()] ?? null;

                if ($key !== null) {
                    $collector->record($retrieved->getTable(), $key);
                }
            });
        }

        // Register default admin & cms components namespace
        Blade::anonymousComponentPath(resource_path('views/components/cms'), 'cms');

        // Bind global brand identity & theme flavor dynamically to all views.
        // Resolved at render time (not once per container) so switching the active
        // theme within a process — e.g. tests, queue workers — stays correct.
        View::composer('*', function (\Illuminate\View\View $view): void {
            $brand = app(BrandServiceInterface::class)->current();

            $view->with([
                'cmsBrand' => $brand,
                // Brand identity (theme manifest) — what the UI shows as the product brand.
                'cmsBrandName' => $brand->name !== '' ? $brand->name : (string) config('cms.name', 'CMS'),
                // Site name (APP_NAME / cms.name) — used for SEO titles and site metadata.
                'cmsSiteName' => (string) config('cms.name', 'CMS'),
                'adminTheme' => $brand->theme,
            ]);
        });

        // Bind global admin layout context through View Composer
        View::composer(['admin.layout', 'admin.*'], AdminLayoutComposer::class);

        // Bind global frontend presentation context through View Composer
        View::composer(['themes::*', 'themes.*', '*.partials.*', 'themes.*.partials.*', 'themes.components.*'], FrontendLayoutComposer::class);

        // Register Core Static & Composite Blocks
        /** @var BlockRegistryInterface $blockRegistry */
        $blockRegistry = $this->app->make(BlockRegistryInterface::class);
        $blockRegistry->register(new HeroBlock);
        $blockRegistry->register(new RichTextBlock);
        $blockRegistry->register(new FeaturesBlock);
        $blockRegistry->register(new KpiBlock);
        $blockRegistry->register(new PricingBlock);
        $blockRegistry->register(new FaqBlock);
        $blockRegistry->register(new TestimonialsBlock);
        $blockRegistry->register(new CarouselBlock);
        $blockRegistry->register(new CtaBlock);
        $blockRegistry->register(new BeforeAfterBlock);
        $blockRegistry->register(new CodeEmbedBlock);
        $blockRegistry->register(new RoiBlock);
        $blockRegistry->register(new SearchBoxBlock);
        $blockRegistry->register(new VideoBlock);

        // RBAC Gates
        Gate::before(function (User $user, string $ability): ?bool {
            if ($user->isAdmin()) {
                return true;
            }

            return null;
        });

        Gate::define('access:admin', fn (User $user): bool => $user->isAdmin() || $user->hasPermission('access:admin') || $user->roles()->exists());
        Gate::define('manage:modules', fn (User $user): bool => $user->hasPermission('manage:modules'));
        Gate::define('manage:users', fn (User $user): bool => $user->hasPermission('manage:users'));
        Gate::define('manage:posts', fn (User $user): bool => $user->hasPermission('manage:posts'));
        Gate::define('manage:pages', fn (User $user): bool => $user->hasPermission('manage:pages'));
        Gate::define('manage:forms', fn (User $user): bool => $user->hasPermission('manage:forms'));
        Gate::define('manage:shop', fn (User $user): bool => $user->hasPermission('manage:shop'));
        Gate::define('manage:menus', fn (User $user): bool => $user->hasPermission('manage:menus'));
        Gate::define('manage:themes', fn (User $user): bool => $user->hasPermission('manage:themes'));
        Gate::define('manage:media', fn (User $user): bool => $user->hasPermission('manage:media'));
        Gate::define('manage:storage', fn (User $user): bool => $user->hasPermission('manage:storage'));
        Gate::define('manage:seo', fn (User $user): bool => $user->hasPermission('manage:seo'));

        // Discover and boot active pluggable CMS modules dynamically
        /** @var ModuleManager $moduleManager */
        $moduleManager = $this->app->make(ModuleManager::class);
        $moduleManager->bootActiveModules();
    }
}
