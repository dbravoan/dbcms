<?php

declare(strict_types=1);

use App\Contracts\Services\ModuleSettingsServiceInterface;
use App\Core\Capabilities\ExecutionContext;
use App\Core\Modules\ModuleManager;
use App\Models\Market;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Capabilities\CmsActionService;
use Database\Seeders\PlatformShowcaseSeeder;
use Modules\Blog\Models\Post;
use Modules\ContentAudit\Models\ContentAuditRevision;
use Modules\Mcp\Services\McpServerService;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function (): void {
    $this->seed(PlatformShowcaseSeeder::class);
    $this->admin = User::where('email', 'admin@db-cms.io')->firstOrFail();
    $this->author = User::where('email', 'author@db-cms.io')->firstOrFail();
});

test('an arbitrary role does not grant access to the administrative shell', function (): void {
    $user = User::factory()->create();
    $user->roles()->attach(Role::create(['slug' => 'empty-role', 'name' => 'Empty role']));
    $this->actingAs($user)->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
});

test('AI permission alone does not bypass backoffice entry authorization', function (): void {
    $role = Role::create(['slug' => 'ai-only', 'name' => 'AI only']);
    $role->permissions()->attach(Permission::where('slug', 'use:ai')->value('id'));
    $user = User::factory()->create();
    $user->roles()->attach($role);
    $this->actingAs($user)->get(route('admin.ai_assistant.workbench'))->assertRedirect(route('admin.login'));
});

test('authors cannot disable editorial review or change approver roles', function (): void {
    $settings = app(ModuleSettingsServiceInterface::class);
    $before = $settings->getAll('content_audit');
    $this->actingAs($this->author)->post(route('admin.audit.settings'), ['is_enabled' => false, 'approver_roles' => ['author']])->assertForbidden();
    expect($settings->getAll('content_audit'))->toBe($before);
});

test('private selector sources require their resource permission', function (string $source): void {
    $this->actingAs($this->author)->getJson(route('admin.api.entity-search', ['source' => $source]))->assertForbidden();
})->with(['users', 'roles', 'forms']);

test('media browsing requires management permission even for an administrative account', function (): void {
    $this->author->roles()->firstOrFail()->permissions()->detach(Permission::where('slug', 'manage:media')->value('id'));
    $this->actingAs($this->author)->getJson(route('admin.media.index'))->assertForbidden();
});

test('selected ids cannot resolve unpublished content or bypass the market scope', function (): void {
    $draft = Post::create(['user_id' => $this->admin->id, 'is_published' => false]);
    $draft->translations()->create(['locale' => 'es', 'title' => 'Private selector draft', 'slug' => 'private-selector-draft', 'content' => 'Private draft body']);
    $market = Market::create(['code' => 'zz', 'name' => 'Empty market', 'default_locale' => 'es', 'currency' => 'EUR', 'is_active' => true]);
    $published = Post::where('is_published', true)->firstOrFail();
    $this->actingAs($this->admin)->getJson(route('admin.api.entity-search', ['source' => 'blog_posts', 'ids' => [(string) $draft->id]]))->assertOk()->assertJsonPath('results', []);
    $this->getJson(route('admin.api.entity-search', ['source' => 'blog_posts', 'ids' => [(string) $published->id], 'market_ids' => [$market->id]]))->assertOk()->assertJsonPath('results', []);
});

test('disabled module selector sources cannot be queried or resolved', function (): void {
    app(ModuleManager::class)->deactivate('blog');
    $this->actingAs($this->admin)->getJson(route('admin.api.entity-search', ['source' => 'blog_posts']))->assertForbidden();
});

test('authors cannot see or compare another authors editorial revisions', function (bool $enabled): void {
    app(ModuleSettingsServiceInterface::class)->set('content_audit', 'is_enabled', $enabled);
    $post = Post::create(['user_id' => $this->admin->id, 'is_published' => false]);
    $revision = ContentAuditRevision::create(['auditable_type' => $post->getMorphClass(), 'auditable_id' => $post->id, 'user_id' => $this->admin->id, 'version' => 1, 'action' => 'updated', 'title' => 'CONFIDENTIAL-OTHER-AUTHOR', 'snapshot' => ['content' => 'private body']]);
    $this->actingAs($this->author)->get(route('admin.audit.index', ['tab' => 'changelog']))->assertOk()->assertDontSee('CONFIDENTIAL-OTHER-AUTHOR');
    $this->getJson(route('admin.audit.diff', ['revision' => $revision->id]))->assertForbidden();
})->with([true, false]);

test('revision comparisons must belong to the same entity', function (): void {
    $revisions = collect([1, 2])->map(function (int $version): ContentAuditRevision {
        $post = Post::create(['user_id' => $this->admin->id, 'is_published' => false]);

        return ContentAuditRevision::create(['auditable_type' => $post->getMorphClass(), 'auditable_id' => $post->id, 'user_id' => $this->admin->id, 'version' => $version, 'action' => 'updated', 'title' => 'Revision', 'snapshot' => []]);
    });
    $this->actingAs($this->admin)->getJson(route('admin.audit.diff', ['revision' => $revisions[0]->id, 'compare_with' => $revisions[1]->id]))->assertForbidden();
});

