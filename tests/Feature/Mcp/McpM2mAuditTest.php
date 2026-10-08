<?php

declare(strict_types=1);

use App\Models\OAuthServiceAccount;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\ServiceAccountProvisioningService;
use Database\Seeders\PlatformShowcaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Passport\ClientRepository;
use Modules\Blog\Models\Post;
use Modules\ContentAudit\Enums\AuditStatus;
use Modules\ContentAudit\Models\ContentAudit;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(PlatformShowcaseSeeder::class);

    Artisan::call('passport:client', [
        '--personal' => true,
        '--name' => 'Feature Test Personal Client',
        '--no-interaction' => true,
    ]);

    $clientRepository = app(ClientRepository::class);
    $this->m2mClient = app(ServiceAccountProvisioningService::class)->create('MCP service regression fixture', ['mcp:access', 'mcp:read', 'mcp:write', 'mcp:publish', 'mcp:destructive'], (int) Role::where('slug', 'author')->firstOrFail()->getKey());
    $this->m2mSecret = $this->m2mClient->plainSecret;
});

test('machine-to-machine publications enter the editorial review queue', function (): void {
    $tokenResponse = $this->post('/oauth/token', [
        'grant_type' => 'client_credentials',
        'client_id' => $this->m2mClient->id,
        'client_secret' => $this->m2mSecret,
        'scope' => 'mcp:access mcp:publish',
    ]);
    $token = $tokenResponse->json('access_token');

    $response = $this->withToken($token)->postJson('/api/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => 'cms_publish_post',
            'arguments' => [
                'title' => 'Artículo automatizado pendiente de revisión',
                'content' => "## Informe automatizado\n\nEl sistema generó este artículo sin intervención humana directa.\n\n### Conclusión\nLos datos respaldan las conclusiones del informe, que ahora pasan por el circuito editorial.",
                'summary' => 'Resumen del artículo automatizado.',
                'locale' => 'es',
            ],
        ],
    ]);

    $response->assertStatus(200);
    $pending = json_decode($response->json('result.content.0.text'), true);
    expect($pending['confirmation_required'])->toBeTrue();
    expect($pending['execution']['status'])->toBe('approval_required');
    $data = mcpTestHumanApproval($pending, User::where('email', 'admin@db-cms.io')->firstOrFail());

    expect($data['success'])->toBeTrue()
        ->and($data['published'])->toBeFalse()
        ->and($data['audit_status'])->toBe(AuditStatus::PENDING_REVIEW->value);

    $post = Post::find($data['post_id']);
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

    $response = $this->withToken($token)->postJson('/api/mcp', [
        'jsonrpc' => '2.0',
        'id' => 2,
        'method' => 'tools/call',
        'params' => [
            'name' => 'cms_publish_post',
            'arguments' => [
                'title' => 'Post Denegado',
                'content' => 'Contenido que no debe publicarse sin permisos.',
            ],
        ],
    ]);

    $response->assertOk();
    expect(json_decode((string) $response->json('result.content.0.text'), true)['denied'])->toBeTrue();
});

test('the mcp service user belongs to the auditable role', function (): void {
    $tokenResponse = $this->post('/oauth/token', [
        'grant_type' => 'client_credentials',
        'client_id' => $this->m2mClient->id,
        'client_secret' => $this->m2mSecret,
        'scope' => 'mcp:access',
    ]);
    $token = $tokenResponse->json('access_token');

    $this->withToken($token)->postJson('/api/mcp', [
        'jsonrpc' => '2.0',
        'id' => 3,
        'method' => 'tools/list',
    ]);

    $serviceUser = OAuthServiceAccount::findOrFail($this->m2mClient->getKey())->user;
    expect($serviceUser)->not->toBeNull();

    $authorRole = Role::where('slug', 'author')->first();
    expect($serviceUser->hasRole($authorRole?->slug ?? 'author'))->toBeTrue();
});
