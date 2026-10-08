<?php

declare(strict_types=1);

use App\Contracts\Services\LocalizationServiceInterface;
use App\Core\Modules\ModuleManager;
use App\Core\Routing\FrontRoutePatterns;
use App\Http\Controllers\Admin\Ai\AiPromptDownloadGetController;
use App\Http\Controllers\Admin\Ai\AiPromptRawGetController;
use App\Http\Controllers\Admin\Api\EntitySearchGetController;
use App\Http\Controllers\Admin\Auth\LoginGetController;
use App\Http\Controllers\Admin\Auth\LoginPostController;
use App\Http\Controllers\Admin\Auth\LogoutPostController;
use App\Http\Controllers\Admin\Auth\QuickLoginGetController;
use App\Http\Controllers\Admin\Connections\ConnectionsGetController;
use App\Http\Controllers\Admin\Connections\ConnectionsMigratePostController;
use App\Http\Controllers\Admin\Connections\ConnectionsMigrationStatusGetController;
use App\Http\Controllers\Admin\Connections\ConnectionsPutController;
use App\Http\Controllers\Admin\Connections\ConnectionsTestPostController;
use App\Http\Controllers\Admin\Dashboard\DashboardGetController;
use App\Http\Controllers\Admin\Markets\LanguageDeleteController;
use App\Http\Controllers\Admin\Markets\LanguagePostController;
use App\Http\Controllers\Admin\Markets\LanguagePutController;
use App\Http\Controllers\Admin\Markets\LanguageTogglePostController;
use App\Http\Controllers\Admin\Markets\MarketDeleteController;
use App\Http\Controllers\Admin\Markets\MarketPostController;
use App\Http\Controllers\Admin\Markets\MarketPutController;
use App\Http\Controllers\Admin\Markets\MarketsGetController;
use App\Http\Controllers\Admin\Markets\MarketTogglePostController;
use App\Http\Controllers\Admin\Media\MediaDeleteController;
use App\Http\Controllers\Admin\Media\MediaGetController;
use App\Http\Controllers\Admin\Media\MediaPutController;
use App\Http\Controllers\Admin\Media\MediaUploadPostController;
use App\Http\Controllers\Admin\Menus\MenuDeleteController;
use App\Http\Controllers\Admin\Menus\MenuItemDeleteController;
use App\Http\Controllers\Admin\Menus\MenuItemPostController;
use App\Http\Controllers\Admin\Menus\MenuPostController;
use App\Http\Controllers\Admin\Menus\MenusGetController;
use App\Http\Controllers\Admin\Menus\MenuZonePostController;
use App\Http\Controllers\Admin\Modules\ModuleImpactGetController;
use App\Http\Controllers\Admin\Modules\ModuleInstallPostController;
use App\Http\Controllers\Admin\Modules\ModulesGetController;
use App\Http\Controllers\Admin\Modules\ModuleTogglePostController;
use App\Http\Controllers\Admin\Modules\ModuleUninstallPostController;
use App\Http\Controllers\Admin\Modules\ModuleUploadPostController;
use App\Http\Controllers\Admin\ModuleSettings\ModuleSettingsGetController;
use App\Http\Controllers\Admin\ModuleSettings\ModuleSettingsPutController;
use App\Http\Controllers\Admin\Notifications\NotificationEmailPreferencePostController;
use App\Http\Controllers\Admin\Notifications\NotificationReadPostController;
use App\Http\Controllers\Admin\Notifications\NotificationsGetController;
use App\Http\Controllers\Admin\Notifications\NotificationsMarkAllReadPostController;
use App\Http\Controllers\Admin\Orchestration\ApprovePostController;
use App\Http\Controllers\Admin\Orchestration\CancelPostController;
use App\Http\Controllers\Admin\Orchestration\ResumePostController;
use App\Http\Controllers\Admin\Orchestration\ReviewGetController;
use App\Http\Controllers\Admin\Orchestration\ShowGetController;
use App\Http\Controllers\Admin\Seo\FilterDeleteController;
use App\Http\Controllers\Admin\Seo\FilterPostController;
use App\Http\Controllers\Admin\Seo\FilterPutController;
use App\Http\Controllers\Admin\Seo\FiltersGetController;
use App\Http\Controllers\Admin\Seo\SeoAuditGetController;
use App\Http\Controllers\Admin\SetLocaleGetController;
use App\Http\Controllers\Admin\SetLocalePostController;
use App\Http\Controllers\Admin\Settings\SettingsGetController;
use App\Http\Controllers\Admin\Settings\SettingsPutController;
use App\Http\Controllers\Admin\Themes\ThemeActivatePostController;
use App\Http\Controllers\Admin\Themes\ThemesGetController;
use App\Http\Controllers\Admin\Tokens\ClientRevokePostController;
use App\Http\Controllers\Admin\Tokens\M2mClientPostController;
use App\Http\Controllers\Admin\Tokens\PersonalTokenPostController;
use App\Http\Controllers\Admin\Tokens\TokenCreateGetController;
use App\Http\Controllers\Admin\Tokens\TokenRevokePostController;
use App\Http\Controllers\Admin\Tokens\TokensGetController;
use App\Http\Controllers\Admin\Users\UserDeleteController;
use App\Http\Controllers\Admin\Users\UserPostController;
use App\Http\Controllers\Admin\Users\UserRolePostController;
use App\Http\Controllers\Admin\Users\UsersGetController;
use App\Http\Controllers\Api\V1\GeoSeoController;
use App\Http\Controllers\Cms\ThemeAssetGetController;
use App\Http\Controllers\Geo\GeoPreferencePostController;
use App\Http\Controllers\Install\AdminGetController;
use App\Http\Controllers\Install\AdminStorePostController;
use App\Http\Controllers\Install\CompleteGetController;
use App\Http\Controllers\Install\DatabaseGetController;
use App\Http\Controllers\Install\DatabaseSavePostController;
use App\Http\Controllers\Install\DatabaseTestPostController;
use App\Http\Controllers\Install\MigrationGetController;
use App\Http\Controllers\Install\MigrationsPostController;
use App\Http\Controllers\Install\RequirementsGetController;
use App\Http\Controllers\Media\UploadsServeController;
use App\Http\Controllers\Seo\RssController;
use App\Http\Controllers\Seo\SitemapController;
use App\Http\Middleware\CachePublicPage;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureAdminAuthenticated;
use App\Http\Middleware\EnsureSeoMeta;
use App\Http\Middleware\RedirectIfInstalled;
use App\Http\Middleware\ResolveMarketAndLocaleMiddleware;
use App\Http\Middleware\SetBackofficeLocale;
use App\Models\Market;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\AiAssistant\Http\Middleware\EnsureAiAuthorized;
use Modules\Pages\Http\Controllers\FrontPageController;

