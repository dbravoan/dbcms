<?php

declare(strict_types=1);

use App\Contracts\Services\ModuleSettingsServiceInterface;
use App\Core\Modules\ModuleManager;
use App\Models\Language;
use App\Models\Market;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Capabilities\EntityCrudService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Event;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\Post;
use Modules\Blog\Models\PostTranslation;
use Modules\ContentAudit\Contracts\Services\ContentAuditServiceInterface;
use Modules\ContentAudit\Contracts\Services\DiffEngineServiceInterface;
use Modules\ContentAudit\Enums\AuditAction;
use Modules\ContentAudit\Enums\AuditStatus;
use Modules\ContentAudit\Events\ContentApprovedEvent;
use Modules\ContentAudit\Events\ContentRejectedEvent;
use Modules\ContentAudit\Models\ContentAudit;
use Modules\ContentAudit\Models\ContentAuditRevision;
use Modules\Shop\Models\Product;
use Modules\Shop\Models\ProductTranslation;

beforeEach(function (): void {
    // Seed language and market
    $spanish = Language::firstOrCreate(
        ['code' => 'es'],
        ['name' => 'Spanish', 'native_name' => 'Español', 'is_default' => true, 'is_active' => true]
    );

    $market = Market::firstOrCreate(
        ['code' => 'es'],
        ['name' => 'España', 'default_locale' => 'es', 'currency' => 'EUR', 'is_default' => true, 'is_active' => true]
    );

    $market->languages()->syncWithoutDetaching([$spanish->id => ['is_default' => true]]);

    // Seed roles and permissions
    $managePosts = Permission::firstOrCreate(['slug' => 'manage:posts'], ['name' => 'Manage posts']);
    $accessAdmin = Permission::firstOrCreate(['slug' => 'access:admin'], ['name' => 'Administrative access']);
    $adminRole = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Administrador']);
    $editorRole = Role::firstOrCreate(['slug' => 'editor'], ['name' => 'Editor']);
    $authorRole = Role::firstOrCreate(['slug' => 'author'], ['name' => 'Autor']);
    Role::firstOrCreate(['slug' => 'marketing'], ['name' => 'Marketing']);

    $adminRole->permissions()->syncWithoutDetaching([$managePosts->id, $accessAdmin->id]);
    $editorRole->permissions()->syncWithoutDetaching([$managePosts->id, $accessAdmin->id]);
    $authorRole->permissions()->syncWithoutDetaching([$managePosts->id, $accessAdmin->id]);

    // Ensure modules are discovered and synced to database
    app(ModuleManager::class)->discoverModules();

    // Ensure settings
    $settings = app(ModuleSettingsServiceInterface::class);
    $settings->set('content_audit', 'is_enabled', true);
    $settings->set('content_audit', 'audited_roles', ['author']);
    $settings->set('content_audit', 'approver_roles', ['admin', 'editor']);
    $settings->set('content_audit', 'audited_entities', ['posts', 'pages', 'products']);
    $settings->set('content_audit', 'auto_publish_on_approval', true);
    $settings->set('content_audit', 'track_all_modifications', true);
    $settings->set('content_audit', 'require_rejection_reason', true);
    $settings->set('content_audit', 'notify_in_app', true);
});

test('content audit module is discovered by ModuleManager', function (): void {
    /** @var ModuleManager $manager */
    $manager = app(ModuleManager::class);
    $discovered = $manager->discoverModules();

    expect($discovered)->toHaveKey('content_audit');
    $module = $discovered['content_audit'];

    expect($module['name'])->toBe('Auditoría de Contenidos & Control de Calidad')
        ->and($module['version'])->toBe('1.0.0');

    /** @var ContentAuditServiceInterface $auditService */
    $auditService = app(ContentAuditServiceInterface::class);
    expect($auditService->isEnabled())->toBeTrue();
});

