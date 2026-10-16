<?php

declare(strict_types=1);

use App\Core\Capabilities\CapabilityRegistry;
use App\Models\CapabilityAuditLog;
use App\Models\Market;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\Post;
use Modules\ContentAudit\Services\EditorialWorkflow;
use Tests\Support\McpComposition;
use Tests\Support\McpFixtures;
use Tests\Support\McpProtocolClient;

uses(RefreshDatabase::class, McpComposition::class);

beforeEach(function (): void {
    McpFixtures::identities();
    $this->admin = User::where('email', 'admin@db-cms.io')->firstOrFail();
    mcpTestBindIdentity($this->admin);
    $category = Category::create(['is_active' => true]);
    $category->translations()->create(['locale' => 'es', 'name' => 'Tecnología', 'slug' => 'tecnologia']);
    $category->markets()->attach(Market::where('code', 'es')->firstOrFail());
    $post = Post::withoutEvents(fn () => Post::create(['user_id' => $this->admin->id, 'category_id' => $category->id, 'is_published' => true, 'published_at' => now()]));
    $post->translations()->create(['locale' => 'es', 'title' => 'Arquitectura modular', 'slug' => 'arquitectura', 'content' => 'Contenido público sobre arquitectura modular.']);
    $post->markets()->attach(Market::where('code', 'es')->firstOrFail());
});

/**
 * Calls a capability through the real MCP JSON-RPC server, with the scopes and
 * the user the Passport middleware would have resolved.
 */
function callCapability(string $name, array $args = [], ?User $user = null, array $scopes = ['mcp:access', 'mcp:read', 'mcp:write', 'mcp:destructive'], bool $m2m = false): array
{
    if ($m2m) {
        throw new LogicException('Use a persisted service-account fixture for machine calls.');
    }
    mcpTestBindIdentity($user, $scopes);

    $response = app(McpProtocolClient::class)->handle([
        'jsonrpc' => '2.0',
        'id' => 'test',
        'method' => 'tools/call',
        'params' => ['name' => $name, 'arguments' => $args],
    ]);

    $text = (string) ($response['result']['content'][0]['text'] ?? '');

    /** @var array<string, mixed> $decoded */
    $decoded = json_decode($text, true) ?? [];

    return mcpTestHumanApproval($decoded, $user) + (isset($response['error']) ? ['rpc_error' => $response['error']] : []);
}

test('the capability manifest advertises the backoffice surface with its permissions', function (): void {
    $registry = app(CapabilityRegistry::class);

    expect($registry->names())->toContain('cms_entity_list')
        ->and($registry->names())->toContain('cms_entity_create')
        ->and($registry->names())->toContain('cms_resource_update')
        ->and($registry->names())->toContain('cms_module_toggle')
        ->and($registry->names())->toContain('cms_maintenance_toggle')
        ->and($registry->names())->toContain('cms_mcp_audit');

    $manifest = collect($registry->all())->map(static fn ($tool): array => ['name' => $tool->name(), 'parameters' => ['required' => $tool->required(), 'properties' => $tool->properties()]])->keyBy('name');
    $entity = $manifest->get('cms_entity_create');

    expect($entity)->not->toBeNull()
        ->and($entity['parameters']['required'])->toContain('type')
        ->and($entity['parameters']['properties'])->toHaveKeys(['type', 'attributes', 'translations']);
});