test('authors retain access to their own revisions without exposing settings', function (): void {
    $post = Post::create(['user_id' => $this->author->id, 'is_published' => false]);
    $revision = ContentAuditRevision::create(['auditable_type' => $post->getMorphClass(), 'auditable_id' => $post->id, 'user_id' => $this->author->id, 'version' => 1, 'action' => 'updated', 'title' => 'MY-PRIVATE-REVISION', 'snapshot' => ['content' => 'Own body']]);
    $this->actingAs($this->author)->get(route('admin.audit.index'))->assertOk()->assertSee('MY-PRIVATE-REVISION')->assertDontSee('action="'.route('admin.audit.settings').'"', false);
    $this->getJson(route('admin.audit.diff', ['revision' => $revision->id]))->assertOk();
});

test('selector authorization is rechecked when its permission is revoked', function (): void {
    $this->actingAs($this->author)->getJson(route('admin.api.entity-search', ['source' => 'blog_posts']))->assertOk();
    $this->author->roles()->firstOrFail()->permissions()->detach(Permission::where('slug', 'manage:posts')->value('id'));
    $this->getJson(route('admin.api.entity-search', ['source' => 'blog_posts']))->assertForbidden();
});

test('the dashboard hides leads and posts from accounts without their permissions', function (): void {
    $role = Role::create(['slug' => 'dashboard-only', 'name' => 'Dashboard only']);
    $role->permissions()->attach(Permission::where('slug', 'access:admin')->value('id'));
    $user = User::factory()->create();
    $user->roles()->attach($role);
    $response = $this->actingAs($user)->get(route('admin.dashboard'))->assertOk();
    $response->assertViewHas('hasForms', false)->assertViewHas('hasBlog', false);
    $response->assertViewHas('recentSubmissions', fn ($rows): bool => $rows->isEmpty());
    $response->assertViewHas('recentPosts', fn ($rows): bool => $rows->isEmpty());
    $response->assertViewHas('stats', fn (array $stats): bool => $stats['total_submissions'] === 0 && $stats['total_users'] === 0);
});

test('authors see only their own private articles in the dashboard', function (): void {
    $own = Post::create(['user_id' => $this->author->id, 'is_published' => false]);
    $own->translations()->create(['locale' => 'es', 'title' => 'My private draft', 'slug' => 'my-private-draft', 'content' => 'Own body']);
    $other = Post::create(['user_id' => $this->admin->id, 'is_published' => false]);
    $other->translations()->create(['locale' => 'es', 'title' => 'Other private draft', 'slug' => 'other-private-draft', 'content' => 'Other body']);
    $this->actingAs($this->author)->get(route('admin.dashboard'))->assertOk()->assertSee('My private draft')->assertDontSee('Other private draft')
        ->assertViewHas('recentSubmissions', fn ($rows): bool => $rows->isEmpty());
});

test('the dashboard respects scheduled publication while retaining the authors own item', function (): void {
    $own = Post::withoutEvents(fn (): Post => Post::create(['user_id' => $this->author->id, 'is_published' => true, 'published_at' => now()->addDay()]));
    $own->translations()->create(['locale' => 'es', 'title' => 'OWN SCHEDULED ITEM', 'slug' => 'own-scheduled-item', 'content' => 'Own embargoed body']);
    $other = Post::withoutEvents(fn (): Post => Post::create(['user_id' => $this->admin->id, 'is_published' => true, 'published_at' => now()->addDay()]));
    $other->translations()->create(['locale' => 'es', 'title' => 'OTHER EMBARGOED ITEM', 'slug' => 'other-embargoed-item', 'content' => 'Other embargoed body']);
    foreach ([$own, $other] as $post) {
        $post->forceFill(['created_at' => now()->addMinute(), 'is_published' => true, 'published_at' => now()->addDay()])->saveQuietly();
    }
    $this->actingAs($this->author)->get(route('admin.dashboard'))->assertOk()->assertSee('OWN SCHEDULED ITEM')->assertDontSee('OTHER EMBARGOED ITEM');
});

test('global agent audit logs require module administration at the transport and direct service', function (): void {
    mcpTestBindIdentity($this->author);
    $response = app(McpServerService::class)->handle(['jsonrpc' => '2.0', 'id' => 'private-logs', 'method' => 'tools/call', 'params' => ['name' => 'cms_mcp_audit', 'arguments' => []]]);
    $payload = json_decode((string) ($response['result']['content'][0]['text'] ?? ''), true);
    expect($payload['denied'] ?? false)->toBeTrue();
    $this->actingAs($this->author);
    app(ExecutionContext::class)->actor = null;
    try {
        app(CmsActionService::class)->auditLog([]);
        $this->fail('Direct audit access must require administration.');
    } catch (HttpException $error) {
        expect($error->getStatusCode())->toBe(403);
    }
});

test('administrators with read scopes can access the global agent audit log', function (): void {
    mcpTestBindIdentity($this->admin, ['mcp:access', 'mcp:read']);
    $response = app(McpServerService::class)->handle(['jsonrpc' => '2.0', 'id' => 'admin-logs', 'method' => 'tools/call', 'params' => ['name' => 'cms_mcp_audit', 'arguments' => []]]);
    $payload = json_decode((string) ($response['result']['content'][0]['text'] ?? ''), true);
    expect($payload)->toHaveKeys(['stats', 'entries'])->not->toHaveKey('denied');
});

test('administrative privileges do not substitute the OAuth read scope for audit access', function (): void {
    mcpTestBindIdentity($this->admin, ['mcp:access']);
    $response = app(McpServerService::class)->handle(['jsonrpc' => '2.0', 'id' => 'missing-scope', 'method' => 'tools/call', 'params' => ['name' => 'cms_mcp_audit', 'arguments' => []]]);
    $payload = json_decode((string) ($response['result']['content'][0]['text'] ?? ''), true);
    expect($payload['denied'] ?? false)->toBeTrue();
});