test('restricted author cannot publish directly and content requires review', function (): void {
    /** @var Role $authorRole */
    $authorRole = Role::where('slug', 'author')->first();
    $author = User::factory()->create();
    $author->roles()->sync([$authorRole->id]);

    /** @var ContentAuditServiceInterface $auditService */
    $auditService = app(ContentAuditServiceInterface::class);

    expect($auditService->canPublishDirectly($author))->toBeFalse()
        ->and($auditService->requiresAudit(new Post, $author))->toBeTrue();

    $category = Category::create();

    $response = $this->actingAs($author)
        ->withoutMiddleware(ValidateCsrfToken::class)
        ->post(route('admin.blog.posts.store'), [
            'translations' => ['es' => ['title' => 'Artículo para revisión técnica', 'content' => 'Contenido redactado por el autor restringido']],
            'category_id' => $category->id,
            'is_published' => 1, // Attempting to publish directly
            'audit_action' => 'submit_for_review',
            'audit_notes' => 'Por favor validar redacción',
        ]);

    $response->assertRedirect(route('admin.blog.posts.index'));

    $post = Post::latest('id')->first();
    expect($post)->not->toBeNull()
        ->and((bool) $post->is_published)->toBeFalse();

    $audit = ContentAudit::where('auditable_type', $post->getMorphClass())
        ->where('auditable_id', $post->id)
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->status)->toBe(AuditStatus::PENDING_REVIEW->value)
        ->and($audit->submitted_by)->toBe($author->id)
        ->and($audit->notes)->toBe('Por favor validar redacción');
});

test('admin can publish directly without review requirement', function (): void {
    /** @var Role $adminRole */
    $adminRole = Role::where('slug', 'admin')->first();
    $admin = User::factory()->create();
    $admin->roles()->sync([$adminRole->id]);

    /** @var ContentAuditServiceInterface $auditService */
    $auditService = app(ContentAuditServiceInterface::class);

    expect($auditService->canPublishDirectly($admin))->toBeTrue()
        ->and($auditService->requiresAudit(new Post, $admin))->toBeFalse();

    $category = Category::create();

    $response = $this->actingAs($admin)
        ->withoutMiddleware(ValidateCsrfToken::class)
        ->post(route('admin.blog.posts.store'), [
            'translations' => ['es' => ['title' => 'Artículo Directo por Admin', 'content' => 'Contenido directo sin pasar por auditoría']],
            'category_id' => $category->id,
            'is_published' => 1,
        ]);

    $response->assertRedirect(route('admin.blog.posts.index'));

    $post = Post::latest('id')->first();
    expect($post)->not->toBeNull()
        ->and((bool) $post->is_published)->toBeTrue();
});

test('auditor can approve pending content and trigger auto-publishing', function (): void {
    Event::fake([ContentApprovedEvent::class]);

    $author = User::factory()->create();
    $auditor = User::factory()->create();
    $adminRole = Role::where('slug', 'admin')->first();
    $auditor->roles()->sync([$adminRole->id]);

    $post = Post::create([
        'user_id' => $author->id,
        'is_published' => false,
    ]);
    PostTranslation::create([
        'post_id' => $post->id,
        'locale' => 'es',
        'title' => 'Post en espera de aprobación',
        'slug' => 'post-en-espera-de-aprobacion',
        'content' => 'Contenido de prueba',
        'schema_type' => 'TechArticle',
    ]);

    /** @var ContentAuditServiceInterface $auditService */
    $auditService = app(ContentAuditServiceInterface::class);
    $auditService->submitForReview($post, $author, 'Revisar ortografía');

    $review = $this->actingAs($auditor)->get(route('admin.audit.review', ['type' => 'posts', 'id' => $post->id]));
    $response = $this->actingAs($auditor)
        ->withoutMiddleware(ValidateCsrfToken::class)
        ->post(route('admin.audit.approve', ['type' => 'posts', 'id' => $post->id]), [
            'review_token' => $review->viewData('reviewToken'),
            'notes' => 'Aprobado con excelencia técnica.',
        ]);

    $response->assertRedirect(route('admin.audit.index'));

    $post->refresh();
    expect((bool) $post->is_published)->toBeTrue();

    $audit = $auditService->getAudit($post);
    expect($audit)->not->toBeNull()
        ->and($audit->status)->toBe(AuditStatus::APPROVED->value)
        ->and($audit->reviewed_by)->toBe($auditor->id)
        ->and($audit->notes)->toBe('Aprobado con excelencia técnica.');

    Event::assertDispatched(ContentApprovedEvent::class);
});

