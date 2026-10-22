<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CmsFixtures;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    CmsFixtures::localization(['es']);
    CmsFixtures::identities();
    $this->user = User::where('email', 'admin@db-cms.io')->firstOrFail();
});

test('clean install with zero active modules renders welcome-cms on root route', function (): void {
    config()->set('cms.version', '3.4.5');
    $response = $this->get('/');

    $response->assertStatus(200)
        ->assertViewIs('welcome-cms')
        ->assertSee('El núcleo base está listo.')
        ->assertSee('CORE v3.4.5')
        ->assertSee('INSTALACIÓN LIMPIA AUTÓNOMA');
});

test('an empty core installation serves welcome on localized routes and rejects missing pages', function (): void {
    $responseLocale = $this->get('/es');
    $responseLocale->assertStatus(200)->assertViewIs('welcome-cms');

    $responseMarketLocale = $this->get('/es/es');
    $responseMarketLocale->assertStatus(200)->assertViewIs('welcome-cms');

    $this->get('/es/es/hidden-page')->assertNotFound();
    $this->get('/es/hidden-page')->assertNotFound();
});

test('clean install backoffice dashboard renders cleanly with zero module errors', function (): void {
    // Direct dashboard route (/admin)
    $response = $this->actingAs($this->user, 'web')->get('/admin');

    $response->assertStatus(200)
        ->assertSee('Modo Clean Install')
        ->assertSee('Núcleo Base Operativo')
        ->assertSee('Explorar Gestor de Módulos');

    // Dashboard redirect alias (/admin/dashboard -> /admin)
    $this->actingAs($this->user, 'web')
        ->get('/admin/dashboard')
        ->assertRedirect(route('admin.dashboard'));
});

test('core backoffice sections remain fully functional in zero-module state', function (): void {
    $acting = $this->actingAs($this->user, 'web');

    $acting->get('/admin/pages')->assertStatus(200);
    $acting->get('/admin/pages/create')->assertStatus(200);
    $acting->get('/admin/modules')->assertStatus(200);
    $acting->get('/admin/users')->assertStatus(200);
    $acting->get('/admin/media')->assertStatus(200);
    $acting->get('/admin/menus')->assertStatus(200);
    $acting->get('/admin/markets')->assertStatus(200);
    $acting->get('/admin/settings')->assertStatus(200);
    $acting->get('/admin/seo/audit')->assertStatus(200);
    $acting->get('/admin/tokens')->assertStatus(200);
});

test('optional module routes are unavailable in the core only composition', function (): void {
    $acting = $this->actingAs($this->user, 'web');

    $acting->get('/admin/blog/posts')->assertStatus(404);
    $acting->get('/admin/shop/products')->assertStatus(404);
    $acting->get('/admin/forms')->assertStatus(404);
    $acting->get('/admin/galleries')->assertStatus(404);
});
