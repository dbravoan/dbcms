<?php

declare(strict_types=1);

use App\Core\Capabilities\AvailableCapabilities;
use App\Core\Capabilities\CapabilityRegistry;
use App\Core\Content\Contracts\EntitySchemaRegistryInterface;
use App\Core\Sections\SectionEditorService;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemNotification;
use App\Models\User;
use App\Services\Capabilities\ActorContextFactory;
use App\Services\Capabilities\LegacyCmsService;
use Illuminate\Database\Eloquent\Model;
use Modules\Blog\Models\Post;
use Modules\Blog\Schema\BlogEntitySchemas;
use Modules\ContentAudit\Contracts\Services\ContentAuditServiceInterface;
use Modules\Mcp\Services\EntityCrudService;
use Modules\Pages\Models\Page;
use Modules\Pages\Schema\PageEntitySchemas;
use Modules\Shop\Models\Product;
use Modules\Shop\Schema\ShopEntitySchemas;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function (): void {
    foreach (array_merge(BlogEntitySchemas::all(), PageEntitySchemas::all(), ShopEntitySchemas::all()) as $schema) {
        app(EntitySchemaRegistryInterface::class)->register($schema);
    }
    $this->other = User::factory()->create();
    $this->actor = User::factory()->create();
    $admin = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
    $this->actor->roles()->attach($admin->getKey());
    $this->actingAs($this->actor);
});

test('legacy create post uses the current actor and saves a draft with its body', function (): void {
    $result = app(LegacyCmsService::class)->execute('cms_create_post', ['title' => 'Actor draft', 'content' => 'Body retained', 'locale' => 'es']);
    $post = Post::findOrFail($result['post_id']);
    expect($post->user_id)->toBe($this->actor->getKey());
    expect($post->is_published)->toBeFalse();
    expect($post->translations->first()->content)->toBe('Body retained');
});

test('legacy create page saves a draft while the publishing adapter explicitly publishes', function (): void {
    $legacy = app(LegacyCmsService::class);
    $draft = $legacy->execute('cms_create_page', ['title' => 'Draft page', 'slug' => 'draft-page']);
    expect(Page::findOrFail($draft['page_id'])->is_published)->toBeFalse();
    $published = $legacy->execute('cms_publish_page', ['title' => 'Published page', 'slug' => 'published-page']);
    expect(Page::findOrFail($published['page_id'])->is_published)->toBeTrue();
});

test('generic create prevents forged authors and defaults products to a valid draft', function (): void {
    $crud = app(EntityCrudService::class);
    $post = $crud->create('post', ['attributes' => ['user_id' => $this->other->getKey()], 'translations' => ['es' => ['title' => 'Real author', 'content' => 'Body']]], $this->actor);
    expect(Post::findOrFail($post['id'])->user_id)->toBe($this->actor->getKey());
    expect(Post::findOrFail($post['id'])->translations->first()->content)->toBe('Body');
    $product = $crud->create('product', ['translations' => ['es' => ['name' => 'Draft product']]], $this->actor);
    $model = Product::findOrFail($product['id']);
    expect($model->is_active)->toBeFalse();
    expect($model->sku)->toStartWith('DRAFT-');
});

test('entity section actions do not derive content access from general panel access', function (): void {
    $visitor = User::factory()->create();
    $role = Role::create(['slug' => 'panel-only', 'name' => 'Panel only']);
    $permission = Permission::firstOrCreate(['slug' => 'access:admin'], ['name' => 'Panel access']);
    $role->permissions()->attach($permission->getKey());
    $visitor->roles()->attach($role->getKey());
    mcpTestBindIdentity($visitor);
    $actor = app(ActorContextFactory::class)->fromRequest(request(), 'mcp');
    $available = app(AvailableCapabilities::class);
    $registry = app(CapabilityRegistry::class);
    expect($registry->get('cms_content_sections_get')->permissionFor(['type' => 'post']))->toBe('manage:posts');
    expect($registry->get('cms_content_sections_sync')->permissionFor(['type' => 'page']))->toBe('manage:pages');
    expect($registry->get('cms_audit_approve')->permissionFor(['type' => 'post']))->toBe('manage:posts');
    expect($available->reason('cms_content_sections_get', $actor, ['type' => 'post', 'id' => 1]))->not->toBeNull();
    expect($available->reason('cms_content_sections_sync', $actor, ['type' => 'page', 'id' => 1, 'sections' => []]))->not->toBeNull();
    expect($available->reason('cms_audit_approve', $actor, ['type' => 'post', 'id' => 1]))->not->toBeNull();
});

