<?php

declare(strict_types=1);

use App\Models\Language;
use App\Models\Market;
use Database\Seeders\PlatformShowcaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Shop\Models\ProductCategory;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(PlatformShowcaseSeeder::class);

    // These tests mutate data and assert the rendered result immediately, so the
    // public page cache (which is event-invalidated) must stay out of the way.
    config(['cms.page_cache.enabled' => false]);
});

test('shop cart route returns 200 without falling into 404 slug matching', function (): void {
    // 1. Root /shop/cart
    $this->get('/shop/cart')->assertStatus(301)->assertRedirect('/tienda/cart');
    $res1 = $this->followingRedirects()->get('/shop/cart');
    $res1->assertOk()->assertViewHas('items');

    // 2. Localized /es/es/cart
    $res2 = $this->get('/es/es/cart');
    $res2->assertOk();

    // 3. Localized French route in the European market.
    $res3 = $this->get('/eu/fr/cart');
    $res3->assertOk();
});

test('submitting contact form widget via localized market and locale route works', function (): void {
    $response = $this->post('/eu/es/forms/contacto/submit', [
        'name' => 'Maria Garcia',
        'email' => 'maria@example.com',
        'message' => 'Solicitud de información sobre licencias técnicas.',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('form_submissions', [
        'payload->email' => 'maria@example.com',
    ]);
});

test('buy button renders clean arrow entity without html escaping leak', function (): void {
    $response = $this->get('/es/es/tienda');
    $response->assertOk();

    // Must NOT leak raw unescaped entity '&amp;rarr;'
    $response->assertDontSee('&amp;rarr;', false);
    $response->assertSee('&rarr;', false);
});

test('shop category title and description are translated when category is selected', function (): void {
    $category = ProductCategory::first();
    expect($category)->not->toBeNull();

    // Legacy query URL 301s to the canonical friendly category URL.
    $slugEs = $category->translations->firstWhere('locale', 'es')?->slug;

    $this->get('/eu/es/tienda?category='.$category->id)->assertStatus(301);

    // Spanish
    $resEs = $this->followingRedirects()->get('/eu/es/tienda?category='.$category->id);
    $resEs->assertOk();
    $resEs->assertSee((string) $category->localizedName('es'));

    // The canonical friendly URL renders the same category.
    if ($slugEs) {
        $this->get('/eu/es/tienda/'.$slugEs)->assertOk();
    }
});

test('homepage sections translate their content across locales', function (): void {
    // In Spanish
    $resEs = $this->get('/eu/es');
    $resEs->assertOk();
    $resEs->assertSee('Lo que hace la plataforma');

    // In English
    $resEn = $this->get('/eu/en');
    $resEn->assertOk();
    $resEn->assertSee('What the platform does');

    // In French
    $resFr = $this->get('/eu/fr');
    $resFr->assertOk();
    $resFr->assertSee('Ce que fait la plateforme');
});

test('visitor geo modal dynamically switches to language preferences when only 1 market exists', function (): void {
    // Leave a single active market with several languages.
    Market::where('code', '!=', 'eu')->update(['is_active' => false]);

    $response = $this->get('/eu/es');
    $response->assertOk();
    $response->assertSee('Preferencias de Idioma');
    $response->assertDontSee('Mercado / País');

    // If only 1 market and 1 language exist, modal must NOT be shown
    Language::where('code', '!=', 'es')->update(['is_active' => false]);
    DB::table('market_language')
        ->where('language_id', '!=', Language::where('code', 'es')->first()->id)
        ->delete();

    $responseSingle = $this->get('/eu/es/');
    $responseSingle->assertOk();
    $responseSingle->assertDontSee('Preferencias de Idioma');
    $responseSingle->assertDontSee('Preferencias de Región');
});
