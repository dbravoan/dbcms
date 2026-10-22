<?php

declare(strict_types=1);

use App\Models\CmsModule;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\ServiceAccountProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\ClientRepository;
use Tests\Support\McpCoreComposition;
use Tests\Support\McpFixtures;

uses(RefreshDatabase::class, McpCoreComposition::class);

beforeEach(function (): void {
    McpFixtures::identities();

    $repository = app(ClientRepository::class);
    $this->m2mClient = app(ServiceAccountProvisioningService::class)->create('MCP HTTP Transport Test', ['mcp:access', 'mcp:read', 'mcp:write', 'mcp:destructive'], Role::where('slug', 'admin')->firstOrFail()->getKey());
    $this->m2mSecret = $this->m2mClient->plainSecret;
});

/**
 * Negotiate an M2M JWT through the canonical OAuth2 token endpoint.
 */
function mcpHttpAccessToken(string $scope = 'mcp:access mcp:read'): string
{
    $response = test()->post('/oauth/token', [
        'grant_type' => 'client_credentials',
        'client_id' => test()->m2mClient->id,
        'client_secret' => test()->m2mSecret,
        'scope' => $scope,
    ]);

    return (string) $response->json('access_token');
}

function mcpHttpCall(string $token, string $tool, array $arguments = []): TestResponse
{
    return test()->withToken($token)->postJson('/mcp/cms', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => ['name' => $tool, 'arguments' => $arguments],
    ]);
}

test('tools/list returns the full manifest over HTTP', function (): void {
    $token = mcpHttpAccessToken();

    $response = $this->withToken($token)->postJson('/mcp/cms', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ]);

    $response->assertOk();
    expect($response->json('result.tools'))->toBeArray()->not->toBeEmpty();
    expect(array_column($response->json('result.tools'), 'name'))->toContain('cms_capabilities');
});

test('read tools execute over HTTP', function (): void {
    $token = mcpHttpAccessToken();

    $calls = [
        ['cms_capabilities', []],
        ['cms_entity_list', ['type' => 'page']],
        ['cms_resource_list', ['resource' => 'market']],
        ['cms_audit_seo_geo', ['title' => 'Título', 'content' => 'Contenido de prueba suficiente para auditar.']],
    ];

    foreach ($calls as [$tool, $args]) {
        $response = mcpHttpCall($token, $tool, $args);
        $response->assertOk();
        expect($response->json('result'))->toHaveKey('content');
        expect($response->json('error'))->toBeNull();
    }
});

test('mutating tools require their common OAuth ability', function (): void {
    $token = mcpHttpAccessToken('mcp:access');

    foreach (['cms_entity_create', 'cms_entity_update', 'cms_entity_delete'] as $tool) {
        $response = mcpHttpCall($token, $tool, ['type' => 'page', 'id' => 1, 'translations' => ['es' => ['title' => 'Page']]]);

        $response->assertStatus(400);
        expect($response->json('error.code'))->toBe(-32602);
    }
});

test('page lifecycle works over HTTP with mcp:write', function (): void {
    $token = mcpHttpAccessToken('mcp:access mcp:write mcp:destructive');

    // Create
    $create = mcpHttpCall($token, 'cms_entity_create', [
        'type' => 'page',
        'translations' => ['es' => ['title' => 'Page HTTP MCP', 'content' => 'Cuerpo de la página']],
    ]);
    $create->assertOk();
    $created = json_decode((string) $create->json('result.content.0.text'), true);
    expect($created['status'])->toBe('draft');
    $postId = (int) $created['id'];

    // Update
    $update = mcpHttpCall($token, 'cms_entity_update', [
        'type' => 'page', 'id' => $postId,
        'translations' => ['es' => ['title' => 'Page HTTP MCP v2', 'content' => 'Cuerpo actualizado']],
    ]);
    $update->assertOk();

    // Delete
    $delete = mcpHttpCall($token, 'cms_entity_delete', ['type' => 'page', 'id' => $postId]);
    $delete->assertOk();

    $pending = json_decode((string) $delete->json('result.content.0.text'), true);
    expect($pending['confirmation_required'])->toBeTrue();
    expect($pending['execution']['status'])->toBe('approval_required');
    $this->assertDatabaseHas('pages', ['id' => $postId]);
    mcpTestHumanApproval($pending, User::where('email', 'admin@db-cms.io')->firstOrFail());
    $this->assertDatabaseMissing('pages', ['id' => $postId]);
});

test('the mcp endpoint requires a valid bearer token', function (): void {
    $response = $this->postJson('/mcp/cms', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ]);

    $response->assertStatus(401);
});

test('authenticated requests share the configured MCP limit per identity, even across tokens', function (): void {
    $module = CmsModule::where('identifier', 'mcp')->firstOrFail();
    $module->update(['settings' => ['is_enabled' => true, 'rate_limit_per_minute' => 1]]);
    $first = mcpHttpAccessToken();
    $second = mcpHttpAccessToken();
    mcpHttpCall($first, 'cms_capabilities')->assertOk();
    mcpHttpCall($second, 'cms_capabilities')->assertStatus(429)->assertHeader('Retry-After');
    $admin = User::where('email', 'admin@db-cms.io')->firstOrFail();
    $personalToken = $admin->createToken('Separate human identity', ['mcp:access', 'mcp:read'])->accessToken;
    mcpHttpCall($personalToken, 'cms_capabilities')->assertOk();
    $this->withHeaders(['Authorization' => 'Bearer invalid'])->postJson('/mcp/cms', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertUnauthorized();
});
