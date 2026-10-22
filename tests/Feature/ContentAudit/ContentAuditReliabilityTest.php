<?php

declare(strict_types=1);

use App\Contracts\Services\ModuleSettingsServiceInterface;
use App\Core\Content\Contracts\EditorialWorkflowInterface;
use App\Core\Modules\ModuleManager;
use App\Models\CmsModule;
use App\Models\Language;
use App\Models\Market;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemNotification;
use App\Models\User;
use App\Services\Admin\AdminNavigationService;
use App\Services\Capabilities\ContentSectionActions;
use App\Services\Capabilities\ContentSnapshotService;
use App\Services\Capabilities\EntityMutationService;
use App\Services\Seo\PublicContentModifiedService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Modules\Blog\Models\Post;
use Modules\ContentAudit\Capabilities\EditorialActions;
use Modules\ContentAudit\Enums\AuditAction;
use Modules\ContentAudit\Events\ContentSubmittedForReviewEvent;
use Modules\ContentAudit\Models\ContentAuditRevision;
use Modules\ContentAudit\Services\AuditNotificationRecipientPolicy;
use Modules\ContentAudit\Services\AuditRevisionService;
use Modules\ContentAudit\Services\EditorialApprovalBinding;
use Modules\ContentAudit\Services\EditorialWorkflow;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\McpEditorialComposition;
use Tests\Support\McpProtocolClient;

uses(McpEditorialComposition::class);