test('editing a published post stages a private revision until editorial approval', function (): void {
    $crud = app(EntityCrudService::class);
    $payload = $crud->create('post', ['attributes' => ['publish' => true], 'translations' => ['es' => ['title' => 'Live post', 'content' => 'Live body']]], $this->actor);
    $updated = $crud->update('post', $payload['id'], ['translations' => ['es' => ['content' => 'Pending body']]]);
    $post = Post::findOrFail($payload['id']);
    expect($post->is_published)->toBeTrue();
    expect($post->translations->first()->content)->toBe('Live body');
    expect($updated['status'])->toBe('draft');
    $audit = app(ContentAuditServiceInterface::class);
    $audit->approve($post, $this->actor);
    expect($post->fresh()->translations->first()->content)->toBe('Pending body');
    expect($post->fresh()->is_published)->toBeTrue();
    expect(SystemNotification::query()->where('module', 'content_audit')->where('type', 'approval')->firstOrFail()->user_id)->toBe($this->actor->getKey());
});

test('editing published page blocks preserves the live version until editorial approval', function (): void {
    $crud = app(EntityCrudService::class);
    $payload = $crud->create('page', ['attributes' => ['publish' => true, 'layout' => 'app'], 'translations' => ['es' => ['title' => 'Live page', 'slug' => 'live-page']], 'sections_json' => json_encode(['es' => [['type' => 'text', 'content' => 'Live block']]])], $this->actor);
    $before = app(SectionEditorService::class)->payload(Page::findOrFail($payload['id']));
    $crud->update('page', $payload['id'], ['attributes' => ['layout' => 'new-layout'], 'sections_json' => json_encode(['es' => [['type' => 'text', 'content' => 'Pending block']]])]);
    $page = Page::findOrFail($payload['id']);
    expect($page->is_published)->toBeTrue();
    expect($page->layout)->toBe('app');
    expect(app(SectionEditorService::class)->payload($page))->toBe($before);
    app(ContentAuditServiceInterface::class)->approve($page, $this->actor);
    expect($page->fresh()->layout)->toBe('new-layout');
    expect(json_encode(app(SectionEditorService::class)->payload($page->fresh())))->toContain('Pending block');
});

