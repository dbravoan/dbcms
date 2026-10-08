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
    $this->user = User::create([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => bcrypt('password'),
    ]);
    $adminRole = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
    $this->user->roles()->sync([$adminRole->id]);
});

test('pages module is marked as core trunk module in manager and database', function (): void {
    $this->manager->discoverModules();

    expect($this->manager->isCore('pages'))->toBeTrue();

    $pageModule = CmsModule::where('identifier', 'pages')->first();
    expect($pageModule)->not->toBeNull()
        ->and($pageModule->is_core)->toBeTrue();
});

test('pages module cannot be uninstalled via admin post controller', function (): void {
    $this->manager->discoverModules();

    $response = $this->actingAs($this->user, 'web')
        ->from('/admin/modules')
        ->post('/admin/modules/pages/uninstall');

    $response->assertRedirect('/admin/modules');
    $response->assertSessionHas('error', "El módulo 'pages' es un módulo troncal esencial del sistema y no puede ser desinstalado.");

    // Ensure it remains installed
    $module = CmsModule::where('identifier', 'pages')->first();
    expect($module->is_installed)->toBeTrue();
});

test('admin modules index renders trunk core badge and prevents uninstall action for pages', function (): void {
    $this->manager->discoverModules();

    $response = $this->actingAs($this->user, 'web')
        ->get('/admin/modules');

    $response->assertStatus(200)
        ->assertSee('TRONCAL / CORE')
        ->assertSee('Troncal');
});

test('deactivation impact returns core module warnings for pages', function (): void {
    $this->manager->discoverModules();

    $impact = $this->manager->getDeactivationImpact('pages');

    expect($impact['identifier'])->toBe('pages')
        ->and($impact['is_core'])->toBeTrue()
        ->and($impact['core_warning'])->toContain('módulo troncal');
});
