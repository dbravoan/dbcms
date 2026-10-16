<?php

declare(strict_types=1);

use App\Models\Market;
use App\Models\Menu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BlogShopComposition;
use Tests\Support\CmsFixtures;

uses(RefreshDatabase::class, BlogShopComposition::class);

beforeEach(function (): void {
    CmsFixtures::localization(['es', 'en', 'fr'], ['eu']);
    CmsFixtures::home(['es', 'en', 'fr'], ['eu']);
    $menu = Menu::create(['slug' => 'header', 'name' => 'Header', 'location' => 'header', 'is_active' => true]);
    $menu->markets()->sync(Market::pluck('id')->all());
    foreach ([['es' => 'Inicio', 'en' => 'Home', 'fr' => 'Accueil'], ['es' => 'Módulos', 'en' => 'Modules', 'fr' => 'Modules'], ['es' => 'Contacto', 'en' => 'Contact', 'fr' => 'Contact']] as $order => $titles) {
        $item = $menu->items()->create(['type' => 'custom', 'url' => '/item-'.$order, 'order' => $order, 'is_active' => true]);
        foreach ($titles as $locale => $title) {
            $item->translations()->create(['locale' => $locale, 'title' => $title]);
        }
    }
});

test('the public home renders the header chrome in the market locale', function (string $locale, array $expected, array $forbidden): void {
    $response = $this->get("/eu/{$locale}");

    $response->assertOk();

    foreach ($expected as $needle) {
        $response->assertSee($needle, false);
    }

    foreach ($forbidden as $needle) {
        $response->assertDontSee($needle, false);
    }
})->with([
    'spanish' => ['es', ['Inicio', 'Módulos', 'Contacto'], ['Home', 'Modules']],
    'english' => ['en', ['Home', 'Modules', 'Contact'], ['Inicio', 'Módulos']],
    'french' => ['fr', ['Accueil', 'Modules'], ['Módulos', 'Inicio']],
]);

test('the localized shop routes render in the requested locale', function (): void {
    $this->get('/eu/es/tienda')->assertOk()->assertSee('lang="es"', false);
    $this->get('/eu/en/shop')->assertOk()->assertSee('lang="en"', false);
    $this->get('/eu/fr/boutique')->assertOk()->assertSee('lang="fr"', false);
});

test('the blog selects the requested locale and translated English heading', function (): void {
    $this->get('/eu/en/blog')->assertOk()->assertSee('lang="en"', false)->assertSee('Articles & Publications');
    $this->get('/eu/fr/blog')->assertOk()->assertSee('lang="fr"', false);
});
