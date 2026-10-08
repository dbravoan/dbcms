<?php

declare(strict_types=1);

use App\Models\Market;
use App\Services\Cms\MenuService;
use Database\Seeders\PlatformShowcaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(PlatformShowcaseSeeder::class);
});

test('menu service resolves header navigation for active market and locale', function (): void {
    $service = app(MenuService::class);
    $market = Market::where('code', 'es')->firstOrFail();

    $menu = $service->getMenu('header', $market, 'es');

    expect($menu)->toBeArray();
    expect($menu)->not->toBeEmpty();
    expect($menu[0])->toHaveKeys(['id', 'title', 'url', 'target_blank', 'children']);
    expect($menu[0]['title'])->toBe('Inicio');
});

test('menu service provides translated titles for english and catalan', function (): void {
    $service = app(MenuService::class);
    $market = Market::where('code', 'es')->firstOrFail();

    $menuEn = $service->getMenu('header', $market, 'en');
    $menuFr = $service->getMenu('header', $market, 'fr');

    expect($menuEn[0]['title'])->toBe('Home');
    expect($menuFr[0]['title'])->toBe('Accueil');
});
