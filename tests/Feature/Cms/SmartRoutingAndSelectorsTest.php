<?php

declare(strict_types=1);

use App\Contracts\Services\LocalizationServiceInterface;
use App\Models\Language;
use App\Models\Market;
use Database\Seeders\PlatformShowcaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Blog\Models\Post;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(PlatformShowcaseSeeder::class);
});

test('smart routing generates correct URLs based on active markets and languages', function (): void {
    /** @var LocalizationServiceInterface $localization */
    $localization = app(LocalizationServiceInterface::class);

    $marketEs = Market::where('code', 'es')->first();
    $langEs = Language::where('code', 'es')->first();

    // In multi-market mode (default with seed)
    expect($localization->hasMultipleMarkets())->toBeTrue();
    $url = $localization->url('/blog', $marketEs, $langEs);
    expect($url)->toContain('/es/es/blog');
});

test('single market and single language omits prefixes in url generation', function (): void {
    // Disable all markets except 1 and all languages except 1
    Market::where('code', '!=', 'es')->update(['is_active' => false]);
    Language::where('code', '!=', 'es')->update(['is_active' => false]);

    /** @var LocalizationServiceInterface $localization */
    $localization = app(LocalizationServiceInterface::class);
    $marketEs = Market::where('code', 'es')->first();
    $langEs = Language::where('code', 'es')->first();

    expect($localization->hasMultipleMarkets())->toBeFalse();
    expect($localization->hasMultipleLanguages($marketEs))->toBeFalse();

    $url = $localization->url('/blog', $marketEs, $langEs);
    expect($url)->toBe(url('/blog'));

    $homeUrl = $localization->url('/', $marketEs, $langEs);
    expect($homeUrl)->toBe(url('/'));
});

test('single market and multiple languages includes language prefix but omits market', function (): void {
    // Disable other markets, keep multiple languages
    Market::where('code', '!=', 'es')->update(['is_active' => false]);

    /** @var LocalizationServiceInterface $localization */
    $localization = app(LocalizationServiceInterface::class);
    $marketEs = Market::where('code', 'es')->first();
    $langEn = Language::where('code', 'en')->first();

    expect($localization->hasMultipleMarkets())->toBeFalse();
    expect($localization->hasMultipleLanguages($marketEs))->toBeTrue();

    $url = $localization->url('/blog', $marketEs, $langEn);
    expect($url)->toBe(url('/en/blog'));
});

test('blog post view renders canonical and multi-directional alternate hreflangs in head', function (): void {
    $post = Post::published()->first();
    $transEs = $post->translations->where('locale', 'es')->first();

    $response = $this->get("/es/es/blog/{$transEs->slug}");
    $response->assertOk();
    $response->assertSee('<link rel="canonical"', false);
    $response->assertSee('rel="alternate" hreflang="es-ES"', false);
    $response->assertSee('rel="alternate" hreflang="en-US"', false);
});

test('language switcher links are semantic HTML anchors pointing directly to alternate translated URLs', function (): void {
    $post = Post::published()->first();
    $transEs = $post->translations->where('locale', 'es')->first();
    $transEn = $post->translations->where('locale', 'en')->first();

    $response = $this->get("/es/es/blog/{$transEs->slug}");
    $response->assertOk();

    // Verify anchor links in switcher contain direct translated URLs (not javascript: or #)
    $response->assertSee("/us/en/blog/{$transEn->slug}", false);
});
