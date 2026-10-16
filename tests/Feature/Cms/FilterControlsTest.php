<?php

declare(strict_types=1);

use App\Contracts\Services\LocalizationServiceInterface;
use App\Core\Filters\Contracts\FilterableModule;
use App\Core\Filters\FilterDimension;
use App\Core\Filters\FilterFacets;
use App\Core\Filters\FilterQueryRedirector;
use App\Core\Filters\FilterSet;
use App\Models\Market;
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
    config(['cms.theme' => 'db-cyber', 'cms.admin_theme' => 'dbcms', 'cms.page_cache.enabled' => false]);
});

function controlModule(): FilterableModule
{
    return new class implements FilterableModule
    {
        public function filterModuleKey(): string
        {
            return 'demo';
        }

        public function filterModuleLabel(string $locale): string
        {
            return 'Demo';
        }

        public function filterDimensions(): array
        {
            return [
                FilterDimension::terms('tag', multi: true, order: 10, vocabulary: fn (): array => [
                    'laravel' => ['label' => 'Laravel', 'key' => 1],
                    'vue' => ['label' => 'Vue', 'key' => 2],
                ]),
                FilterDimension::enum('sort', [
                    'recientes' => ['es' => 'Recientes', 'en' => 'Newest'],
                    'antiguos' => ['es' => 'Antiguos', 'en' => 'Oldest'],
                ], order: 20),
                FilterDimension::bool('featured', order: 30),
                FilterDimension::range('price', order: 40, marketCurrency: true),
                FilterDimension::date('date_from', order: 50, bound: 'from'),
                FilterDimension::text('search', order: 60),
            ];
        }
    };
}

test('a filter form submission is canonicalized into a 301', function (array $query, ?string $expected): void {
    $market = Market::where('code', 'es')->firstOrFail();

    $path = app(FilterQueryRedirector::class)->canonicalPath(controlModule(), $market, 'es', $query);

    expect($path)->toBe($expected);
})->with([
    'multi-select terms' => [['tag' => ['laravel', 'vue']], 'laravel+vue'],
    'single value' => [['tag' => 'laravel'], 'laravel'],
    'enum select' => [['sort' => 'recientes'], 'recientes'],
    'flag switch' => [['featured' => '1'], 'destacados'],
    'range min/max' => [['price_min' => '100', 'price_max' => '200'], 'entre-100-200-eur'],
    'range lower only' => [['price_min' => '100'], 'desde-100-eur'],
    'range upper only' => [['price_max' => '200'], 'hasta-200-eur'],
    'compact range' => [['price' => '100-200'], 'entre-100-200-eur'],
    'date' => [['date_from' => '2026-01-01'], 'desde-2026-01-01'],
    'search' => [['search' => 'Mi Término'], 'mi-termino'],
    'combination' => [['tag' => ['laravel'], 'sort' => 'recientes', 'price_max' => '200'], 'laravel/recientes/hasta-200-eur'],
    'nothing to filter' => [['page' => '2'], null],
    'invalid values are ignored' => [['sort' => 'no-such-option'], null],
]);

test('the front filter controls render every declared control type', function (): void {
    $html = (string) $this->get('/es/es/blog')->assertOk()->getContent();

    expect($html)->toContain('<fieldset')
        // Terms: link-based checkboxes/radios, crawlable and marked as pressed.
        ->toContain('aria-pressed')
        // Sort: a real select.
        ->toContain('name="sort"')
        // Dates: date pickers.
        ->toContain('type="date"')
        // Search: a search box.
        ->toContain('type="search"')
        // The form canonicalizes through the shared 301 endpoint.
        ->toContain('<form method="GET"');
});

test('the shop catalogue renders the range and switch controls with their state', function (): void {
    $html = (string) $this->get('/es/es/tienda/entre-0-1000000-eur/destacados')->assertOk()->getContent();

    expect($html)->toContain('name="price_min"')
        ->toContain('name="price_max"')
        ->toContain('value="0"')
        ->toContain('value="1000000"')
        ->toContain('name="featured"')
        ->toContain('checked');
});

test('submitting the filter form redirects 301 to the canonical url', function (): void {
    $this->get('/es/es/tienda?price_min=100&price_max=200&sort=precio-asc')
        ->assertStatus(301)
        ->assertRedirect('/es/es/tienda/entre-100-200-eur/precio-asc');

    $this->get('/es/es/blog?search=mi+termino')
        ->assertStatus(301)
        ->assertRedirect('/es/es/blog/mi-termino');
});

test('active link-based selections travel inside the form as hidden inputs', function (): void {
    $set = new FilterSet('blog', 'es', 'es', ['tag' => ['laravel']]);
    $hidden = app(FilterFacets::class)->hiddenInputs(controlModule(), $set);

    expect($hidden)->toBe(['tag' => ['laravel']]);
});

test('multi term facet links retain existing values and other dimensions', function (): void {
    $module = controlModule();
    $market = Market::query()->where('code', 'es')->sole();
    $localization = app(LocalizationServiceInterface::class);
    $set = new FilterSet('demo', 'es', 'es', ['tag' => ['laravel'], 'sort' => 'recientes']);
    $groups = app(FilterFacets::class)->groups($module, $set, $module->filterDimensions(), $market, $localization);
    $tags = collect($groups)->firstWhere('key', 'tag');
    $vue = collect($tags['values'])->firstWhere('value', 'vue');

    expect($vue['url'])->toBe($localization->url('/demo/laravel+vue/recientes', $market, 'es'));
});
