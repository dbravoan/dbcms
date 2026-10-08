<?php

declare(strict_types=1);

use App\Contracts\Services\ModuleSettingsServiceInterface;
use App\Core\Modules\ModuleManager;
use App\Models\Language;
use App\Models\Market;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\Post;
use Modules\ContentAudit\Contracts\Services\ContentAuditServiceInterface;
use Modules\ContentAudit\Enums\AuditStatus;
use Modules\ContentAudit\Models\ContentAudit;

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

    $settings = app(ModuleSettingsServiceInterface::class);
    $settings->set('content_audit', 'is_enabled', true);
    $settings->set('content_audit', 'audited_roles', ['author']);
    $settings->set('content_audit', 'approver_roles', ['admin', 'editor']);
    $settings->set('content_audit', 'audited_entities', ['posts', 'pages', 'products']);
});

test('restricted author saving a draft does not submit content for review', function (): void {
    $authorRole = Role::where('slug', 'author')->first();
    $author = User::factory()->create();
    $author->roles()->sync([$authorRole->id]);

    $category = Category::create();

    $response = $this->actingAs($author)
        ->withoutMiddleware(ValidateCsrfToken::class)
        ->post(route('admin.blog.posts.store'), [
            'translations' => ['es' => ['title' => 'Borrador sin revisión', 'content' => 'Contenido del borrador']],
            'category_id' => $category->id,
            'is_published' => 1,
            'audit_action' => 'draft',
        ]);

    $response->assertRedirect(route('admin.blog.posts.index'));

    $post = Post::latest('id')->first();
    expect($post)->not->toBeNull()
        ->and((bool) $post->is_published)->toBeFalse();

    /** @var ContentAuditServiceInterface $auditService */
    $auditService = app(ContentAuditServiceInterface::class);
    $audit = ContentAudit::where('auditable_type', $post->getMorphClass())
        ->where('auditable_id', $post->id)
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->status)->toBe(AuditStatus::DRAFT->value);
});

test('restricted author submitting for review queues the content', function (): void {
    $authorRole = Role::where('slug', 'author')->first();
    $author = User::factory()->create();
    $author->roles()->sync([$authorRole->id]);

    $category = Category::create();

    $response = $this->actingAs($author)
        ->withoutMiddleware(ValidateCsrfToken::class)
        ->post(route('admin.blog.posts.store'), [
            'translations' => ['es' => ['title' => 'Borrador a revisión', 'content' => 'Contenido que sí va a revisión']],
            'category_id' => $category->id,
            'is_published' => 1,
            'audit_action' => 'submit_for_review',
        ]);

    $response->assertRedirect(route('admin.blog.posts.index'));

    $post = Post::latest('id')->first();
    $audit = ContentAudit::where('auditable_type', $post->getMorphClass())
        ->where('auditable_id', $post->id)
        ->first();

    expect($audit->status)->toBe(AuditStatus::PENDING_REVIEW->value);
});

test('saving as draft takes pending content out of the review queue', function (): void {
    $authorRole = Role::where('slug', 'author')->first();
    $author = User::factory()->create();
    $author->roles()->sync([$authorRole->id]);

    $category = Category::create();

    $this->actingAs($author)
        ->withoutMiddleware(ValidateCsrfToken::class)
        ->post(route('admin.blog.posts.store'), [
            'translations' => ['es' => ['title' => 'Contenido pendiente', 'content' => 'Texto enviado a revisión inicialmente']],
            'category_id' => $category->id,
            'is_published' => 1,
            'audit_action' => 'submit_for_review',
        ]);

    $post = Post::latest('id')->first();

    /** @var ContentAuditServiceInterface $auditService */
    $auditService = app(ContentAuditServiceInterface::class);
    expect($auditService->getAudit($post)?->status)->toBe(AuditStatus::PENDING_REVIEW->value);

    $this->actingAs($author)
        ->withoutMiddleware(ValidateCsrfToken::class)
        ->put(route('admin.blog.posts.update', $post), [
            'translations' => ['es' => ['title' => 'Vuelvo a borrador', 'content' => 'Texto guardado como borrador']],
            'category_id' => $category->id,
            'is_published' => 0,
            'audit_action' => 'draft',
        ]);

    expect($auditService->getAudit($post)?->status)->toBe(AuditStatus::DRAFT->value);
});