test('successive drafts merge changes and stale approvals preserve newer live content', function (): void {
    $crud = app(EntityCrudService::class);
    $payload = $crud->create('post', ['publish' => true, 'translations' => ['es' => ['title' => 'Live', 'content' => 'Original']]], $this->actor);
    $crud->update('post', $payload['id'], ['translations' => ['es' => ['content' => 'Draft body']]]);
    $crud->update('post', $payload['id'], ['translations' => ['es' => ['title' => 'Draft title']]]);
    $post = Post::findOrFail($payload['id']);
    expect($post->translations->first()->title)->toBe('Live');
    app(ContentAuditServiceInterface::class)->approve($post, $this->actor);
    expect($post->fresh()->translations->first()->title)->toBe('Draft title');
    expect($post->fresh()->translations->first()->content)->toBe('Draft body');
    $crud->update('post', $payload['id'], ['translations' => ['es' => ['content' => 'Second draft']]]);
    $post->translations()->first()->forceFill(['content' => 'Newer live body'])->saveQuietly();
    try {
        app(ContentAuditServiceInterface::class)->approve($post->fresh(), $this->actor);
        $this->fail('A stale draft must not overwrite newer live content.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(409);
    }
    expect($post->fresh()->translations->first()->content)->toBe('Newer live body');
});

test('approving an explicit deactivation preserves the requested visibility', function (string $type, string $flag, array $translations): void {
    $crud = app(EntityCrudService::class);
    $payload = $crud->create($type, ['publish' => true, 'translations' => ['es' => $translations]], $this->actor);
    $entity = $crud->resolveEntity($type, $payload['id']);
    expect((bool) $entity->getAttribute($flag))->toBeTrue();
    $crud->update($type, $payload['id'], ['attributes' => [$flag => false]]);
    expect((bool) $entity->fresh()->getAttribute($flag))->toBeTrue();
    // A later content-only save must retain the pending deactivation intent.
    $crud->update($type, $payload['id'], ['translations' => ['es' => $translations]]);
    $entity = $entity->fresh();
    $audit = app(ContentAuditServiceInterface::class)->getAudit($entity);
    expect($audit->revisions()->whereKey($audit->pending_revision_id)->firstOrFail()->snapshot['_visibility_intent']['value'])->toBeFalse();
    $this->actingAs($this->actor);
    $slug = app(ContentAuditServiceInterface::class)->resolveEntitySlug($entity);
    $review = $this->get(route('admin.audit.review', ['type' => $slug, 'id' => $entity->getKey()]))->assertOk();
    expect((bool) $entity->fresh()->getAttribute($flag))->toBeTrue();
    $this->post(route('admin.audit.approve', ['type' => $slug, 'id' => $entity->getKey()]), ['review_token' => $review->viewData('reviewToken')])->assertRedirect(route('admin.audit.index'));
    expect((bool) $entity->fresh()->getAttribute($flag))->toBeFalse();
    expect(app(ContentAuditServiceInterface::class)->getAudit($entity)->status)->toBe('approved');
})->with([
    'page' => ['page', 'is_published', ['title' => 'Live page pending deactivation', 'content' => 'Body']],
    'product' => ['product', 'is_active', ['name' => 'Live product pending deactivation', 'description' => 'Description']],
]);

test('rejecting a stale draft archives it and allows a new draft without reverting inventory', function (): void {
    $product = Model::withoutEvents(fn () => Product::create(['sku' => 'REJECT-STALE-STOCK', 'price' => 20, 'stock_quantity' => 4, 'is_active' => true]));
    $product->translations()->create(['locale' => 'es', 'name' => 'Live inventory', 'slug' => 'live-inventory']);
    $crud = app(EntityCrudService::class);
    $audits = app(ContentAuditServiceInterface::class);
    $crud->update('product', $product->getKey(), ['attributes' => ['price' => 25]]);
    Model::withoutEvents(fn () => $product->refresh()->decrement('stock_quantity'));
    try {
        $audits->approve($product->fresh(), $this->actor);
        $this->fail('The inventory change must invalidate approval.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(409);
    }
    $audit = $audits->reject($product->fresh(), $this->actor, 'Descartar la revisión anterior');
    expect($audit->pending_revision_id)->toBeNull();
    $rejected = $audit->revisions()->where('action', 'rejected')->latest('version')->firstOrFail();
    expect((float) $rejected->snapshot['attributes']['price'])->toBe(25.0);
    expect($product->fresh()->stock_quantity)->toBe(3)->and($product->fresh()->is_active)->toBeTrue();
    $next = $crud->update('product', $product->getKey(), ['attributes' => ['price' => 30]]);
    expect($next['live_preserved'])->toBeTrue();
    $audits->approve($product->fresh(), $this->actor);
    expect($product->fresh()->stock_quantity)->toBe(3)->and((float) $product->fresh()->price)->toBe(30.0);
});