test('tools list exposes the authorized capability registry', function (): void {
    $response = app(McpProtocolClient::class)->handle(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list']);
    $names = array_column((array) ($response['result']['tools'] ?? []), 'name');

    expect($names)->toContain('cms_capabilities')
        ->and($names)->toContain('cms_entity_create')
        ->and($names)->toContain('cms_resource_list')
        ->and($names)->toContain('cms_search_reindex')
        ->and(count($names))->toBe(count(array_unique($names)));
});

test('cms_capabilities describes what an agent may do', function (): void {
    $result = callCapability('cms_capabilities', [], $this->admin);

    expect($result)->toHaveKeys(['tools', 'entity_types', 'resources', 'abilities'])
        ->and(collect($result['tools'])->pluck('name'))->toContain('cms_entity_delete')
        ->and(collect($result['entity_types'])->pluck('type'))->toContain('post')
        ->and(collect($result['resources'])->pluck('resource'))->toContain('market')
        ->and($result['abilities']['destructive'])->toContain('approval');
});

test('an entity can be created, read, listed, updated and deleted through the capability layer', function (): void {
    $category = Post::query()->firstOrFail()->category_id;

    $created = callCapability('cms_entity_create', [
        'type' => 'post',
        'attributes' => ['category_id' => $category, 'is_featured' => true, 'publish' => true],
        'translations' => [
            'es' => ['title' => 'Capacidad MCP', 'summary' => 'Creado por un agente'],
            'en' => ['title' => 'MCP capability'],
        ],
    ], $this->admin);

    expect($created['id'])->toBeInt()
        ->and($created['status'])->toBe('published')
        ->and($created['translations']['es']['title'])->toBe('Capacidad MCP')
        ->and($created['translations']['es']['slug'])->toBe('capacidad-mcp')
        ->and($created['urls']['es'])->toContain('/blog/capacidad-mcp')
        // The acting agent becomes the author.
        ->and($created['attributes']['user_id'])->toBe($this->admin->id);

    $read = callCapability('cms_entity_get', ['type' => 'post', 'id' => $created['id']], $this->admin);
    expect($read['id'])->toBe($created['id']);

    $listed = callCapability('cms_entity_list', ['type' => 'post', 'search' => 'Capacidad MCP'], $this->admin);
    expect($listed['total'])->toBe(1)
        ->and($listed['items'][0]['id'])->toBe($created['id']);

    $updated = callCapability('cms_entity_update', [
        'type' => 'post',
        'id' => $created['id'],
        'attributes' => ['publish' => false],
        'translations' => ['es' => ['title' => 'Capacidad MCP renombrada']],
    ], $this->admin);

    expect($updated['status'])->toBe('draft')
        ->and($updated['translations']['es']['title'])->toBe('Capacidad MCP renombrada')
        // The other locale was not touched.
        ->and($updated['translations']['en']['title'])->toBe('MCP capability');

    $deleted = callCapability('cms_entity_delete', ['type' => 'post', 'id' => $created['id'], 'confirm' => true], $this->admin);

    expect($deleted['deleted'])->toBeTrue()
        ->and(Post::query()->whereKey($created['id'])->exists())->toBeFalse();
});

test('entity slugs stay unique per locale', function (): void {
    $first = callCapability('cms_entity_create', [
        'type' => 'post',
        'translations' => ['es' => ['title' => 'Slug repetido']],
    ], $this->admin);

    $second = callCapability('cms_entity_create', [
        'type' => 'post',
        'translations' => ['es' => ['title' => 'Slug repetido']],
    ], $this->admin);

    expect($first['translations']['es']['slug'])->toBe('slug-repetido')
        ->and($second['translations']['es']['slug'])->toBe('slug-repetido-2');
});

test('platform resources are manageable: markets, media metadata and orders', function (): void {
    $created = callCapability('cms_resource_create', [
        'resource' => 'market',
        'attributes' => ['code' => 'fr', 'name' => 'Francia', 'currency' => 'EUR', 'default_locale' => 'fr'],
    ], $this->admin);

    expect($created['resource'])->toBe('market')
        ->and($created['code'])->toBe('fr');

    $listed = callCapability('cms_resource_list', ['resource' => 'market', 'search' => 'Francia'], $this->admin);
    expect($listed['total'])->toBe(1);

    $updated = callCapability('cms_resource_update', [
        'resource' => 'market',
        'id' => $created['id'],
        'attributes' => ['name' => 'Francia metropolitana'],
    ], $this->admin);

    expect($updated['name'])->toBe('Francia metropolitana');

    // Every resource is described with its permission and allowed operations.
    $types = callCapability('cms_resource_types', [], $this->admin);
    $order = collect($types['resources'])->firstWhere('resource', 'order');

    expect($order['permission'])->toBe('manage:shop')
        ->and($order['operations'])->toContain('update')
        ->and($order['operations'])->not->toContain('create');
});

test('platform actions work: module lifecycle, maintenance, caches and search reindex', function (): void {
    $toggled = callCapability('cms_module_toggle', ['identifier' => 'forms', 'enabled' => false], $this->admin);
    expect($toggled['enabled'])->toBeFalse();

    $toggled = callCapability('cms_module_toggle', ['identifier' => 'forms'], $this->admin);
    expect($toggled['enabled'])->toBeTrue();

    $settings = callCapability('cms_module_settings_update', [
        'identifier' => 'blog',
        'settings' => ['comments_enabled' => false],
    ], $this->admin);
    expect($settings['settings']['comments_enabled'])->toBeFalse();

    $maintenance = callCapability('cms_maintenance_toggle', ['enabled' => true, 'message' => 'Volvemos pronto', 'confirm' => true], $this->admin);
    expect($maintenance['active'])->toBeTrue();

    $maintenance = callCapability('cms_maintenance_toggle', ['enabled' => false, 'confirm' => true], $this->admin);
    expect($maintenance['active'])->toBeFalse();

    $purged = callCapability('cms_cache_purge', ['confirm' => true], $this->admin);
    expect($purged['purged'])->toBeTrue();

    $reindex = callCapability('cms_search_reindex', ['keep' => true], $this->admin);
    expect($reindex['total'])->toBeGreaterThan(0);
});

test('block sections of a page can be read and replaced by an agent', function (): void {
    $page = callCapability('cms_entity_create', [
        'type' => 'page',
        'translations' => ['es' => ['title' => 'Landing MCP', 'slug' => 'landing-mcp']],
    ], $this->admin);

    $synced = callCapability('cms_content_sections_sync', [
        'type' => 'page',
        'id' => $page['id'],
        'sections' => [
            'es' => [
                ['type' => 'text', 'title' => 'Hola', 'content' => 'Bloque creado por un agente'],
            ],
            'en' => [
                ['type' => 'text', 'title' => 'Hello', 'content' => 'Block written by an agent'],
            ],
        ],
    ], $this->admin);

    expect($synced['sections'])->toHaveCount(1)
        ->and($synced['sections'][0]['type'])->toBe('text')
        ->and($synced['sections'][0]['translations']['es']['content'])->toBe('Bloque creado por un agente')
        ->and($synced['sections'][0]['translations']['en']['content'])->toBe('Block written by an agent');

    $read = callCapability('cms_content_sections_get', ['type' => 'page', 'id' => $page['id']], $this->admin);
    expect($read['sections'])->toHaveCount(1);
});

test('the editorial queue can be approved from an agent', function (): void {
    $post = Post::query()->firstOrFail();

    // A pending revision, exactly as an author would leave it.
    app(EditorialWorkflow::class)
        ->submitForReview($post, $this->admin, 'Revisión solicitada por el agente');

    $approved = callCapability('cms_audit_approve', ['type' => 'post', 'id' => $post->id, 'notes' => 'Aprobado por el agente'], $this->admin);

    expect($approved['approved'])->toBeTrue();
});

test('the seo curation of a filter landing is reachable', function (): void {
    $result = callCapability('cms_seo_filter_upsert', [
        'module' => 'blog',
        'path' => 'tecnologia',
        'market' => 'es',
        'locale' => 'es',
        'title' => 'Tecnología',
        'is_indexable' => true,
    ], $this->admin);

    expect($result['path'])->toBe('tecnologia')
        ->and($result['is_indexable'])->toBeTrue();

    $deindexed = callCapability('cms_seo_filter_upsert', [
        'module' => 'blog',
        'path' => 'tecnologia',
        'market' => 'es',
        'locale' => 'es',
        'is_indexable' => false,
    ], $this->admin);

    expect($deindexed['is_indexable'])->toBeFalse();
});

test('every capability call is written to the agent audit trail', function (): void {
    callCapability('cms_module_toggle', ['identifier' => 'forms', 'enabled' => false], $this->admin);
    callCapability('cms_settings_update', ['attributes' => ['general.site_name' => 'Agente']], $this->admin);

    expect(CapabilityAuditLog::query()->where('tool', 'cms_module_toggle')->where('status', 'ok')->exists())->toBeTrue()
        ->and(CapabilityAuditLog::query()->where('tool', 'cms_settings_update')->exists())->toBeTrue();

    $trail = callCapability('cms_mcp_audit', ['limit' => 5], $this->admin);

    expect($trail['entries'])->not->toBeEmpty()
        ->and($trail['stats']['total'])->toBeGreaterThan(0);
});

test('secrets are redacted from the audit trail', function (): void {
    callCapability('cms_settings_update', [
        'attributes' => ['general.smtp_password' => 'super-secreto', 'general.site_name' => 'Visible'],
    ], $this->admin);

    $entry = CapabilityAuditLog::query()->where('tool', 'cms_settings_update')->latest('id')->firstOrFail();
    $args = (array) $entry->getAttribute('args');

    expect(json_encode($args))->not->toContain('super-secreto')
        ->and(json_encode($args))->toContain('Visible');
});