// ==========================================
// Web Installer Wizard (/install)
// ==========================================
Route::prefix('install')->middleware(['web', 'throttle:10,1', RedirectIfInstalled::class])->group(function (): void {
    Route::get('/', RequirementsGetController::class)->name('install.step1');
    Route::get('/database', DatabaseGetController::class)->name('install.step2');
    Route::post('/database/test', DatabaseTestPostController::class)->name('install.test-db');
    Route::post('/database/save', DatabaseSavePostController::class)->name('install.save-db');
    Route::get('/migrate', MigrationGetController::class)->name('install.step3');
    Route::post('/migrate/run', MigrationsPostController::class)->name('install.run-migrations');
    Route::get('/admin', AdminGetController::class)->name('install.step4');
    Route::post('/admin/store', AdminStorePostController::class)->name('install.store-admin');
    Route::get('/complete', CompleteGetController::class)->name('install.step5');
});

// ==========================================
// Generative Engine Optimization (GEO) & SEO
// ==========================================
// 1. Global root endpoints
Route::get('/llms.txt', [GeoSeoController::class, 'llmsTxt'])->name('geo.llms');
Route::get('/llms-full.txt', [GeoSeoController::class, 'llmsFullTxt'])->name('geo.llms-full');

// Sitemap index → segmented child sitemaps (pages/posts/products/galleries),
// dynamic robots.txt and the blog RSS feed. All cached with the page-cache version.
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('seo.sitemap');
Route::get('/sitemaps.xml', [SitemapController::class, 'index'])->name('seo.sitemaps');
Route::get('/sitemaps/{segment}.xml', [SitemapController::class, 'segment'])->name('seo.sitemap.segment');
Route::get('/sitemap-part-{part}.xml', [GeoSeoController::class, 'sitemapPart'])->where('part', '[1-9][0-9]*');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('seo.robots');
Route::get('/feed.xml', RssController::class)->name('seo.feed');

// Uploads fallback: static files are served directly by the web server, so this
// only runs for files missing locally and resolves them from external storage.
Route::get('/uploads/{path}', UploadsServeController::class)
    ->where('path', '.*')
    ->name('media.uploads');

