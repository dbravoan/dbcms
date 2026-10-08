<?php

declare(strict_types=1);

use App\Core\Modules\ModuleManager;
use App\Models\CmsModule;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\CmsFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(CmsFoundationSeeder::class);
    $this->manager = app(ModuleManager::class);
    $this->manager->discoverModules();

    // Create admin user with full role
    $this->user = User::create([
        'name' => 'Super Admin',
        'email' => 'admin@test.local',
        'password' => bcrypt('password'),
    ]);
    $adminRole = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
    $this->user->roles()->sync([$adminRole->id]);

    // Deactivate ALL modules to simulate clean zero-module state
    $modules = CmsModule::all();
    foreach ($modules as $mod) {
        $this->manager->deactivate($mod->identifier);
    }
});

test('clean install with zero active modules renders welcome-cms on root route', function (): void {
    $response = $this->get('/');

    $response->assertStatus(200)
        ->assertViewIs('welcome-cms')
        ->assertSee('El núcleo base está listo.')
        ->assertSee('INSTALACIÓN LIMPIA AUTÓNOMA');
});

test('clean install renders welcome-cms on localized routes when pages module is disabled', function (): void {
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

    $acting->get('/admin/modules')->assertStatus(200);
    $acting->get('/admin/users')->assertStatus(200);
    $acting->get('/admin/media')->assertStatus(200);
    $acting->get('/admin/menus')->assertStatus(200);
    $acting->get('/admin/markets')->assertStatus(200);
    $acting->get('/admin/settings')->assertStatus(200);
    $acting->get('/admin/seo/audit')->assertStatus(200);
    $acting->get('/admin/tokens')->assertStatus(200);
});

test('module routes return 404 when modules are disabled', function (): void {
    $acting = $this->actingAs($this->user, 'web');

    $acting->get('/admin/blog/posts')->assertStatus(404);
    $acting->get('/admin/shop/products')->assertStatus(404);
    $acting->get('/admin/forms')->assertStatus(404);
    $acting->get('/admin/pages')->assertStatus(404);
    $acting->get('/admin/galleries')->assertStatus(404);
});