test('auditor can reject pending content with required reason', function (): void {
    Event::fake([ContentRejectedEvent::class]);

    $author = User::factory()->create();
    $auditor = User::factory()->create();
    $adminRole = Role::where('slug', 'admin')->first();
    $auditor->roles()->sync([$adminRole->id]);

    $post = Post::create([
        'user_id' => $author->id,
        'is_published' => false,
    ]);
    PostTranslation::create([
        'post_id' => $post->id,
        'locale' => 'es',
        'title' => 'Post con errores para rechazar',
        'slug' => 'post-con-errores-para-rechazar',
        'content' => 'Contenido inicial',
        'schema_type' => 'TechArticle',
    ]);

    /** @var ContentAuditServiceInterface $auditService */
    $auditService = app(ContentAuditServiceInterface::class);
    $auditService->submitForReview($post, $author);

    // Validation failure: missing reason
    $failResponse = $this->actingAs($auditor)
        ->withoutMiddleware(ValidateCsrfToken::class)
        ->post(route('admin.audit.reject', ['type' => 'posts', 'id' => $post->id]), [
            'rejection_reason' => '',
        ]);
    $failResponse->assertSessionHasErrors(['rejection_reason']);

    // Valid rejection is bound to the revision shown to this auditor.
    $review = $this->actingAs($auditor)->get(route('admin.audit.review', ['type' => 'posts', 'id' => $post->id]));
    $response = $this->actingAs($auditor)
        ->withoutMiddleware(ValidateCsrfToken::class)
        ->post(route('admin.audit.reject', ['type' => 'posts', 'id' => $post->id]), [
            'review_token' => $review->viewData('reviewToken'),
            'rejection_reason' => 'Faltan fuentes bibliográficas en la sección técnica.',
        ]);

    $response->assertRedirect(route('admin.audit.index'));

    $post->refresh();
    expect((bool) $post->is_published)->toBeFalse();

    $audit = $auditService->getAudit($post);
    expect($audit)->not->toBeNull()
        ->and($audit->status)->toBe(AuditStatus::REJECTED->value)
        ->and($audit->rejection_reason)->toBe('Faltan fuentes bibliográficas en la sección técnica.')
        ->and($audit->reviewed_by)->toBe($auditor->id);

    Event::assertDispatched(ContentRejectedEvent::class);
});

test('diff engine computes differences between content revisions', function (): void {
    $author = User::factory()->create();

    $post = Post::create([
        'user_id' => $author->id,
        'is_published' => false,
    ]);
    PostTranslation::create([
        'post_id' => $post->id,
        'locale' => 'es',
        'title' => 'Versión Inicial',
        'slug' => 'version-inicial',
        'content' => "Primera línea de contenido.\nSegunda línea sin cambios.",
        'schema_type' => 'TechArticle',
    ]);

    /** @var ContentAuditServiceInterface $auditService */
    $auditService = app(ContentAuditServiceInterface::class);
    $rev1 = $auditService->recordChange($post, $author, AuditAction::CREATED->value);

    // Make changes to post translation
    $translation = $post->translations()->where('locale', 'es')->first();
    $translation->update([
        'title' => 'Versión Modificada',
        'content' => "Primera línea modificada.\nSegunda línea sin cambios.\nTercera línea nueva.",
    ]);

    $rev2 = $auditService->recordChange($post, $author, AuditAction::UPDATED->value);

    /** @var DiffEngineServiceInterface $diffEngine */
    $diffEngine = app(DiffEngineServiceInterface::class);
    $diff = $diffEngine->computeDiff($rev1->snapshot, $rev2->snapshot);

    expect($diff->hasChanges)->toBeTrue()
        ->and($diff->fieldDiffs)->toHaveKey('es.title')
        ->and($diff->textDiffs)->toHaveKey('es.content');
});

test('rollback restores historical snapshot version', function (): void {
    $author = User::factory()->create();
    $auditor = User::factory()->create();
    $adminRole = Role::where('slug', 'admin')->first();
    $auditor->roles()->sync([$adminRole->id]);

    $post = Post::create([
        'user_id' => $author->id,
        'is_published' => false,
    ]);
    PostTranslation::create([
        'post_id' => $post->id,
        'locale' => 'es',
        'title' => 'Título Original Seguro',
        'slug' => 'titulo-original-seguro',
        'content' => 'Contenido original inmutable',
        'schema_type' => 'TechArticle',
    ]);

    /** @var ContentAuditServiceInterface $auditService */
    $auditService = app(ContentAuditServiceInterface::class);
    $rev1 = $auditService->recordChange($post, $author, AuditAction::CREATED->value);

    // Change title
    $post->translations()->where('locale', 'es')->update([
        'title' => 'Título Dañado o No Deseado',
    ]);
    $rev2 = $auditService->recordChange($post, $author, AuditAction::UPDATED->value);

    // Perform rollback using the exact historical choice shown in this review.
    $review = $this->actingAs($auditor)->get(route('admin.audit.review', ['type' => 'posts', 'id' => $post->id]));
    $response = $this->actingAs($auditor)
        ->from(route('admin.audit.review', ['type' => 'posts', 'id' => $post->id]))
        ->withoutMiddleware(ValidateCsrfToken::class)
        ->post(route('admin.audit.rollback', ['revision' => $rev1->id]), ['review_token' => $review->viewData('rollbackTokens')[$rev1->id]]);

    $response->assertRedirect(route('admin.audit.review', ['type' => 'posts', 'id' => $post->id]));

    $post->refresh();
    $restoredTranslation = $post->translations()->where('locale', 'es')->first();
    expect($restoredTranslation->title)->toBe('Título Original Seguro');

    $latestRev = ContentAuditRevision::where('auditable_type', $post->getMorphClass())
        ->where('auditable_id', $post->id)
        ->orderByDesc('version')
        ->first();

    expect($latestRev->action)->toBe(AuditAction::ROLLBACK->value);
});