// 2. Localized market & locale scoped endpoints
Route::middleware(['web', ResolveMarketAndLocaleMiddleware::class, EnsureSeoMeta::class, CachePublicPage::class])->group(function (): void {
    $adminPath = (string) config('cms.admin_path', 'admin');
    $blocked = "admin|{$adminPath}|api|forms|geo|install|media|up";

    Route::prefix('{market}/{locale}')
        ->where(['market' => "^(?!{$blocked})[a-zA-Z0-9_\-]+$", 'locale' => FrontRoutePatterns::locale()])
        ->group(function (): void {
            Route::get('/llms.txt', [GeoSeoController::class, 'llmsTxt'])->name('geo.market.llms');
            Route::get('/llms-full.txt', [GeoSeoController::class, 'llmsFullTxt'])->name('geo.market.llms-full');
            Route::get('/sitemap.xml', [GeoSeoController::class, 'sitemapXml'])->name('seo.market.sitemap');
            Route::get('/sitemaps.xml', [GeoSeoController::class, 'sitemapXml'])->name('seo.market.sitemaps');
            Route::get('/sitemap-part-{part}.xml', [GeoSeoController::class, 'sitemapPart'])->where('part', '[1-9][0-9]*');

            Route::get('/', function (ModuleManager $moduleManager) {
                if (! $moduleManager->isModuleEnabled('pages')) {
                    return view('welcome-cms', [
                        'activeMarkets' => Market::where('is_active', true)->get(),
                        'installedModules' => $moduleManager->getActiveModules(),
                    ]);
                }
                abort(404);
            });
            Route::get('', function (ModuleManager $moduleManager) {
                if (! $moduleManager->isModuleEnabled('pages')) {
                    return view('welcome-cms', [
                        'activeMarkets' => Market::where('is_active', true)->get(),
                        'installedModules' => $moduleManager->getActiveModules(),
                    ]);
                }
                abort(404);
            });
        });

    Route::prefix('{locale}')
        ->where(['locale' => "^(?!{$blocked}|blog|galeria|gallery|shop|tienda)[a-zA-Z]{2,5}$"])
        ->group(function (): void {
            Route::get('/llms.txt', [GeoSeoController::class, 'llmsTxt']);
            Route::get('/llms-full.txt', [GeoSeoController::class, 'llmsFullTxt']);
            Route::get('/sitemap.xml', [GeoSeoController::class, 'sitemapXml']);
            Route::get('/sitemaps.xml', [GeoSeoController::class, 'sitemapXml']);
            Route::get('/sitemap-part-{part}.xml', [GeoSeoController::class, 'sitemapPart'])->where('part', '[1-9][0-9]*');

            Route::get('/', function (ModuleManager $moduleManager) {
                if (! $moduleManager->isModuleEnabled('pages')) {
                    return view('welcome-cms', [
                        'activeMarkets' => Market::where('is_active', true)->get(),
                        'installedModules' => $moduleManager->getActiveModules(),
                    ]);
                }
                abort(404);
            });
            Route::get('', function (ModuleManager $moduleManager) {
                if (! $moduleManager->isModuleEnabled('pages')) {
                    return view('welcome-cms', [
                        'activeMarkets' => Market::where('is_active', true)->get(),
                        'installedModules' => $moduleManager->getActiveModules(),
                    ]);
                }
                abort(404);
            });
        });
});

// Visitor Geo Preference Cookie Handler
Route::post('/geo/set-preference', GeoPreferencePostController::class)->name('geo.set-preference');

// Backoffice theme assets (served from resources/views/themes/admin/{theme}/assets)
Route::get('/theme-assets/{theme}/{path}', ThemeAssetGetController::class)
    ->where('path', '.*')
    ->name('theme.asset');

// ==========================================
// Backoffice Administration Suite
// ==========================================
$adminPath = config('cms.admin_path', 'admin');

if ($adminPath !== 'admin') {
    Route::get('/admin', fn () => redirect("/{$adminPath}/login"));
}

