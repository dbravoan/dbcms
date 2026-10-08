<?php

declare(strict_types=1);

use App\Models\CmsModule;
use App\Services\Cms\CookieConsentService;
use Database\Seeders\PlatformShowcaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(PlatformShowcaseSeeder::class);
});

test('cookie consent service discovers active module categories dynamically', function (): void {
    $service = app(CookieConsentService::class);

    $categories = $service->getDisclosedCategories('es');

    // Necessary category is always present
    expect($categories)->toHaveKey('necessary');
    expect($categories['necessary']['required'])->toBeTrue();

    // Shop module is active in demo seeder -> functional category present
    expect($categories)->toHaveKey('functional');
    expect($categories['functional']['required'])->toBeFalse();

    // Analytics module is active -> analytics category present
    expect($categories)->toHaveKey('analytics');
    expect($categories['analytics']['required'])->toBeFalse();
    expect($categories['analytics']['cookies'])->toBeArray();

    // If analytics module is deactivated, category disappears
    CmsModule::where('identifier', 'analytics')->update(['is_active' => false]);
    $categoriesWithoutAnalytics = $service->getDisclosedCategories('es');
    expect($categoriesWithoutAnalytics)->not->toHaveKey('analytics');
});

test('cookie consent service correctly checks consent for categories', function (): void {
    $service = app(CookieConsentService::class);

    // Necessary is always allowed
    expect($service->hasConsent('necessary'))->toBeTrue();
    expect($service->hasConsent('technical'))->toBeTrue();

    // With request containing JSON consent cookie
    $request = Request::create('/es/es/', 'GET', [], [
        'cms_cookie_consent' => json_encode([
            'necessary' => true,
            'analytics' => true,
            'functional' => false,
        ]),
    ]);

    expect($service->hasConsent('necessary', $request))->toBeTrue();
    expect($service->hasConsent('analytics', $request))->toBeTrue();
    expect($service->hasConsent('functional', $request))->toBeFalse();
    expect($service->hasConsent('marketing', $request))->toBeFalse();

    $prefs = $service->getConsentPreferences($request);
    expect($prefs['necessary'])->toBeTrue();
    expect($prefs['analytics'])->toBeTrue();
    expect($prefs['functional'])->toBeFalse();
});

test('frontoffice layout includes cookie consent banner and modal component', function (): void {
    $response = $this->get('/es/es/');
    $response->assertOk();

    // Check presence of cookie consent banner and GDPR preference modal
    $response->assertSee('Privacidad & Preferencias de Cookies');
    $response->assertSee('Aceptar Todas');
    $response->assertSee('Solo Necesarias');
    $response->assertSee('Personalizar');
    $response->assertSee('Centro de Preferencias de Cookies RGPD');
    $response->assertSee('XSRF-TOKEN');
});

test('frontoffice layout translates cookie banner in French and English', function (): void {
    // In French
    $resFr = $this->get('/eu/fr');
    $resFr->assertOk();
    $resFr->assertSee('Confidentialité & préférences de cookies');
    $resFr->assertSee('Tout accepter');
    $resFr->assertSee('Nécessaires uniquement');

    // In English
    $resEn = $this->get('/us/en/');
    $resEn->assertOk();
    $resEn->assertSee('Privacy & Cookie Preferences');
    $resEn->assertSee('Accept All');
    $resEn->assertSee('Necessary Only');
});

test('returning visitor with configured cookie consent does not receive the floating banner but can access preference modal', function (): void {
    $response = $this->withCookie('cms_cookie_consent', json_encode(['necessary' => true, 'analytics' => true]))
        ->get('/es/es/');

    $response->assertOk();
    $response->assertDontSee('Privacidad & Preferencias de Cookies');
    $response->assertDontSee('Personalizar');
    $response->assertSee('Centro de Preferencias de Cookies RGPD');
    $response->assertSee('Guardar Preferencias');
});

test('footer translations render localized headings, links, and cookie triggers across locales', function (): void {
    // Catalan
    $resFr = $this->get('/eu/fr');
    $resFr->assertOk();
    $resFr->assertSee('Navigation rapide');
    $resFr->assertSee('Mentions légales');
    $resFr->assertSee('Paramètres des cookies');
    $resFr->assertSee('Tous droits réservés.');
    $resFr->assertSee('Marché actif :');

    // English
    $resEn = $this->get('/us/en/');
    $resEn->assertOk();
    $resEn->assertSee('Quick Navigation');
    $resEn->assertSee('Legal & Transparency');
    $resEn->assertSee('Cookie Settings');
    $resEn->assertSee('All rights reserved.');
    $resEn->assertSee('Active Market:');

    // Spanish
    $resEs = $this->get('/es/es/');
    $resEs->assertOk();
    $resEs->assertSee('Navegación Rápida');
    $resEs->assertSee('Legal & Transparencia');
    $resEs->assertSee('Configuración de Cookies');
    $resEs->assertSee('Todos los derechos reservados.');
    $resEs->assertSee('Mercado Activo:');
});