test('admin can update content audit module settings', function (): void {
    $admin = User::factory()->create();
    $adminRole = Role::where('slug', 'admin')->first();
    $admin->roles()->sync([$adminRole->id]);

    $response = $this->actingAs($admin)
        ->withoutMiddleware(ValidateCsrfToken::class)
        ->post(route('admin.audit.settings'), [
            'is_enabled' => 1,
            'audited_roles' => ['author', 'marketing'],
            'approver_roles' => ['admin', 'editor'],
            'audited_entities' => ['posts', 'pages', 'products'],
            'auto_publish_on_approval' => 1,
            'track_all_modifications' => 1,
            'require_rejection_reason' => 1,
            'notify_in_app' => 1,
            'notify_email' => 0,
        ]);

    $response->assertRedirect(route('admin.audit.index', ['tab' => 'settings']));

    $settings = app(ModuleSettingsServiceInterface::class);
    expect($settings->get('content_audit', 'is_enabled'))->toBeTrue()
        ->and($settings->get('content_audit', 'audited_roles'))->toContain('marketing');
});

test('describeEntity is strict-mode safe across entity shapes', function (): void {
    /** @var ContentAuditServiceInterface $service */
    $service = app(ContentAuditServiceInterface::class);

    $author = User::factory()->create();

    $post = Post::create(['user_id' => $author->id, 'is_published' => true]);
    PostTranslation::create([
        'post_id' => $post->id,
        'locale' => 'es',
        'title' => 'Artículo Descrito',
        'slug' => 'articulo-descrito',
        'content' => 'Contenido',
        'schema_type' => 'TechArticle',
    ]);

    $postDescription = $service->describeEntity($post->fresh('translations'));
    expect($postDescription['title'])->toBe('Artículo Descrito')
        ->and($postDescription['is_published'])->toBeTrue();

    // Products expose `name`/`is_active` and no `title`/`content` columns.
    $product = Product::create(['sku' => 'SKU-DESC-1', 'price' => 19.99, 'is_active' => false]);
    ProductTranslation::create([
        'product_id' => $product->id,
        'locale' => 'es',
        'name' => 'Producto Descrito',
        'slug' => 'producto-descrito',
        'description' => 'Descripción del producto',
    ]);

    $productDescription = $service->describeEntity($product->fresh('translations'));
    expect($productDescription['title'])->toBe('Producto Descrito')
        ->and($productDescription['is_published'])->toBeFalse();
});

test('quality review page renders safely for posts and products', function (): void {
    $admin = User::factory()->create();
    $adminRole = Role::where('slug', 'admin')->first();
    $admin->roles()->sync([$adminRole->id]);
    $author = User::factory()->create();

    $post = Post::create(['user_id' => $author->id, 'is_published' => true]);
    PostTranslation::create([
        'post_id' => $post->id,
        'locale' => 'es',
        'title' => 'Artículo Revisable',
        'slug' => 'articulo-revisable',
        'content' => 'Contenido del artículo',
        'schema_type' => 'TechArticle',
    ]);

    $postResponse = $this->actingAs($admin)
        ->get(route('admin.audit.review', ['type' => 'posts', 'id' => $post->id]));
    $postResponse->assertOk();
    $postResponse->assertSee('Artículo Revisable');

    $product = Product::create(['sku' => 'SKU-REVIEW-1', 'price' => 19.99, 'is_active' => false]);
    ProductTranslation::create([
        'product_id' => $product->id,
        'locale' => 'es',
        'name' => 'Producto Revisable',
        'slug' => 'producto-revisable',
        'description' => 'Descripción del producto',
    ]);

    $productResponse = $this->actingAs($admin)
        ->get(route('admin.audit.review', ['type' => 'products', 'id' => $product->id]));
    $productResponse->assertOk();
    $productResponse->assertSee('Producto Revisable');
});