Route::prefix($adminPath)->name('admin.')->middleware([SetBackofficeLocale::class])->group(function (): void {
    // Guest Auth Routes
    Route::get('/login', LoginGetController::class)->name('login');
    Route::post('/login', LoginPostController::class)->name('login.submit');
    Route::get('/quick-login/{role}', QuickLoginGetController::class)->name('quick-login');
    Route::post('/logout', LogoutPostController::class)->name('logout');

    // Backoffice language switch — deliberately available to guests too, so the
    // login screen can be read in the visitor's language. It only stores a
    // whitelisted session preference.
    Route::get('/locale/{locale}', SetLocaleGetController::class)->name('locale.switch');
    Route::post('/locale', SetLocalePostController::class)->name('locale.update');

    // Authenticated Backoffice Routes
    Route::middleware([EnsureAdminAuthenticated::class])->group(function (): void {
        Route::get('/', DashboardGetController::class)->name('dashboard');
        Route::get('/dashboard', fn () => redirect()->route('admin.dashboard'));

        // Generic AJAX search for the reusable searchable select component
        Route::get('/api/entity-search', EntitySearchGetController::class)->name('api.entity-search');

        // SEO & GEO Live Audit
        Route::middleware([CheckPermission::class.':manage:seo'])->group(function (): void {
            Route::get('/seo/audit', SeoAuditGetController::class)->name('seo.audit');
            Route::get('/seo', fn () => redirect()->route('admin.seo.audit'));

            // Curated filter landings (dynamic filter/search pages with SEO text).
            Route::get('/seo/filters', FiltersGetController::class)->name('seo.filters.index');
            Route::post('/seo/filters', FilterPostController::class)->name('seo.filters.store');
            Route::put('/seo/filters/{filterPage}', FilterPutController::class)->name('seo.filters.update');
            Route::delete('/seo/filters/{filterPage}', FilterDeleteController::class)->name('seo.filters.destroy');
        });

        // AI Editorial Prompt & Style Guide
        Route::get('/ai/download-prompt', AiPromptDownloadGetController::class)->name('ai.download-prompt');
        Route::get('/ai/raw-prompt', AiPromptRawGetController::class)->name('ai.raw-prompt');

        // Module Management
        Route::middleware([CheckPermission::class.':manage:modules'])->group(function (): void {
            Route::get('/modules', ModulesGetController::class)->name('modules.index');
            Route::post('/modules/upload', ModuleUploadPostController::class)->name('modules.upload');
            Route::post('/modules/{identifier}/install', ModuleInstallPostController::class)->name('modules.install');
            Route::post('/modules/{identifier}/uninstall', ModuleUninstallPostController::class)->name('modules.uninstall');
            Route::post('/modules/{identifier}/toggle', ModuleTogglePostController::class)->name('modules.toggle');
            Route::get('/modules/{identifier}/impact', ModuleImpactGetController::class)->name('modules.impact');
            Route::get('/modules/{identifier}/settings', ModuleSettingsGetController::class)->name('modules.settings');
            Route::put('/modules/{identifier}/settings', ModuleSettingsPutController::class)->name('modules.settings.update');

            // Markets & Locales
            Route::get('/markets', MarketsGetController::class)->name('markets.index');
            Route::post('/markets', MarketPostController::class)->name('markets.store');
            Route::put('/markets/{market}', MarketPutController::class)->name('markets.update');
            Route::post('/markets/{market}/toggle', MarketTogglePostController::class)->name('markets.toggle');
            Route::delete('/markets/{market}', MarketDeleteController::class)->name('markets.destroy');
            Route::post('/languages', LanguagePostController::class)->name('languages.store');
            Route::put('/languages/{language}', LanguagePutController::class)->name('languages.update');
            Route::post('/languages/{language}/toggle', LanguageTogglePostController::class)->name('languages.toggle');
            Route::delete('/languages/{language}', LanguageDeleteController::class)->name('languages.destroy');

            // General Site & Company Settings
            Route::get('/settings', SettingsGetController::class)->name('settings.index');
            Route::put('/settings', SettingsPutController::class)->name('settings.update');
        });

        // External Connections (AWS S3 / OVH / Cloudflare R2 document storage)
        Route::middleware([CheckPermission::class.':manage:storage'])->group(function (): void {
            Route::get('/connections', ConnectionsGetController::class)->name('connections.index');
            Route::put('/connections', ConnectionsPutController::class)->name('connections.update');
            Route::post('/connections/test', ConnectionsTestPostController::class)->name('connections.test');
            Route::post('/connections/migrate', ConnectionsMigratePostController::class)->name('connections.migrate');
            Route::get('/connections/migration/status', ConnectionsMigrationStatusGetController::class)->name('connections.migration.status');
        });

        // User & Role Management
        Route::middleware([CheckPermission::class.':manage:users'])->group(function (): void {
            Route::get('/users', UsersGetController::class)->name('users.index');
            Route::post('/users', UserPostController::class)->name('users.store');
            Route::post('/users/{user}/role', UserRolePostController::class)->name('users.update-role');
            Route::delete('/users/{user}', UserDeleteController::class)->name('users.destroy');

            // API & MCP Tokens / M2M Clients Management
            Route::get('/tokens', TokensGetController::class)->name('tokens.index');
            Route::get('/tokens/create', TokenCreateGetController::class)->name('tokens.create');
            Route::post('/tokens/personal', PersonalTokenPostController::class)->name('tokens.store-personal');
            Route::post('/tokens/m2m', M2mClientPostController::class)->name('tokens.store-m2m');
            Route::post('/tokens/{id}/revoke', TokenRevokePostController::class)->name('tokens.revoke');
            Route::post('/tokens/clients/{id}/revoke', ClientRevokePostController::class)->name('tokens.revoke-client');
        });

        // Menus & Navigation Zones
        Route::middleware([CheckPermission::class.':manage:menus'])->group(function (): void {
            Route::get('/menus', MenusGetController::class)->name('menus.index');
            Route::post('/menus', MenuPostController::class)->name('menus.store');
            Route::post('/menus/zones', MenuZonePostController::class)->name('menus.zones.store');
            Route::delete('/menus/{menu}', MenuDeleteController::class)->name('menus.destroy');
            Route::post('/menus/{menu}/items', MenuItemPostController::class)->name('menus.store-item');
            Route::delete('/menus/items/{item}', MenuItemDeleteController::class)->name('menus.destroy-item');
        });

        // Themes & Layout Switcher
        Route::middleware([CheckPermission::class.':manage:themes'])->group(function (): void {
            Route::get('/themes', ThemesGetController::class)->name('themes.index');
            Route::post('/themes/{theme}/activate', ThemeActivatePostController::class)->name('themes.activate');
        });

        // Media Manager API for WYSIWYG & Filemanager Modal
        Route::get('/media', MediaGetController::class)->middleware(CheckPermission::class.':manage:media')->name('media.index');
        Route::middleware([CheckPermission::class.':manage:media'])->group(function (): void {
            Route::post('/media/upload', MediaUploadPostController::class)->name('media.upload');
            Route::put('/media/{id}', MediaPutController::class)->name('media.update');
            Route::delete('/media/{filename}', MediaDeleteController::class)->name('media.destroy')->where('filename', '.*');
        });

        // Notifications API
        Route::get('/notifications', NotificationsGetController::class)->name('notifications.index');
        Route::post('/notifications/email-preference', NotificationEmailPreferencePostController::class)->name('notifications.email-preference');
        Route::post('/notifications/mark-all-read', NotificationsMarkAllReadPostController::class)->name('notifications.mark-all-read');
        Route::post('/notifications/{id}/read', NotificationReadPostController::class)->name('notifications.read');
    });
});

