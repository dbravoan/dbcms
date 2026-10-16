<?php

declare(strict_types=1);

namespace Tests\Feature\Mcp;

use App\Core\Modules\ModuleManager;
use App\Models\CmsModule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Tests\Support\McpCoreComposition;
use Tests\Support\McpFixtures;

uses(RefreshDatabase::class, McpCoreComposition::class);

beforeEach(function (): void {
    McpFixtures::identities();
    app(ClientRepository::class)->createPersonalAccessGrantClient('Testing Personal Client');
});

test('mcp module is discovered and loaded in module manager', function (): void {
    $manager = app(ModuleManager::class);
    $discovered = $manager->discoverModules();

    expect($discovered)->toHaveKey('mcp');
    expect($discovered['mcp']['provider'])->toBe('Modules\\Mcp\\McpModuleServiceProvider');
    expect($discovered['mcp']['settings'])->toHaveKeys(['is_enabled', 'enable_sse', 'rate_limit_per_minute']);
});

test('admin can access mcp module settings and view external agent guide for opencode and cursor', function (): void {
    $admin = User::first();
    $this->actingAs($admin, 'web');

    $manager = app(ModuleManager::class);
    $manager->discoverModules();

    $response = $this->get(route('admin.modules.settings', ['identifier' => 'mcp']));
    $response->assertStatus(200);
    $response->assertSee('Servidor MCP');
    $response->assertSee('Autenticación Exclusiva con Laravel Passport');
    $response->assertSee('OpenCode (Recomendado)');
    $response->assertSee('opencode.json');
    $response->assertSee('Cursor IDE');
    $response->assertSee('.cursor/mcp.json');
    $response->assertSee('1-Clic: Emitir Token Passport');
});

test('admin can generate personal access token directly from mcp settings with 1-click', function (): void {
    $admin = User::first();
    $this->actingAs($admin, 'web');

    $response = $this->post(route('admin.modules.mcp.generate-token'));

    $response->assertRedirect();
    $response->assertSessionHas('mcp_token_generated');
    $response->assertSessionHas('success');

    $sessionData = session('mcp_token_generated');
    expect($sessionData)->toHaveKey('token');
    expect($sessionData['token'])->toBeString();
});

test('disabling mcp module returns 503 on mcp endpoints', function (): void {
    $manager = app(ModuleManager::class);
    $manager->discoverModules();

    // Disable the module
    CmsModule::where('identifier', 'mcp')->update(['is_active' => false]);

    $admin = User::first();
    $token = $admin->createToken('Test Agent', ['mcp:access'])->accessToken;

    $response = $this->withToken($token)->postJson('/mcp/cms', [
        'jsonrpc' => '2.0',
        'method' => 'tools/list',
        'id' => 1,
    ]);

    $response->assertNotFound();
});
