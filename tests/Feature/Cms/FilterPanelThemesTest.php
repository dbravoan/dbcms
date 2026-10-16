<?php

declare(strict_types=1);

use App\Core\Filters\FilterPanelSkin;
use App\Services\Cms\FrontViewResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Shop\Models\Product;
use Tests\Support\BlogShopComposition;
use Tests\Support\CmsFixtures;
use Tests\Support\FilterFixtures;

uses(RefreshDatabase::class, BlogShopComposition::class);

beforeEach(function (): void {
    CmsFixtures::localization(['es', 'en'], ['es', 'us']);
    CmsFixtures::identities();
    FilterFixtures::blog();
    FilterFixtures::shop();
    Product::query()->update(['is_featured' => true]);
    config(['cms.admin_theme' => 'dbcms', 'cms.page_cache.enabled' => false]);
});

/**
 * Every front theme must dress the filter panel in its own visual language while
 * keeping the same functional contract: crawlable toggle links, a canonicalizing
 * form, mobile collapse without JavaScript and an accessible structure.
 */
dataset('front_themes', [
    'cyber' => ['db-cyber', ['data-filter-panel="db-cyber"', 'font-mono', '#00FF99']],
    'brutalist' => ['db-brutalist', ['data-filter-panel="db-brutalist"', 'border-2', 'shadow-[6px_6px_0_0_#000]']],
    'editorial' => ['db-editorial', ['data-filter-panel="db-editorial"', 'font-serif', 'divide-y']],
    'enterprise' => ['db-enterprise', ['data-filter-panel="db-enterprise"', 'backdrop-blur', 'indigo']],
    'minimal' => ['db-minimal', ['data-filter-panel="db-minimal"', 'rounded-full', 'tracking-[0.24em]']],
    'sunset' => ['db-sunset', ['data-filter-panel="db-sunset"', 'from-rose-500', 'backdrop-blur']],
    'clean' => ['default-clean', ['data-filter-panel="default-clean"', 'rounded-xl', 'border-slate-200']],
]);

test('the filter panel keeps its functional and accessible contract in every theme', function (string $theme): void {
    config(['cms.theme' => $theme]);

    $html = (string) $this->get('/es/es/blog')->assertOk()->getContent();

    expect($html)
        // Structure: labelled groups, canonicalizing form, mobile collapse.
        ->toContain('<fieldset')
        ->toContain('<legend')
        ->toContain('<form method="GET"')
        ->toContain('data-filter-form')
        ->toContain('data-filter-collapsible')
        ->toContain('sticky bottom-0')
        // Options are crawlable toggle links with an accessible pressed state.
        ->toContain('aria-pressed=')
        // Free-form controls stay usable without JavaScript (blog dimensions).
        ->toContain('name="sort"')
        ->toContain('type="search"')
        ->toContain('type="date"')
        // …and auto-submit through the CSP-safe data attribute, never an inline handler.
        ->toContain('data-auto-submit')
        ->not->toContain('onchange=')
        ->not->toContain('onclick=')
        // Emojis aside, the accessible bits are present.
        ->toContain('sr-only');
})->with('front_themes');

test('the shop panel exposes the range, select and switch controls in every theme', function (string $theme): void {
    config(['cms.theme' => $theme]);

    $html = (string) $this->get('/es/es/tienda')->assertOk()->getContent();

    expect($html)
        ->toContain('data-filter-panel="'.$theme.'"')
        ->toContain('name="price_min"')
        ->toContain('name="price_max"')
        ->toContain('name="sort"')
        ->toContain('name="featured"')
        ->toContain('data-auto-submit');
})->with('front_themes');

test('a theme without a skin file falls back to the neutral defaults', function (): void {
    $skin = app(FilterPanelSkin::class)->forTheme('theme-that-does-not-exist');

    expect($skin)->toHaveKey('panel')
        ->and($skin['panel'])->toBe(config('front_filter_ui.default.panel'));
});

test('the panel resolves the theme override before the shared implementation', function (): void {
    expect(app(FrontViewResolver::class)->filterPanel())->toBe('components.cms-filter.panel');
});