test('quality queue index lists posts and products without tripping strict mode', function (): void {
    $admin = User::factory()->create();
    $adminRole = Role::where('slug', 'admin')->first();
    $admin->roles()->sync([$adminRole->id]);
    $author = User::factory()->create();

    /** @var ContentAuditServiceInterface $service */
    $service = app(ContentAuditServiceInterface::class);

    $post = Post::create(['user_id' => $author->id, 'is_published' => false]);
    PostTranslation::create([
        'post_id' => $post->id,
        'locale' => 'es',
        'title' => 'Post En Cola',
        'slug' => 'post-en-cola',
        'content' => 'Contenido',
        'schema_type' => 'TechArticle',
    ]);
    $service->submitForReview($post, $author);

    $product = Product::create(['sku' => 'SKU-QUEUE-1', 'price' => 9.99, 'is_active' => true]);
    ProductTranslation::create([
        'product_id' => $product->id,
        'locale' => 'es',
        'name' => 'Producto En Cola',
        'slug' => 'producto-en-cola',
        'description' => 'Descripción',
    ]);
    $service->submitForReview($product, $author);

    $response = $this->actingAs($admin)
        ->get(route('admin.audit.index', ['status' => 'all']));

    $response->assertOk();
    $response->assertSee('Post En Cola');
    $response->assertSee('Producto En Cola');
});

test('legacy approval binds the pending revision that the auditor actually viewed', function (): void {
    $auditor = User::factory()->create();
    $auditor->roles()->sync([Role::where('slug', 'admin')->firstOrFail()->getKey()]);
    $this->actingAs($auditor);
    $crud = app(EntityCrudService::class);
    $created = $crud->create('post', ['publish' => true, 'translations' => ['es' => ['title' => 'Public version', 'content' => 'Public body']]], $auditor);
    $crud->update('post', $created['id'], ['translations' => ['es' => ['title' => 'Pending version A']]]);
    $reviewRoute = route('admin.audit.review', ['type' => 'posts', 'id' => $created['id']]);
    $approveRoute = route('admin.audit.approve', ['type' => 'posts', 'id' => $created['id']]);
    $review = $this->get($reviewRoute)->assertOk()->assertSee('Pending version A');
    $token = $review->viewData('reviewToken');
    $crud->update('post', $created['id'], ['translations' => ['es' => ['title' => 'Pending version B']]]);
    $this->post($approveRoute, ['review_token' => $token])->assertConflict();
    expect(Post::findOrFail($created['id'])->translations->first()->title)->toBe('Public version');
    $current = $this->get($reviewRoute)->assertOk()->assertSee('Pending version B');
    $this->post($approveRoute, ['review_token' => $current->viewData('reviewToken')])->assertRedirect(route('admin.audit.index'));
    expect(Post::findOrFail($created['id'])->translations->first()->title)->toBe('Pending version B');
    $this->post($approveRoute, ['review_token' => $current->viewData('reviewToken')])->assertConflict();
});

test('legacy approval rejects absent forged expired or another auditors binding', function (): void {
    $auditor = User::factory()->create();
    $other = User::factory()->create();
    $role = Role::where('slug', 'admin')->firstOrFail();
    $auditor->roles()->sync([$role->getKey()]);
    $other->roles()->sync([$role->getKey()]);
    $this->actingAs($auditor);
    $crud = app(EntityCrudService::class);
    $created = $crud->create('post', ['translations' => ['es' => ['title' => 'Awaiting review', 'content' => 'Draft body']]], $auditor);
    $approveRoute = route('admin.audit.approve', ['type' => 'posts', 'id' => $created['id']]);
    $review = $this->get(route('admin.audit.review', ['type' => 'posts', 'id' => $created['id']]))->assertOk();
    $token = $review->viewData('reviewToken');
    $this->postJson($approveRoute, [])->assertUnprocessable()->assertJsonValidationErrors('review_token');
    $this->postJson($approveRoute, ['review_token' => 'forged'])->assertUnprocessable()->assertJsonValidationErrors('review_token');
    $this->actingAs($other)->post($approveRoute, ['review_token' => $token])->assertConflict();
    $this->actingAs($auditor);
    $this->travel(31)->minutes();
    $this->post($approveRoute, ['review_token' => $token])->assertConflict();
    expect(Post::findOrFail($created['id'])->is_published)->toBeFalse();
});
