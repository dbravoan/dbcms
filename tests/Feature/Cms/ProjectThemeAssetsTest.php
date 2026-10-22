<?php

declare(strict_types=1);

use App\Contracts\Services\GeneralSettingsServiceInterface;
use App\Models\Setting;
use App\Services\Cms\FrontViewResolver;
use App\Services\Cms\PageCacheService;
use App\Services\Cms\PublicPagePersonalization;
use App\Services\Cms\ThemeManagerService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    // A regression to the legacy writer must never touch the developer's .env.
    File::partialMock()->shouldReceive('exists')->with(base_path('.env'))->andReturnFalse();
});

test('public asset entries include only the active frontend and backoffice brand', function (string $brand): void {
    config(['cms.theme' => 'db-minimal', 'cms.admin_theme' => $brand]);

    expect(cms_asset_entries(true))->toBe([
        'resources/css/app.css',
        'resources/js/app.js',
        'resources/views/themes/admin/'.$brand.'/theme.css',
        'themes/db-minimal/theme.css',
    ])->not->toContain('themes/db-cyber/theme.css');
    expect(cms_asset_entries())->not->toContain('themes/db-minimal/theme.css');
})->with(fn (): array => array_map('basename', glob(dirname(__DIR__, 3).'/resources/views/themes/admin/*', GLOB_ONLYDIR) ?: []));

test('derived theme discovery and fallbacks stay inside the installed selection', function (): void {
    config(['cms.project' => ['themes' => ['db-minimal'], 'default_theme' => 'db-minimal'], 'cms.theme' => 'missing-theme']);

    expect(array_keys(app(ThemeManagerService::class)->getAvailableThemes()))->toBe(['db-minimal']);
    expect(app(FrontViewResolver::class)->layout())->toBe('themes::db-minimal.layouts.app');
});

test('activating a selected theme persists across cached configuration reloads and invalidates public html', function (): void {
    config(['cms.project' => ['themes' => ['db-minimal', 'db-editorial'], 'default_theme' => 'db-minimal'], 'cms.theme' => 'db-minimal']);
    $cache = app(PageCacheService::class);
    $version = $cache->version();
    $environment = hash_file('sha256', app()->environmentFilePath());

    app(ThemeManagerService::class)->setActiveTheme('db-editorial');
    config(['cms.theme' => 'db-minimal']);
    app(GeneralSettingsServiceInterface::class)->flushCache();

    expect(app(ThemeManagerService::class)->getActiveTheme())->toBe('db-editorial');
    expect($cache->version())->toBeGreaterThan($version);
    expect(hash_file('sha256', app()->environmentFilePath()))->toBe($environment);
});

test('uninstalled themes cannot be activated even if their files exist in the source catalogue', function (): void {
    config(['cms.project' => ['themes' => ['db-minimal'], 'default_theme' => 'db-minimal'], 'cms.theme' => 'db-minimal']);

    expect(fn () => app(ThemeManagerService::class)->setActiveTheme('db-cyber'))->toThrow(ValidationException::class);
    expect(fn () => app(ThemeManagerService::class)->setActiveTheme('../db-minimal'))->toThrow(ValidationException::class);
});

test('the next request refreshes a stored theme change even with stale worker configuration', function (): void {
    config(['cms.project' => ['themes' => ['db-minimal', 'db-editorial'], 'default_theme' => 'db-minimal'], 'cms.theme' => 'db-minimal']);
    $manager = app(ThemeManagerService::class);
    expect($manager->getActiveTheme())->toBe('db-minimal');

    // Another request updates the database while this worker retains its config.
    Setting::setValue(ThemeManagerService::ACTIVE_THEME_SETTING, 'db-editorial', 'appearance');
    $manager->syncActiveTheme();

    expect(config('cms.theme'))->toBe('db-editorial');
    expect(cms_asset_entries(true))->toContain('themes/db-editorial/theme.css')->not->toContain('themes/db-minimal/theme.css');
});

test('theme cache invalidation respects the configured compiled view directory', function (): void {
    $directory = sys_get_temp_dir().'/dbcms-theme-views-'.bin2hex(random_bytes(8));
    mkdir($directory, 0700);
    file_put_contents($directory.'/compiled.php', '<?php /* disposable compiled view */');
    file_put_contents($directory.'/keep.txt', 'unrelated');
    config(['view.compiled' => $directory]);

    try {
        app(ThemeManagerService::class)->flushCompiledViews();

        expect(is_file($directory.'/compiled.php'))->toBeFalse();
        expect(is_file($directory.'/keep.txt'))->toBeTrue();
    } finally {
        File::deleteDirectory($directory);
    }
});

test('derived widgets use their module fallback without borrowing another frontend', function (): void {
    config(['cms.project' => ['themes' => ['db-minimal'], 'default_theme' => 'db-minimal'], 'cms.theme' => 'db-minimal']);
    View::shouldReceive('exists')->with('themes::db-minimal.shop.blocks.example')->andReturnFalse();
    View::shouldReceive('exists')->with('shop::blocks.example')->andReturnTrue();

    expect(app(FrontViewResolver::class)->resolve('shop.blocks.example', 'shop::blocks.example'))->toBe('shop::blocks.example');
});

test('public cache fragments accept installed custom themes and reject uninstalled headers', function (): void {
    $manager = Mockery::mock(ThemeManagerService::class);
    $manager->shouldReceive('getAvailableThemes')->andReturn(['client-site' => []]);
    app()->instance(ThemeManagerService::class, $manager);
    $personalization = new PublicPagePersonalization($manager);
    $render = static fn (string $view): string => '<header>'.$view.'</header>';

    expect($personalization->hydrate('<!--cms-fragment:themes::client-site.partials.header-->', 'csrf', $render))
        ->toBe('<header>themes::client-site.partials.header</header>');
    expect(fn () => $personalization->hydrate('<!--cms-fragment:themes::db-cyber.partials.header-->', 'csrf', $render))
        ->toThrow(UnexpectedValueException::class);
});
