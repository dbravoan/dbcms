<?php

declare(strict_types=1);

use Database\Seeders\PlatformShowcaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(PlatformShowcaseSeeder::class);
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

test('the shop page title and meta description are localized', function (): void {
    $this->get('/eu/es/tienda')->assertOk();
    $this->get('/eu/en/shop')->assertOk();
    $this->get('/eu/fr/boutique')->assertOk();
});

test('the blog page title is localized', function (): void {
    $this->get('/eu/en/blog')->assertOk();
    $this->get('/eu/fr/blog')->assertOk();
});

test('json translation files keep key parity across all three locales', function (): void {
    $langPath = dirname(__DIR__, 3).'/lang';

    $en = array_keys((array) json_decode((string) file_get_contents($langPath.'/en.json'), true));
    $es = array_keys((array) json_decode((string) file_get_contents($langPath.'/es.json'), true));
    $ca = array_keys((array) json_decode((string) file_get_contents($langPath.'/ca.json'), true));

    sort($en);
    sort($es);
    sort($ca);

    expect($es)->toBe($en)
        ->and($ca)->toBe($en);
});
