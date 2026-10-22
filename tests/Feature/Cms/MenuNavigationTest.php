<?php

declare(strict_types=1);

use App\Models\Market;
use App\Models\Menu;
use App\Services\Cms\MenuService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CmsFixtures;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    CmsFixtures::localization(['es', 'en', 'fr']);
    $menu = Menu::create(['slug' => 'main', 'name' => 'Main', 'location' => 'header', 'is_active' => true]);
    $item = $menu->items()->create(['type' => 'custom', 'url' => '/', 'order' => 0, 'is_active' => true]);
    foreach (['es' => 'Inicio', 'en' => 'Home', 'fr' => 'Accueil'] as $locale => $title) {
        $item->translations()->create(['locale' => $locale, 'title' => $title]);
    }
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

test('menu service provides translated titles for english and french', function (): void {
    $service = app(MenuService::class);
    $market = Market::where('code', 'es')->firstOrFail();

    $menuEn = $service->getMenu('header', $market, 'en');
    $menuFr = $service->getMenu('header', $market, 'fr');

    expect($menuEn[0]['title'])->toBe('Home');
    expect($menuFr[0]['title'])->toBe('Accueil');
});
