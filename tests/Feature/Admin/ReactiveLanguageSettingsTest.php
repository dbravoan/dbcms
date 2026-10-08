<?php

declare(strict_types=1);

use App\Contracts\Services\LocalizationServiceInterface;
use App\Models\Language;
use App\Models\Menu;
use App\Models\MenuItemTranslation;
use App\Models\User;
use Database\Seeders\PlatformShowcaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(PlatformShowcaseSeeder::class);
});

test('menu items accept a title for every active language, including a new one', function (): void {
    Language::firstOrCreate(['code' => 'fr'], [
        'name' => 'French', 'native_name' => 'Français', 'direction' => 'ltr', 'is_active' => true,
    ]);

    $menu = Menu::firstOrFail();
    $admin = User::first();

    $this->actingAs($admin, 'web')
        ->post(route('admin.menus.store-item', $menu), [
            'translations' => [
                'es' => ['title' => 'Inicio ES'],
                'fr' => ['title' => 'Accueil FR'],
            ],
            'url' => '/',
            'type' => 'custom',
        ])
        ->assertRedirect(route('admin.menus.index'));

    $item = $menu->items()->latest('id')->firstOrFail();
    $titles = MenuItemTranslation::where('menu_item_id', $item->id)->pluck('title', 'locale')->all();

    expect($titles)->toHaveKey('es')
        ->and($titles['es'])->toBe('Inicio ES')
        ->and($titles)->toHaveKey('fr')
        ->and($titles['fr'])->toBe('Accueil FR');
});

test('the menus screen shows a language tab per active language', function (): void {
    Language::firstOrCreate(['code' => 'fr'], [
        'name' => 'French', 'native_name' => 'Français', 'direction' => 'ltr', 'is_active' => true,
    ]);

    $admin = User::first();
    $response = $this->actingAs($admin, 'web')->get(route('admin.menus.index'));

    $response->assertOk();

    // One tab + one title input per active language (es/en from the demo seeder
    // plus the French created here).
    foreach (app(LocalizationServiceInterface::class)->activeLocales() as $code) {
        $response->assertSee("itemLocale = '{$code}'", false);
        $response->assertSee("[title]'", false);
    }

    $response->assertSee('French', false);
});

test('the registry is reactive: a new active language shows up immediately', function (): void {
    $localization = app(LocalizationServiceInterface::class);
    $before = $localization->activeLocales();

    expect($before)->not->toContain('sv');

    // No manual cache invalidation: the model write does it.
    Language::firstOrCreate(['code' => 'sv'], [
        'name' => 'Swedish', 'native_name' => 'Svenska', 'direction' => 'ltr', 'is_active' => true,
    ]);

    expect($localization->activeLocales())->toContain('sv');
});

test('deactivating a language removes it from every reactive surface', function (): void {
    $fr = Language::firstOrCreate(['code' => 'fr'], [
        'name' => 'French', 'native_name' => 'Français', 'direction' => 'ltr', 'is_active' => true,
    ]);

    $localization = app(LocalizationServiceInterface::class);
    expect($localization->activeLocales())->toContain('fr');

    $fr->update(['is_active' => false]);

    expect($localization->activeLocales())->not->toContain('fr');
});