beforeEach(function (): void {
    config(['app.key' => 'base64:'.base64_encode(str_repeat('Q', 32))]);
    app(ModuleManager::class)->discoverModules();
    $settings = app(ModuleSettingsServiceInterface::class);
    $settings->set('content_audit', 'is_enabled', true);
    $settings->set('content_audit', 'notify_in_app', false);
    Language::firstOrCreate(['code' => 'es'], ['name' => 'Spanish', 'native_name' => 'Español', 'is_active' => true, 'is_default' => true]);
    $this->market = Market::firstOrCreate(['code' => 'es'], ['name' => 'Spain', 'default_locale' => 'es', 'is_active' => true, 'is_default' => true]);
    $this->otherMarket = Market::firstOrCreate(['code' => 'mx'], ['name' => 'Mexico', 'default_locale' => 'es', 'is_active' => true]);
    $this->reviewer = User::factory()->create();
    $this->reviewer->roles()->attach(Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']));
    $this->actingAs($this->reviewer);
    $this->audits = app(EditorialWorkflow::class);
    $this->crud = app(EntityMutationService::class);
});

function reliabilityPost(object $test): Post
{
    $created = $test->crud->create('post', ['publish' => true, 'attributes' => ['is_featured' => true], 'market_ids' => [$test->market->getKey()], 'translations' => ['es' => ['title' => 'Public editorial version', 'content' => 'Public body', 'schema_type' => 'TechArticle', 'meta_description' => 'Original metadata']]], $test->reviewer);

    return Post::findOrFail($created['id']);
}

test('editorial approval returns the entity type rather than its numeric identifier', function (): void {
    $post = reliabilityPost($this);
    $this->audits->submitForReview($post, $this->reviewer);
    $result = app(EditorialActions::class)->auditApprove(['type' => 'post', 'id' => $post->getKey()]);
    expect($result['entity'])->toBe(['type' => 'post', 'id' => $post->getKey()]);
});

test('the pending audit badge reveals only items the current actor may inspect', function (): void {
    $manage = Permission::firstOrCreate(['slug' => 'manage:posts'], ['name' => 'Manage posts']);
    $access = Permission::firstOrCreate(['slug' => 'access:admin'], ['name' => 'Access admin']);
    $role = Role::firstOrCreate(['slug' => 'author'], ['name' => 'Author']);
    $role->permissions()->sync([$manage->getKey(), $access->getKey()]);
    $author = User::factory()->create();
    $author->roles()->attach($role);
    $own = reliabilityPost($this);
    $own->updateQuietly(['user_id' => $author->getKey()]);
    $this->audits->submitForReview($own, $author);
    $other = reliabilityPost($this);
    $this->audits->submitForReview($other, $this->reviewer);
    $this->actingAs($author);
    expect(app(AdminNavigationService::class)->getPendingAuditCount())->toBe(1);
    $restricted = User::factory()->create();
    $restrictedRole = Role::firstOrCreate(['slug' => 'audit-badge-limited'], ['name' => 'Limited']);
    $restrictedRole->permissions()->sync([$access->getKey()]);
    $restricted->roles()->attach($restrictedRole);
    $this->actingAs($restricted);
    expect(app(AdminNavigationService::class)->getPendingAuditCount())->toBe(0);
    $workflow = app(EditorialWorkflowInterface::class);
    expect($workflow->canReview($author))->toBeFalse()
        ->and($workflow->canReview($this->reviewer))->toBeTrue()
        ->and($workflow->pendingReviewCount($this->reviewer))->toBe(2);
    CmsModule::where('identifier', 'blog')->update(['is_active' => false]);
    expect($workflow->pendingReviewCount($this->reviewer))->toBe(0);
    CmsModule::where('identifier', 'content_audit')->update(['is_active' => false]);
    expect($workflow->canReview($this->reviewer))->toBeFalse()
        ->and($workflow->pendingReviewCount($this->reviewer))->toBe(0);
});

test('physically inactive audit module stops editorial enforcement and preserves history', function (): void {
    CmsModule::where('identifier', 'content_audit')->update(['is_active' => false]);
    expect($this->audits->isEnabled())->toBeFalse();
    CmsModule::where('identifier', 'content_audit')->update(['is_active' => true, 'is_installed' => false]);
    expect($this->audits->isEnabled())->toBeFalse();
});

test('historical snapshots retain all domain records rather than only normalized text', function (): void {
    $post = reliabilityPost($this);
    $revision = app(AuditRevisionService::class)->record($post, $this->reviewer, AuditAction::UPDATED->value);
    expect($revision->snapshot)->toHaveKey('snapshot_version', 3)->not->toHaveKey('translations')->not->toHaveKey('sections')
        ->and($revision->snapshot)->toHaveKey('attributes')
        ->and((bool) $revision->snapshot['attributes']['is_featured'])->toBeTrue()
        ->and($revision->snapshot['translation_records'][0]['schema_type'])->toBe('TechArticle')
        ->and($revision->snapshot['market_ids'])->toBe([$this->market->getKey()]);
});

test('rollback fully restores a staged revision and clears the obsolete pending pointer', function (): void {
    $post = reliabilityPost($this);
    $this->crud->update('post', $post->getKey(), ['attributes' => ['is_featured' => false], 'market_ids' => [$this->otherMarket->getKey()], 'translations' => ['es' => ['title' => 'Desired historic draft', 'schema_type' => 'Article', 'content' => 'Historical body']]]);
    $revision = app(AuditRevisionService::class)->auditFor($post)->revisions()->whereKey(app(AuditRevisionService::class)->auditFor($post)->pending_revision_id)->firstOrFail();
    $this->crud->update('post', $post->getKey(), ['translations' => ['es' => ['title' => 'Obsolete pending draft']]]);
    $this->audits->rollback($post, $revision->version, $this->reviewer);
    $post->refresh();
    expect($post->translations->first()->title)->toBe('Desired historic draft')
        ->and($post->translations->first()->schema_type)->toBe('Article')
        ->and($post->is_featured)->toBeFalse()
        ->and($post->markets->modelKeys())->toBe([$this->otherMarket->getKey()])
        ->and(app(AuditRevisionService::class)->auditFor($post)->pending_revision_id)->toBeNull();
});

test('rejecting from an obsolete review screen cannot discard a newer draft', function (): void {
    $post = reliabilityPost($this);
    $this->crud->update('post', $post->getKey(), ['translations' => ['es' => ['title' => 'Review version A']]]);
    $review = app(EditorialApprovalBinding::class)->review($post, $this->reviewer);
    $this->crud->update('post', $post->getKey(), ['translations' => ['es' => ['title' => 'Review version B']]]);
    $pending = app(AuditRevisionService::class)->auditFor($post)->pending_revision_id;
    $this->postJson(route('admin.audit.reject', ['type' => 'posts', 'id' => $post->getKey()]), ['review_token' => $review['token'], 'rejection_reason' => 'Necesita fuentes comprobables'])->assertConflict();
    expect(app(AuditRevisionService::class)->auditFor($post)->pending_revision_id)->toBe($pending);
});

test('disabled administrative change tracking does not emit unsolicited history', function (): void {
    app(ModuleSettingsServiceInterface::class)->set('content_audit', 'track_all_modifications', false);
    $post = reliabilityPost($this);
    expect($post->auditRevisions()->count())->toBe(0);
});

test('quality gate checks a second locale of a published staged draft', function (): void {
    $post = reliabilityPost($this);
    $role = Role::firstOrCreate(['slug' => 'author'], ['name' => 'Author']);
    $permissions = Permission::query()->whereIn('slug', ['manage:posts', 'access:admin'])->pluck('id');
    $role->permissions()->sync($permissions);
    $author = User::factory()->create();
    $author->roles()->attach($role);
    $post->updateQuietly(['user_id' => $author->getKey()]);
    $this->actingAs($author);
    app(ModuleSettingsServiceInterface::class)->set('content_audit', 'enforce_editorial_quality', true);
    $before = app(ContentSnapshotService::class)->hash(app(ContentSnapshotService::class)->capture($post->refresh()));
    expect(fn () => $this->crud->update('post', $post->getKey(), ['translations' => ['es' => ['title' => 'Datos contrastados', 'content' => 'El informe identifica requisitos y facilita comprobar sus fuentes.'], 'en' => ['title' => 'The new regulation', 'content' => 'In the digital age, we delve into a rich tapestry of opportunities.']]]))->toThrow(ValidationException::class);
    expect(app(ContentSnapshotService::class)->hash(app(ContentSnapshotService::class)->capture($post->refresh())))->toBe($before);
});

test('published content submitted for review appears in the pending queue while preserving live', function (): void {
    $post = reliabilityPost($this);
    $this->put(route('admin.blog.posts.update', $post), ['translations' => ['es' => ['title' => 'Queued replacement', 'content' => 'New draft']], 'is_published' => '1', 'audit_action' => 'submit_for_review'])->assertRedirect();
    expect(app(AuditRevisionService::class)->auditFor($post)->status)->toBe('pending_review')
        ->and($post->fresh()->translations->first()->title)->toBe('Public editorial version');
});

test('disabling the review flow cannot grant review authority to a restricted author', function (): void {
    $post = reliabilityPost($this);
    $role = Role::firstOrCreate(['slug' => 'author'], ['name' => 'Author']);
    $permission = Permission::firstOrCreate(['slug' => 'manage:posts'], ['name' => 'Manage posts']);
    $access = Permission::firstOrCreate(['slug' => 'access:admin'], ['name' => 'Access admin']);
    $role->permissions()->sync([$permission->getKey(), $access->getKey()]);
    $author = User::factory()->create();
    $author->roles()->attach($role);
    app(ModuleSettingsServiceInterface::class)->set('content_audit', 'is_enabled', false);
    expect(fn () => $this->audits->approve($post, $author))->toThrow(HttpException::class);
});

test('approval without automatic publication keeps the live version until explicit publication', function (): void {
    $post = reliabilityPost($this);
    $this->crud->update('post', $post->getKey(), ['translations' => ['es' => ['title' => 'Approved replacement']]]);
    app(ModuleSettingsServiceInterface::class)->set('content_audit', 'auto_publish_on_approval', false);
    $this->audits->approve($post, $this->reviewer);
    expect($post->fresh()->translations->first()->title)->toBe('Public editorial version')
        ->and(app(AuditRevisionService::class)->auditFor($post)->status)->toBe('approved')
        ->and(app(AuditRevisionService::class)->auditFor($post)->pending_revision_id)->not->toBeNull();
    $this->crud->update('post', $post->getKey(), ['publish' => true]);
    expect($post->fresh()->translations->first()->title)->toBe('Approved replacement')
        ->and(app(AuditRevisionService::class)->auditFor($post)->pending_revision_id)->toBeNull();
});

test('rollback from a stale screen cannot replace the current pending draft', function (): void {
    $post = reliabilityPost($this);
    $historic = $post->auditRevisions()->firstOrFail();
    $screen = $this->get(route('admin.audit.review', ['type' => 'posts', 'id' => $post->getKey()]))->assertOk();
    $this->crud->update('post', $post->getKey(), ['translations' => ['es' => ['title' => 'Pending newer edit']]]);
    $pending = app(AuditRevisionService::class)->auditFor($post)->pending_revision_id;
    $tokens = $screen->original->getData()['rollbackTokens'] ?? [];
    $this->postJson(route('admin.audit.rollback', $historic->getKey()), ['review_token' => $tokens[$historic->getKey()] ?? $screen->viewData('reviewToken')])->assertConflict();
    expect(app(AuditRevisionService::class)->auditFor($post)->pending_revision_id)->toBe($pending);
});

test('rolled back submissions cannot emit review events or notifications', function (): void {
    $post = reliabilityPost($this);
    Event::fake([ContentSubmittedForReviewEvent::class]);
    app(ModuleSettingsServiceInterface::class)->set('content_audit', 'notify_in_app', true);
    DB::beginTransaction();
    $this->audits->submitForReview($post, $this->reviewer);
    DB::rollBack();
    Event::assertNotDispatched(ContentSubmittedForReviewEvent::class);
    expect(SystemNotification::query()->count())->toBe(0)
        ->and($post->fresh()->is_published)->toBeTrue();
});

test('audit settings expose all quality and delivery options in both administrative themes', function (string $brand): void {
    config(['cms.brand' => $brand]);
    $this->get(route('admin.audit.index', ['tab' => 'settings']))->assertOk()->assertSee('name="enforce_editorial_quality"', false)->assertSee('name="min_anti_ai_score"', false)->assertSee('name="notify_email"', false)->assertSee('no verifica hechos');
    $this->postJson(route('admin.audit.settings'), ['min_anti_ai_score' => 101])->assertUnprocessable()->assertJsonValidationErrors('min_anti_ai_score');
})->with(['dbcms', 'termocms']);

test('market-only changes update applied content dates and staged edits preserve live dates', function (): void {
    $post = reliabilityPost($this);
    $original = $post->updated_at->toISOString();
    $this->travel(2)->seconds();
    $this->crud->update('post', $post->getKey(), ['market_ids' => [$this->otherMarket->getKey()]]);
    expect($post->fresh()->updated_at->toISOString())->toBe($original);
    $this->audits->approve($post, $this->reviewer);
    expect($post->fresh()->updated_at->toISOString())->not->toBe($original);
    app(ModuleSettingsServiceInterface::class)->set('content_audit', 'is_enabled', false);
    $before = $post->fresh()->updated_at->toISOString();
    $this->travel(2)->seconds();
    $this->crud->update('post', $post->getKey(), ['market_ids' => [$this->market->getKey()]]);
    expect($post->fresh()->updated_at->toISOString())->not->toBe($before);
});

test('an explicit job author follows the same review workflow without a web session', function (): void {
    $role = Role::firstOrCreate(['slug' => 'author'], ['name' => 'Author']);
    $author = User::factory()->create();
    $author->roles()->attach($role);
    auth()->forgetGuards();
    $created = $this->crud->create('post', ['publish' => true, 'audit_action' => 'submit_for_review', 'audit_notes' => 'Revisar las fuentes', 'translations' => ['es' => ['title' => 'Borrador del trabajo', 'content' => 'Datos que requieren comprobación editorial.']]], $author);
    $post = Post::findOrFail($created['id']);
    expect($post->is_published)->toBeFalse()
        ->and(app(AuditRevisionService::class)->auditFor($post)->status)->toBe('pending_review')
        ->and(app(AuditRevisionService::class)->auditFor($post)->submitted_by)->toBe($author->getKey())
        ->and(app(AuditRevisionService::class)->auditFor($post)->revisions()->latest('version')->first()->snapshot['translation_records'][0]['title'])->toBe('Borrador del trabajo');
});

test('a notification is suppressed after its source module or revision ceases to be available', function (): void {
    $post = reliabilityPost($this);
    $audit = $this->audits->submitForReview($post, $this->reviewer);
    $notification = new SystemNotification(['module' => 'content_audit', 'type' => 'submission', 'data' => ['entity_type' => 'posts', 'entity_id' => $post->getKey(), 'audit_version' => $audit->current_version]]);
    $policy = app(AuditNotificationRecipientPolicy::class);
    expect($policy($notification, $this->reviewer))->toBeTrue();
    CmsModule::where('identifier', 'blog')->update(['is_active' => false]);
    expect($policy($notification, $this->reviewer))->toBeFalse();
    CmsModule::where('identifier', 'blog')->update(['is_active' => true]);
    $this->audits->saveAsDraft($post, $this->reviewer);
    expect($policy($notification, $this->reviewer))->toBeFalse();
});

test('rejecting live content from MCP waits for a bound human decision before changing visibility', function (): void {
    $post = reliabilityPost($this);
    mcpTestBindIdentity($this->reviewer);
    $response = app(McpProtocolClient::class)->handle(['jsonrpc' => '2.0', 'id' => 42, 'method' => 'tools/call', 'params' => ['name' => 'cms_audit_reject', 'arguments' => ['type' => 'post', 'id' => $post->getKey(), 'reason' => 'Faltan fuentes verificables']]]);
    $data = json_decode($response['result']['content'][0]['text'], true);
    expect($data)->toHaveKey('confirmation_required', true)
        ->and($post->fresh()->is_published)->toBeTrue();
    $result = mcpTestHumanApproval($data, $this->reviewer);
    expect($result['rejected'])->toBeTrue()->and($post->fresh()->is_published)->toBeFalse();
});

test('private content and block edits retain the public modification date until publication', function (): void {
    $post = reliabilityPost($this);
    $dates = app(PublicContentModifiedService::class);
    $before = $dates->forEntity($post->fresh())->toISOString();
    $this->travel(2)->seconds();
    $this->crud->update('post', $post->getKey(), ['translations' => ['es' => ['title' => 'Private title', 'content' => 'Private text']], 'sections_json' => json_encode(['es' => [['type' => 'text', 'content' => 'Private block']]])]);
    expect($dates->forEntity($post->fresh())->toISOString())->toBe($before);
    app(ModuleSettingsServiceInterface::class)->set('content_audit', 'auto_publish_on_approval', false);
    $this->audits->approve($post, $this->reviewer);
    expect($dates->forEntity($post->fresh())->toISOString())->toBe($before);
    $this->travel(2)->seconds();
    $this->crud->update('post', $post->getKey(), ['publish' => true]);
    expect($dates->forEntity($post->fresh())->toISOString())->not->toBe($before);
});

test('a deleted entity keeps an exact final snapshot in its private revision history', function (): void {
    $post = reliabilityPost($this);
    $id = $post->getKey();
    $this->crud->delete('post', $id);
    $last = ContentAuditRevision::query()->where('auditable_type', $post->getMorphClass())->where('auditable_id', $id)->latest('version')->firstOrFail();
    expect($last->action)->toBe('deleted')
        ->and($last->snapshot['translation_records'][0]['title'])->toBe('Public editorial version')
        ->and(Post::find($id))->toBeNull();
});

test('restoring a snapshot rolls back canonical rows when a related record cannot be restored', function (): void {
    $post = reliabilityPost($this);
    $snapshots = app(ContentSnapshotService::class);
    $snapshot = $snapshots->capture($post);
    $snapshot['attributes']['is_featured'] = false;
    $snapshot['translation_records'][0]['title'] = 'Must not survive failed restore';
    $snapshot['translation_records'][] = $snapshot['translation_records'][0];

    expect(fn () => $snapshots->restore($post, $snapshot))->toThrow(QueryException::class);
    $post->refresh();
    expect($post->is_featured)->toBeTrue()
        ->and($post->translations()->sole()->title)->toBe('Public editorial version');
});

test('an invalid publication convenience flag cannot silently unpublish content', function (): void {
    $post = reliabilityPost($this);
    expect(fn () => $this->crud->update('post', $post->getKey(), ['publish' => 'invalid']))
        ->toThrow(ValidationException::class);
    expect($post->fresh()->is_published)->toBeTrue();
});

test('approval rejects a stale live version even when automatic publication is disabled', function (bool $autoPublish): void {
    $post = reliabilityPost($this);
    $this->crud->update('post', $post->getKey(), ['translations' => ['es' => ['title' => 'Pending replacement']]]);
    $pending = app(AuditRevisionService::class)->auditFor($post)->pending_revision_id;
    $post->translations()->where('locale', 'es')->update(['title' => 'Concurrent live change']);
    app(ModuleSettingsServiceInterface::class)->set('content_audit', 'auto_publish_on_approval', $autoPublish);

    try {
        $this->audits->approve($post, $this->reviewer);
        $this->fail('A stale pending revision cannot be approved.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(409);
    }
    expect($post->fresh()->translations->first()->title)->toBe('Concurrent live change')
        ->and(app(AuditRevisionService::class)->auditFor($post)->pending_revision_id)->toBe($pending)
        ->and(app(AuditRevisionService::class)->auditFor($post)->status)->toBe('draft');
})->with([true, false]);

test('the section capability stages edits through the editorial boundary and reads its pending blocks', function (): void {
    $post = reliabilityPost($this);
    $actions = app(ContentSectionActions::class);
    $result = $actions->sectionsSync([
        'type' => 'post',
        'id' => $post->getKey(),
        'sections' => ['es' => [['type' => 'text', 'content' => 'Pending section content']]],
    ]);

    expect($result['live_preserved'])->toBeTrue()
        ->and($result['pending_revision_id'])->toBeInt()
        ->and($post->sections()->count())->toBe(0)
        ->and($result['sections'][0]['translations']['es']['content'])->toBe('Pending section content');

    $this->audits->approve($post, $this->reviewer);
    expect($post->fresh()->publishedSections()->count())->toBe(1)
        ->and($actions->sectionsGet(['type' => 'post', 'id' => $post->getKey()])['sections'][0]['translations']['es']['content'])->toBe('Pending section content');
});
