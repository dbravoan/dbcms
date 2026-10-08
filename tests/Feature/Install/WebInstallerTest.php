<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\PlatformShowcaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Support\DirectoryFixture;

uses(RefreshDatabase::class);

$originalInstalledLock = null;

beforeEach(function () use (&$originalInstalledLock): void {
    $this->seed(PlatformShowcaseSeeder::class);
    // Temporarily remove installed lock for installer tests
    if (File::exists(storage_path('installed'))) {
        $originalInstalledLock = (string) File::get(storage_path('installed'));
        File::delete(storage_path('installed'));
    }
});

afterEach(function () use (&$originalInstalledLock): void {
    // Restore installed lock after tests
    if ($originalInstalledLock !== null) {
        File::put(storage_path('installed'), $originalInstalledLock);
    } else {
        File::put(storage_path('installed'), (string) json_encode([
            'installed_at' => now()->toIso8601String(),
            'admin_email' => 'admin@db-cms.io',
            'admin_path' => 'admin',
            'version' => '1.0.0',
            'platform' => 'dbCMS Enterprise',
        ], JSON_PRETTY_PRINT));
    }
});

test('installer step 1 displays onboarding explanation and server check', function (): void {
    $response = $this->get(route('install.step1'));

    $response->assertStatus(200);
    $response->assertSee('dbCMS');
    $response->assertSee('¿Qué es dbCMS');
    $response->assertSee('Multi-mercado real');
    $response->assertSee('Protocolo MCP nativo');
    $response->assertSee('Módulos PHP del Servidor');
});

test('installer step 2 tests database connection via ajax with auto-create support', function (): void {
    $response = $this->postJson(route('install.test-db'), [
        'host' => env('DB_HOST', 'mysql'),
        'port' => (string) env('DB_PORT', '3306'),
        'database' => 'testing',
        'username' => env('DB_USERNAME', 'sail'),
        'password' => env('DB_PASSWORD', 'secret'),
        'auto_create' => true,
    ]);

    $response->assertStatus(200);
    $response->assertJson(['success' => true]);
});

test('installer step 2 saves database credentials and redirects to step 3', function (): void {
    $response = $this->post(route('install.save-db'), [
        'host' => env('DB_HOST', 'mysql'),
        'port' => (string) env('DB_PORT', '3306'),
        'database' => 'testing',
        'username' => env('DB_USERNAME', 'sail'),
        'password' => env('DB_PASSWORD', 'secret'),
    ]);

    $response->assertRedirect(route('install.step3'));
});

test('installer step 3 runs provisioning migrations and seeds cleanly', function (): void {
    $response = $this->postJson(route('install.run-migrations'), [
        'with_demo' => false,
    ]);

    $response->assertStatus(200);
    $response->assertJson(['success' => true]);
    $response->assertJsonStructure(['success', 'message', 'logs']);
});

test('installer step 4 creates primary admin, custom admin slug and seals installation lock', function (): void {
    $response = $this->post(route('install.store-admin'), [
        'name' => 'Installer Admin',
        'email' => 'master@db-cms.io',
        'password' => 'secret12345',
        'password_confirmation' => 'secret12345',
        'site_name' => 'dbCMS Test Platform',
        'admin_path' => 'dbadmin',
    ]);

    $response->assertRedirect(route('install.step5'));

    expect(User::where('email', 'master@db-cms.io')->exists())->toBeTrue();
    expect(File::exists(storage_path('installed')))->toBeTrue();
});

test('installer step 4 exposes the brand and front theme selectors', function (): void {
    $response = $this->get(route('install.step4'));

    $response->assertStatus(200);
    $response->assertSee('Marca del CMS', false);
    $response->assertSee('name="brand"', false);
    $response->assertSee('name="front_theme"', false);
    // Brands discovered from disk, not hardcoded.
    $response->assertSee('value="dbcms"', false);
    $response->assertSee('value="termocms"', false);
});

test('installer auto-selects a single brand without showing a selector', function (): void {
    // Isolate a single backoffice theme for the request.
    $hidden = resource_path('views/themes/admin/termocms');
    $moved = resource_path('views/themes/admin/.termocms-hidden');
    DirectoryFixture::hide($hidden, $moved, function (): void {
        $response = $this->get(route('install.step4'));

        $response->assertStatus(200);
        // No radio selector, but the single brand is still submitted.
        $response->assertDontSee('name="brand" value="dbcms" type="radio"', false);
        $response->assertSee('type="hidden" name="brand" value="dbcms"', false);
        $response->assertSee('Única marca', false);
    });
});

test('installer accepts submission without a brand when only one is available', function (): void {
    $hidden = resource_path('views/themes/admin/termocms');
    $moved = resource_path('views/themes/admin/.termocms-hidden');
    DirectoryFixture::hide($hidden, $moved, function (): void {
        $response = $this->post(route('install.store-admin'), [
            'name' => 'Single Brand Admin',
            'email' => 'single-admin@db-cms.io',
            'password' => 'secret12345',
            'password_confirmation' => 'secret12345',
            'site_name' => 'Single Brand',
            'admin_path' => 'panel',
        ]);

        $response->assertRedirect(route('install.step5'));

        $lock = json_decode((string) File::get(storage_path('installed')), true);
        expect($lock['brand'])->toBe('dbcms');
    });
});

test('installer persists the selected brand and front theme in the install lock', function (): void {
    $response = $this->post(route('install.store-admin'), [
        'name' => 'Brand Admin',
        'email' => 'brand-admin@db-cms.io',
        'password' => 'secret12345',
        'password_confirmation' => 'secret12345',
        'site_name' => 'Corporate Platform',
        'admin_path' => 'panel',
        'brand' => 'termocms',
        'front_theme' => 'db-cyber',
    ]);

    $response->assertRedirect(route('install.step5'));
    $response->assertSessionHas('install.brand', 'termocms');

    $lock = json_decode((string) File::get(storage_path('installed')), true);
    expect($lock['brand'])->toBe('termocms');
    expect($lock['front_theme'])->toBe('db-cyber');
});

test('installer rejects an unknown brand', function (): void {
    $response = $this->post(route('install.store-admin'), [
        'name' => 'Bad Brand',
        'email' => 'bad-brand@db-cms.io',
        'password' => 'secret12345',
        'password_confirmation' => 'secret12345',
        'site_name' => 'Bad Brand',
        'admin_path' => 'panel',
        'brand' => 'not-a-real-brand',
    ]);

    $response->assertSessionHasErrors('brand');
});

test('installer step 5 displays completed launchpad screen', function (): void {
    $response = $this->get(route('install.step5'));

    $response->assertStatus(200);
    $response->assertSee('¡dbCMS ha quedado instalado con éxito!');
    $response->assertSee('Panel de Administración');
    $response->assertSee('Portada del Sitio');
    $response->assertSee('Protocolo MCP');
});
