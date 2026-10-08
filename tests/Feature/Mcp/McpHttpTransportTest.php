<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\User;
use App\Services\Auth\ServiceAccountProvisioningService;
use Database\Seeders\PlatformShowcaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\ClientRepository;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(PlatformShowcaseSeeder::class);

    $repository = app(ClientRepository::class);
    $this->m2mClient = app(ServiceAccountProvisioningService::class)->create('MCP HTTP Transport Test', ['mcp:access', 'mcp:read', 'mcp:write', 'mcp:publish', 'mcp:destructive'], Role::where('slug', 'admin')->firstOrFail()->getKey());
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
    return test()->withToken($token)->postJson('/api/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => ['name' => $tool, 'arguments' => $arguments],
    ]);
}

test('tools/list returns the full manifest over HTTP', function (): void {
    $token = mcpHttpAccessToken();

    $response = $this->withToken($token)->postJson('/api/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ]);

    $response->assertOk();
    expect($response->json('result.tools'))->toBeArray()->not->toBeEmpty();
    expect(array_column($response->json('result.tools'), 'name'))->toContain('cms_inspect_system');
});

test('read tools execute over HTTP', function (): void {
    $token = mcpHttpAccessToken();

    $calls = [
        ['cms_inspect_system', []],
        ['cms_search_content', ['query' => 'a']],
        ['cms_list_posts', ['limit' => 2]],
        ['cms_list_pages', []],
        ['cms_list_categories', []],
        ['cms_audit_seo_geo', ['title' => 'Titulo', 'content' => 'Contenido de prueba suficiente para auditar.']],
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

    foreach (['cms_create_post', 'cms_update_post', 'cms_delete_post', 'cms_publish_post', 'cms_create_category', 'cms_create_page'] as $tool) {
        $response = mcpHttpCall($token, $tool, ['id' => 1, 'title' => 'X', 'content' => 'Y', 'slug' => 'x']);

        $response->assertOk();
        expect(json_decode((string) $response->json('result.content.0.text'), true)['denied'])->toBeTrue();
    }
});

test('post lifecycle works over HTTP with mcp:publish', function (): void {
    $token = mcpHttpAccessToken('mcp:access mcp:publish mcp:destructive');

    // Create
    $create = mcpHttpCall($token, 'cms_create_post', [
        'title' => 'Post HTTP MCP',
        'content' => '## Cuerpo del post',
        'summary' => 'Resumen',
        'locale' => 'es',
    ]);
    $create->assertOk();
    $created = json_decode((string) $create->json('result.content.0.text'), true);
    expect($created['success'])->toBeTrue();
    $postId = (int) $created['post_id'];

    // Update
    $update = mcpHttpCall($token, 'cms_update_post', [
        'id' => $postId,
        'title' => 'Post HTTP MCP v2',
        'content' => '## Cuerpo actualizado',
        'locale' => 'es',
    ]);
    $update->assertOk();

    // Delete
    $delete = mcpHttpCall($token, 'cms_delete_post', ['id' => $postId]);
    $delete->assertOk();

    $pending = json_decode((string) $delete->json('result.content.0.text'), true);
    expect($pending['confirmation_required'])->toBeTrue();
    expect($pending['execution']['status'])->toBe('approval_required');
    $this->assertDatabaseHas('posts', ['id' => $postId]);
    mcpTestHumanApproval($pending, User::where('email', 'admin@db-cms.io')->firstOrFail());
    $this->assertDatabaseMissing('posts', ['id' => $postId]);
});

test('the mcp endpoint requires a valid bearer token', function (): void {
    $response = $this->postJson('/api/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ]);

    $response->assertStatus(401);
});
