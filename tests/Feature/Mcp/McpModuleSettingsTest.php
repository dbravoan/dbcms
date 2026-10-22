<?php

declare(strict_types=1);

namespace Tests\Feature\Mcp;

use App\Contracts\Services\AdminThemeServiceInterface;
use App\Contracts\Services\ModuleSettingsServiceInterface;
use App\Core\Modules\ModuleManager;
use App\Models\CmsModule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
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
    expect($discovered['mcp']['settings'])->toHaveKeys(['is_enabled', 'rate_limit_per_minute']);
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

test('MCP settings keep token issuance in its own form under the current CSP', function (string $brand): void {
    app(AdminThemeServiceInterface::class)->setActiveTheme($brand);
    $response = $this->actingAs(User::firstOrFail(), 'web')->get(route('admin.modules.settings', ['identifier' => 'mcp']));
    $response->assertOk()->assertDontSee('onclick=', false)->assertDontSee('settings[enable_sse]', false)->assertDontSee('settings[log_mcp_requests]', false);
    $document = new \DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new \DOMXPath($document);
    $tokenForms = $xpath->query('//form[@action="'.route('admin.modules.mcp.generate-token').'"]');
    expect($tokenForms->length)->toBe(1);
    $form = $tokenForms->item(0);
    expect($xpath->query('ancestor::form', $form)->length)->toBe(0);
    expect($xpath->query('.//input[@name="_method"]', $form)->length)->toBe(0);
    expect($xpath->query('.//input[@name="_token"]', $form)->length)->toBe(1);
    expect($xpath->query('.//button[@type="submit"]', $form)->length)->toBe(1);
})->with(['dbcms', 'termocms']);

test('MCP rate limits reject nonpositive or fractional settings before persistence', function (mixed $limit): void {
    expect(fn () => app(ModuleSettingsServiceInterface::class)->setMany('mcp', ['rate_limit_per_minute' => $limit]))
        ->toThrow(ValidationException::class);
    expect(app(ModuleSettingsServiceInterface::class)->get('mcp', 'rate_limit_per_minute'))->toBe(60);
})->with([0, -1, 1.5]);
