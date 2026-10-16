<?php

declare(strict_types=1);

use App\Models\OAuthServiceAccount;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\ServiceAccountProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Modules\Blog\Models\Post;
use Modules\ContentAudit\Enums\AuditStatus;
use Modules\ContentAudit\Models\ContentAudit;
use Tests\Support\McpEditorialComposition;
use Tests\Support\McpFixtures;

uses(RefreshDatabase::class, McpEditorialComposition::class);

beforeEach(function (): void {
    McpFixtures::identities();

    $clientRepository = app(ClientRepository::class);
    $this->m2mClient = app(ServiceAccountProvisioningService::class)->create('MCP service regression fixture', ['mcp:access', 'mcp:read', 'mcp:write', 'mcp:destructive'], (int) Role::where('slug', 'author')->firstOrFail()->getKey());
    $this->m2mSecret = $this->m2mClient->plainSecret;
});

test('machine-to-machine publications enter the editorial review queue', function (): void {
    $tokenResponse = $this->post('/oauth/token', [
        'grant_type' => 'client_credentials',
        'client_id' => $this->m2mClient->id,
        'client_secret' => $this->m2mSecret,
        'scope' => 'mcp:access mcp:write',
    ]);
    $token = $tokenResponse->json('access_token');

    $response = $this->withToken($token)->postJson('/mcp/cms', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => 'cms_entity_create',
            'arguments' => [
                'type' => 'post', 'publish' => true,
                'translations' => ['es' => [
                    'title' => 'Artículo automatizado pendiente de revisión',
                    'content' => "## Informe automatizado\n\nEl sistema generó este artículo sin intervención humana directa.\n\n### Conclusión\nLos datos respaldan las conclusiones del informe, que ahora pasan por el circuito editorial.",
                    'summary' => 'Resumen del artículo automatizado.',
                ]],
            ],
        ],
    ]);

    $response->assertStatus(200);
    $pending = json_decode($response->json('result.content.0.text'), true);
    expect($pending['confirmation_required'])->toBeTrue();
    expect($pending['execution']['status'])->toBe('approval_required');
    $data = mcpTestHumanApproval($pending, User::where('email', 'admin@db-cms.io')->firstOrFail());

    expect($data['status'])->toBe('draft');

    $post = Post::find($data['id']);
    expect($post)->not->toBeNull()
        ->and((bool) $post->is_published)->toBeFalse();

    $audit = ContentAudit::where('auditable_type', $post->getMorphClass())
        ->where('auditable_id', $post->id)
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->status)->toBe(AuditStatus::PENDING_REVIEW->value);
});

test('machine-to-machine publications without publish scope stay forbidden', function (): void {
    $tokenResponse = $this->post('/oauth/token', [
        'grant_type' => 'client_credentials',
        'client_id' => $this->m2mClient->id,
        'client_secret' => $this->m2mSecret,
        'scope' => 'mcp:access',
    ]);
    $token = $tokenResponse->json('access_token');

    $response = $this->withToken($token)->postJson('/mcp/cms', [
        'jsonrpc' => '2.0',
        'id' => 2,
        'method' => 'tools/call',
        'params' => [
            'name' => 'cms_entity_create',
            'arguments' => [
                'type' => 'post', 'publish' => true,
                'translations' => ['es' => [
                    'title' => 'Post Denegado',
                    'content' => 'Contenido que no debe publicarse sin permisos.',
                ]],
            ],
        ],
    ]);

    $response->assertStatus(400);
    expect($response->json('error.code'))->toBe(-32602);
});

test('the mcp service user belongs to the auditable role', function (): void {
    $tokenResponse = $this->post('/oauth/token', [
        'grant_type' => 'client_credentials',
        'client_id' => $this->m2mClient->id,
        'client_secret' => $this->m2mSecret,
        'scope' => 'mcp:access',
    ]);
    $token = $tokenResponse->json('access_token');

    $this->withToken($token)->postJson('/mcp/cms', [
        'jsonrpc' => '2.0',
        'id' => 3,
        'method' => 'tools/list',
    ]);

    $serviceUser = OAuthServiceAccount::findOrFail($this->m2mClient->getKey())->user;
    expect($serviceUser)->not->toBeNull();

    $authorRole = Role::where('slug', 'author')->first();
    expect($serviceUser->hasRole($authorRole?->slug ?? 'author'))->toBeTrue();
});