// ==========================================
// Root Domain Dynamic Routing
// ==========================================
Route::get('/', function (Request $request, LocalizationServiceInterface $localization, ModuleManager $moduleManager) {
    if ($moduleManager->isModuleEnabled('pages') && class_exists(FrontPageController::class)) {
        $hasMarkets = $localization->hasMultipleMarkets();
        $hasLangs = $localization->hasMultipleLanguages();

        $defaultMarket = Market::where('is_default', true)->where('is_active', true)->first()
            ?? Market::where('is_active', true)->first();
        $marketCode = $defaultMarket ? $defaultMarket->code : 'es';
        $locale = $defaultMarket ? $defaultMarket->default_locale : 'es';

        // If single market
        if (! $hasMarkets) {
            if (! $hasLangs) {
                return app(FrontPageController::class)->show($request);
            }

            return redirect("/{$locale}");
        }

        // If multi-market: redirect to /{market}/{locale}
        return redirect("/{$marketCode}/{$locale}");
    }

    return view('welcome-cms', [
        'activeMarkets' => Market::where('is_active', true)->get(),
        'installedModules' => $moduleManager->getActiveModules(),
    ]);
})->middleware(['web', ResolveMarketAndLocaleMiddleware::class]);

Route::prefix($adminPath.'/orchestration')->name('admin.orchestration.')->middleware(['web', 'auth', EnsureAdminAuthenticated::class, SetBackofficeLocale::class, EnsureAiAuthorized::class.':use:ai'])->group(__DIR__.'/orchestration.php');

Route::post($adminPath.'/orchestration/{execution}/approve', ApprovePostController::class)->name('admin.orchestration.approve')->middleware(['web', 'auth', EnsureAdminAuthenticated::class, SetBackofficeLocale::class, EnsureAiAuthorized::class.':use:ai']);

Route::prefix($adminPath.'/executions')->name('admin.executions.')->middleware(['web', 'auth', EnsureAdminAuthenticated::class, SetBackofficeLocale::class])->group(function (): void {
    Route::get('/{execution}', ReviewGetController::class)->name('review');
    Route::get('/{execution}/status', ShowGetController::class)->name('show');
    Route::post('/{execution}/approve', ApprovePostController::class)->name('approve');
    Route::post('/{execution}/resume', ResumePostController::class)->name('resume');
    Route::post('/{execution}/cancel', CancelPostController::class)->name('cancel');
});
