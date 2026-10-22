<?php

declare(strict_types=1);

use App\Contracts\Services\ModuleSettingsServiceInterface;
use App\Core\Modules\ModuleManager;
use App\Models\Language;
use App\Models\Market;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Cms\PageCacheService;
use Illuminate\Support\Facades\Cache;
use Modules\Blog\Models\Post;
use Modules\Blog\Models\PostTranslation;
use Modules\ContentAudit\Enums\AuditStatus;
use Modules\ContentAudit\Services\AuditRevisionService;
use Modules\ContentAudit\Services\EditorialWorkflow;
use Tests\Support\BlogAuditComposition;

uses(BlogAuditComposition::class);

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

test('approving pending content invalidates the public page cache', function (): void {
    $author = User::factory()->create();
    $adminRole = Role::where('slug', 'admin')->first();
    $auditor = User::factory()->create();
    $auditor->roles()->sync([$adminRole->id]);

    $post = Post::create(['user_id' => $author->id, 'is_published' => false]);
    PostTranslation::create([
        'post_id' => $post->id,
        'locale' => 'es',
        'title' => 'Post con caché que invalidar',
        'slug' => 'post-con-cache-que-invalidar',
        'content' => 'Contenido de prueba',
        'schema_type' => 'TechArticle',
    ]);

    /** @var EditorialWorkflow $auditService */
    $auditService = app(EditorialWorkflow::class);
    $auditService->submitForReview($post, $author);

    // Seed the reverse index with a cached page, then approve: the editorial
    // decision must drop both the registered page and the dependency index.
    // Keys are derived from the real post id (InnoDB does not rewind
    // AUTO_INCREMENT when transactions roll back between tests).
    $depKey = PageCacheService::DEP_PREFIX.$post->getTable().':'.$post->id;
    $pageKey = "cms:page:{$post->id}:abc";
    Cache::put($depKey, [$pageKey => true], 60);
    Cache::put($pageKey, '<html>cached</html>', 60);

    $auditService->approve($post, $auditor);

    expect(Cache::get($depKey))->toBeNull()
        ->and(Cache::get($pageKey))->toBeNull()
        ->and(app(AuditRevisionService::class)->auditFor($post)?->status)->toBe(AuditStatus::APPROVED->value);
});

test('rejecting pending content invalidates the public page cache', function (): void {
    $author = User::factory()->create();
    $adminRole = Role::where('slug', 'admin')->first();
    $auditor = User::factory()->create();
    $auditor->roles()->sync([$adminRole->id]);

    $post = Post::create(['user_id' => $author->id, 'is_published' => false]);
    PostTranslation::create([
        'post_id' => $post->id,
        'locale' => 'es',
        'title' => 'Post rechazado',
        'slug' => 'post-rechazado',
        'content' => 'Contenido de prueba',
        'schema_type' => 'TechArticle',
    ]);

    /** @var EditorialWorkflow $auditService */
    $auditService = app(EditorialWorkflow::class);
    $auditService->submitForReview($post, $author);

    // Seed the reverse index with a cached page, then reject: the editorial
    // decision must drop both the registered page and the dependency index.
    $depKey = PageCacheService::DEP_PREFIX.$post->getTable().':'.$post->id;
    $pageKey = "cms:page:{$post->id}:xyz";
    Cache::put($depKey, [$pageKey => true], 60);
    Cache::put($pageKey, '<html>cached</html>', 60);

    $auditService->reject($post, $auditor, 'Corregir fuentes antes de publicar.');

    expect(Cache::get($depKey))->toBeNull()
        ->and(Cache::get($pageKey))->toBeNull()
        ->and(app(AuditRevisionService::class)->auditFor($post)?->status)->toBe(AuditStatus::REJECTED->value);
});
