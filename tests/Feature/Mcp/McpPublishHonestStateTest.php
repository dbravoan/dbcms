<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Blog\Models\Post;
use Tests\Support\McpEditorialComposition;
use Tests\Support\McpFixtures;

uses(RefreshDatabase::class, McpEditorialComposition::class);

beforeEach(function (): void {
    McpFixtures::identities();

});

test('restricted author token reports the real pending state instead of claiming publication', function (): void {
    $authorRole = Role::where('slug', 'author')->first();
    $author = User::factory()->create();
    $author->roles()->sync([$authorRole->id]);

    $token = $author->createToken('Author Editorial Token', ['mcp:access', 'mcp:write'])->accessToken;

    $response = $this->withToken($token)->postJson('/mcp/cms', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => 'cms_entity_create',
            'arguments' => [
                'type' => 'post', 'publish' => true,
                'translations' => ['es' => [
                    'title' => 'Artículo de autor en revisión editorial',
                    'content' => "## Borrador del autor\n\nEl texto pasa por el circuito de calidad antes de ser visible.\n\n### Nota final\nLa respuesta del CMS debe reflejar el estado real de la entidad.",
                    'summary' => 'Resumen pendiente de revisión.',
                ]],
            ],
        ],
    ]);

    $response->assertStatus(200);
    $pending = json_decode($response->json('result.content.0.text'), true);
    expect($pending['confirmation_required'])->toBeTrue();
    expect($pending['execution']['status'])->toBe('approval_required');
    $data = mcpTestHumanApproval($pending, isset($author) ? $author : $admin);

    expect($data['status'])->toBe('draft');

    $post = Post::find($data['id']);
    expect((bool) $post->is_published)->toBeFalse();
});

test('approver token publishes directly and reports the real state', function (): void {
    $adminRole = Role::where('slug', 'admin')->first();
    $admin = User::factory()->create();
    $admin->roles()->sync([$adminRole->id]);

    $token = $admin->createToken('Admin Editorial Token', ['mcp:access', 'mcp:write'])->accessToken;

    $response = $this->withToken($token)->postJson('/mcp/cms', [
        'jsonrpc' => '2.0',
        'id' => 2,
        'method' => 'tools/call',
        'params' => [
            'name' => 'cms_entity_create',
            'arguments' => [
                'type' => 'post', 'publish' => true,
                'translations' => ['es' => [
                    'title' => 'Artículo publicado directamente por el equipo editorial',
                    'content' => "## Publicación directa\n\nEl equipo editorial confirma el contenido sin pasar por la cola de revisión.\n\n### Conclusión\nEl estado devuelto debe ser coherente con la base de datos.",
                    'summary' => 'Resumen publicado.',
                ]],
            ],
        ],
    ]);

    $response->assertStatus(200);
    $pending = json_decode($response->json('result.content.0.text'), true);
    expect($pending['confirmation_required'])->toBeTrue();
    expect($pending['execution']['status'])->toBe('approval_required');
    $data = mcpTestHumanApproval($pending, isset($author) ? $author : $admin);

    expect($data['status'])->toBe('published');

    $post = Post::find($data['id']);
    expect((bool) $post->is_published)->toBeTrue();
});
