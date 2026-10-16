<?php

declare(strict_types=1);

use App\Contracts\Services\ModuleSettingsServiceInterface;
use App\Core\Capabilities\Contracts\ContentRevisionInspectorInterface;
use App\Core\Modules\ModuleManager;
use App\Models\Language;
use App\Models\Market;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Capabilities\EntityMutationService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\Post;
use Modules\Blog\Services\PostDomainService;
use Modules\ContentAudit\Models\ContentAudit;
use Tests\Support\BlogAuditComposition;

uses(BlogAuditComposition::class);

test('disabling review stops old pending snapshots from replacing subsequent live edits', function (string $switch): void {
    $admin = User::factory()->create();
    $admin->roles()->attach(Role::where('slug', 'admin')->firstOrFail());
    $this->actingAs($admin);
    $entities = app(EntityMutationService::class);
    $created = $entities->create('post', ['publish' => true, 'translations' => ['es' => ['title' => 'Original live copy']]], $admin);
    $post = Post::findOrFail($created['id']);
    $pending = $entities->update('post', $post->id, ['translations' => ['es' => ['title' => 'Old pending copy']]], $admin);
    expect($pending['live_preserved'])->toBeTrue();
    if ($switch === 'module') {
        app(ModuleManager::class)->deactivate('content_audit');
    } else {
        app(ModuleSettingsServiceInterface::class)->set('content_audit', 'is_enabled', false);
    }
    $entities->update('post', $post->id, ['translations' => ['es' => ['title' => 'Current live edit']]], $admin);
    $editor = app(PostDomainService::class)->editData($post->fresh())['post'];

    expect($editor->translations->firstWhere('locale', 'es')->title)->toBe('Current live edit');
    expect(app(ContentRevisionInspectorInterface::class)->fingerprint($post))->toBeNull();
    expect(ContentAudit::where('auditable_type', $post->getMorphClass())->where('auditable_id', $post->id)->value('pending_revision_id'))->toBe($pending['pending_revision_id']);
})->with(['module', 'flow']);

beforeEach(function (): void {
    $spanish = Language::firstOrCreate(
        ['code' => 'es'],
        ['name' => 'Spanish', 'native_name' => 'Español', 'is_default' => true, 'is_active' => true]
    );

    $market = Market::firstOrCreate(
        ['code' => 'es'],
        ['name' => 'España', 'default_locale' => 'es', 'currency' => 'EUR', 'is_default' => true, 'is_active' => true]
    );

    $market->languages()->syncWithoutDetaching([$spanish->id => ['is_default' => true]]);

    $managePosts = Permission::firstOrCreate(['slug' => 'manage:posts'], ['name' => 'Manage posts']);
    $accessAdmin = Permission::firstOrCreate(['slug' => 'access:admin'], ['name' => 'Administrative access']);
    $adminRole = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Administrador']);
    $authorRole = Role::firstOrCreate(['slug' => 'author'], ['name' => 'Autor']);

    $adminRole->permissions()->syncWithoutDetaching([$managePosts->id, $accessAdmin->id]);
    $authorRole->permissions()->syncWithoutDetaching([$managePosts->id, $accessAdmin->id]);

    app(ModuleManager::class)->discoverModules();
});

test('disabling the audit flow keeps the audit backoffice reachable', function (): void {
    $adminRole = Role::where('slug', 'admin')->first();
    $admin = User::factory()->create();
    $admin->roles()->sync([$adminRole->id]);

    $settings = app(ModuleSettingsServiceInterface::class);
    $settings->set('content_audit', 'is_enabled', false);

    $response = $this->actingAs($admin)->get(route('admin.audit.index'));

    // The flow toggle must never lock the module out of its own UI.
    $response->assertStatus(200);
});

test('disabling the audit flow stops observer interference entirely', function (): void {
    $adminRole = Role::where('slug', 'admin')->first();
    $authorRole = Role::where('slug', 'author')->first();
    $author = User::factory()->create();
    $author->roles()->sync([$authorRole->id]);

    $settings = app(ModuleSettingsServiceInterface::class);
    $settings->set('content_audit', 'is_enabled', false);
    $settings->set('content_audit', 'audited_roles', ['author']);
    $settings->set('content_audit', 'audited_entities', ['posts', 'pages', 'products']);

    $category = Category::create();

    $response = $this->actingAs($author)
        ->withoutMiddleware(ValidateCsrfToken::class)
        ->post(route('admin.blog.posts.store'), [
            'translations' => ['es' => ['title' => 'Sin auditoría activa', 'content' => 'Contenido publicado directamente']],
            'category_id' => $category->id,
            'is_published' => 1,
        ]);

    $response->assertRedirect(route('admin.blog.posts.index'));

    $post = Post::latest('id')->first();
    expect($post)->not->toBeNull()
        ->and((bool) $post->is_published)->toBeTrue();

    expect(ContentAudit::where('auditable_type', $post->getMorphClass())
        ->where('auditable_id', $post->id)
        ->exists())->toBeFalse();
});
