<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\User;
use App\Services\Auth\ServiceAccountProvisioningService;
use Database\Seeders\PlatformShowcaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(PlatformShowcaseSeeder::class);

    // Create personal access client for testing
    Artisan::call('passport:client', [
        '--personal' => true,
        '--name' => 'Feature Test Personal Client',
        '--no-interaction' => true,
    ]);

    // Create M2M client for testing
    $clientRepository = app(ClientRepository::class);
    $this->m2mClient = app(ServiceAccountProvisioningService::class)->create('MCP service regression fixture', ['mcp:access', 'mcp:read', 'mcp:write', 'mcp:publish', 'mcp:destructive'], (int) Role::where('slug', 'author')->firstOrFail()->getKey());
    $this->m2mSecret = $this->m2mClient->plainSecret;
});

test('client_credentials grant negotiates JWT token via POST /oauth/token', function (): void {
    $response = $this->post('/oauth/token', [
        'grant_type' => 'client_credentials',
        'client_id' => $this->m2mClient->id,
        'client_secret' => $this->m2mSecret,
        'scope' => 'mcp:access',
    ]);

    $response->assertStatus(200);
    $data = $response->json();

    expect($data)->toHaveKeys(['token_type', 'expires_in', 'access_token']);
    expect($data['token_type'])->toBe('Bearer');
    expect($data['access_token'])->toBeString();
});

test('mcp endpoint accepts M2M token and returns tools manifest', function (): void {
    // 1. Get M2M token
    $tokenResponse = $this->post('/oauth/token', [
        'grant_type' => 'client_credentials',
        'client_id' => $this->m2mClient->id,
        'client_secret' => $this->m2mSecret,
        'scope' => 'mcp:access',
    ]);
    $token = $tokenResponse->json('access_token');

    // 2. Call /api/mcp
    $response = $this->withToken($token)->postJson('/api/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ]);

    $response->assertStatus(200);
    $result = $response->json();
    expect($result['id'])->toBe(1);
    expect($result['result']['tools'])->toBeArray();
});

test('mcp endpoint enforces mcp:publish scope on mutating tools', function (): void {
    // Token with ONLY mcp:access (no mcp:publish)
    $tokenResponse = $this->post('/oauth/token', [
        'grant_type' => 'client_credentials',
        'client_id' => $this->m2mClient->id,
        'client_secret' => $this->m2mSecret,
        'scope' => 'mcp:access',
    ]);
    $readToken = $tokenResponse->json('access_token');

    // Attempt to publish post
    $forbiddenResponse = $this->withToken($readToken)->postJson('/api/mcp', [
        'jsonrpc' => '2.0',
        'id' => 2,
        'method' => 'tools/call',
        'params' => [
            'name' => 'cms_publish_post',
            'arguments' => [
                'title' => 'Post Denegado',
                'content' => 'Contenido que debe ser rechazado',
            ],
        ],
    ]);

    $forbiddenResponse->assertOk();
    expect(json_decode((string) $forbiddenResponse->json('result.content.0.text'), true)['denied'])->toBeTrue();

    // Now request token WITH mcp:publish
    $publishTokenResponse = $this->post('/oauth/token', [
        'grant_type' => 'client_credentials',
        'client_id' => $this->m2mClient->id,
        'client_secret' => $this->m2mSecret,
        'scope' => 'mcp:access mcp:publish',
    ]);
    $publishToken = $publishTokenResponse->json('access_token');

    $successResponse = $this->withToken($publishToken)->postJson('/api/mcp', [
        'jsonrpc' => '2.0',
        'id' => 3,
        'method' => 'tools/call',
        'params' => [
            'name' => 'cms_publish_post',
            'arguments' => [
                'title' => 'Post Autorizado M2M',
                'content' => 'Contenido redactado con variedad métrica y cadencia humana en castellano.',
                'summary' => 'Resumen de prueba para verificar autorización de publicación.',
                'locale' => 'es',
            ],
        ],
    ]);

    $successResponse->assertStatus(200);
    $data = json_decode($successResponse->json('result.content.0.text'), true);
    expect($data['confirmation_required'])->toBeTrue();
    expect($data['execution']['status'])->toBe('approval_required');
});

test('mcp sse endpoint yields streaming event-stream handshake', function (): void {
    $tokenResponse = $this->post('/oauth/token', [
        'grant_type' => 'client_credentials',
        'client_id' => $this->m2mClient->id,
        'client_secret' => $this->m2mSecret,
        'scope' => 'mcp:access',
    ]);
    $token = $tokenResponse->json('access_token');

    $response = $this->withToken($token)->get('/api/mcp/sse');

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'text/event-stream; charset=utf-8');
});

test('mcp tools manifest requires authenticated identity', function (): void {
    $this->getJson('/api/mcp/tools')->assertUnauthorized();
    $admin = User::where('email', 'admin@db-cms.io')->firstOrFail();
    $response = $this->withToken($admin->createToken('tools', ['mcp:access', 'mcp:read'])->accessToken)->getJson('/api/mcp/tools');

    $response->assertStatus(200);
    expect($response->json('tools'))->toBeArray();
    expect($response->json('transport.http'))->toBeString();
    expect($response->json('transport.sse'))->toBeString();
});

test('backoffice token management full crud workflow', function (): void {
    $admin = User::first();
    $this->actingAs($admin);

    // 1. Index view renders
    $indexResponse = $this->get(route('admin.tokens.index'));
    $indexResponse->assertStatus(200);
    $indexResponse->assertSee('Gestión de Tokens de API');

    // 2. Create view renders
    $createResponse = $this->get(route('admin.tokens.create'));
    $createResponse->assertStatus(200);
    $createResponse->assertSee('Personal Access Token');
    $createResponse->assertSee('Cliente M2M');

    // 3. Store Personal Token
    $storePersonal = $this->post(route('admin.tokens.store-personal'), [
        'name' => 'Admin Test Personal Token',
        'subject_type' => 'users',
        'subject_id' => $admin->id,
        'scopes' => ['mcp:access', 'mcp:publish'],
    ]);
    $storePersonal->assertRedirect(route('admin.tokens.index'));
    $storePersonal->assertSessionHas('issued_token');

    // 4. Store M2M Client
    $storeM2m = $this->post(route('admin.tokens.store-m2m'), [
        'name' => 'External Zapier Service',
        'role_id' => Role::where('slug', 'author')->firstOrFail()->getKey(),
        'scopes' => ['mcp:access'],
    ]);
    $storeM2m->assertRedirect(route('admin.tokens.index'));
    $storeM2m->assertSessionHas('issued_m2m_client');

    // 5. Revoke Client
    $client = Client::where('name', 'External Zapier Service')->first();
    expect($client)->not->toBeNull();

    $revokeClient = $this->post(route('admin.tokens.revoke-client', $client->id));
    $revokeClient->assertRedirect();
    expect($client->fresh()->revoked)->toBeTrue();
});
