<?php

declare(strict_types=1);

use App\Contracts\Services\BrandServiceInterface;
use App\Contracts\Services\GeneralSettingsServiceInterface;
use App\Services\Cms\FrontViewResolver;
use App\Services\Cms\PageCacheService;
use App\Services\Cms\ThemeManagerService;
use Illuminate\Support\Facades\View;

beforeEach(function (): void {
    if (! is_array(config('cms.project'))) {
        $this->markTestSkipped('This contract validates a physically initialized derived project.');
    }
});

it('resolves the installed administrative brand and every selected frontend', function (): void {
    $profile = config('cms.project');
    $manager = app(ThemeManagerService::class);
    expect(app(BrandServiceInterface::class)->activeTheme())->toBe($profile['brand']);
    expect(app(BrandServiceInterface::class)->current()->valid)->toBeTrue();
    $available = array_keys($manager->getAvailableThemes());
    $selected = $profile['themes'];
    sort($available);
    sort($selected);
    expect($available)->toBe($selected);
    foreach ($profile['themes'] as $theme) {
        $manager->setActiveTheme($theme);
        expect(app(FrontViewResolver::class)->layout())->toBe('themes.'.$theme.'.layouts.app');
        expect(View::exists('themes.'.$theme.'.layouts.app'))->toBeTrue();
        expect(cms_asset_entries(true))->toContain('themes/'.$theme.'/theme.css');
        foreach (array_diff($profile['themes'], [$theme]) as $other) {
            expect(cms_asset_entries(true))->not->toContain('themes/'.$other.'/theme.css');
        }
    }
});

it('retains selected activation over stale configuration and invalidates page caches', function (): void {
    $profile = config('cms.project');
    $theme = $profile['themes'][array_key_last($profile['themes'])];
    $cache = app(PageCacheService::class);
    $previous = $cache->version();
    app(ThemeManagerService::class)->setActiveTheme($theme);
    config(['cms.theme' => 'uninstalled-theme']);
    app(GeneralSettingsServiceInterface::class)->flushCache();
    expect(app(ThemeManagerService::class)->getActiveTheme())->toBe($theme);
    expect($cache->version())->toBeGreaterThan($previous);
});
