<?php

declare(strict_types=1);

use App\Core\Modules\ModuleManager;
use App\Models\Language;
use App\Models\Market;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Http;
use Modules\AiAssistant\DTOs\MultimodalOrchestrationRequest;
use Modules\AiAssistant\Services\AiDraftPersistenceService;
use Modules\Blog\Models\Post;
use Modules\Pages\Models\Page;

beforeEach(function (): void {
    Http::preventStrayRequests();
    app(ModuleManager::class)->discoverModules();
    $role = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
    $this->actor = User::factory()->create();
    $this->actor->roles()->attach($role);
    Language::firstOrCreate(['code' => 'es'], ['name' => 'Español', 'native_name' => 'Español', 'is_active' => true]);
    Market::firstOrCreate(['code' => 'es'], ['name' => 'España', 'default_locale' => 'es', 'currency' => 'EUR', 'is_active' => true]);
});

test('generated post drafts preserve the acting author and remain unpublished', function (): void {
    $request = new MultimodalOrchestrationRequest(topic: 'Test', options: ['actor_id' => $this->actor->id]);
    $id = app(AiDraftPersistenceService::class)->create('post', $request, ['title' => 'Technical article', 'slug' => 'technical-article', 'summary' => 'Summary', 'content' => 'Article body', 'meta_title' => 'Article', 'meta_description' => 'Description'], []);
    $post = Post::findOrFail($id);
    expect($post->user_id)->toBe($this->actor->id)->and($post->is_published)->toBeFalse()->and($post->translations()->where('locale', 'es')->value('content'))->toBe('Article body');
});

test('generated page drafts persist their editable sections and translations', function (): void {
    $request = new MultimodalOrchestrationRequest(topic: 'Test page', entityType: 'page', options: ['actor_id' => $this->actor->id]);
    $id = app(AiDraftPersistenceService::class)->create('page', $request, ['title' => 'Landing', 'slug' => 'landing', 'meta_title' => 'Landing', 'meta_description' => 'Description'], [['type' => 'text', 'data' => ['title' => 'Section heading', 'content' => 'Editable body']]]);
    $page = Page::findOrFail($id);
    $section = $page->sections()->firstOrFail();
    expect($page->is_published)->toBeFalse()->and($section->status)->toBe('draft')->and($section->translations()->count())->toBeGreaterThan(0);
    expect(json_encode($section->translations()->first()->content_json))->toContain('Editable body');
});

test('draft creation never substitutes the first user when its actor is absent', function (): void {
    expect(fn () => app(AiDraftPersistenceService::class)->create('post', new MultimodalOrchestrationRequest('No actor'), ['title' => 'Unsafe', 'slug' => 'unsafe'], []))->toThrow(AuthorizationException::class);
    expect(Post::whereHas('translations', fn ($query) => $query->where('slug', 'unsafe'))->exists())->toBeFalse();
});

test('draft persistence refuses an actor without AI and entity permissions', function (): void {
    $unprivileged = User::factory()->create();
    $request = new MultimodalOrchestrationRequest(topic: 'Denied', options: ['actor_id' => $unprivileged->id]);
    expect(fn () => app(AiDraftPersistenceService::class)->create('post', $request, ['title' => 'Denied', 'slug' => 'denied'], []))->toThrow(AuthorizationException::class);
    expect(Post::count())->toBe(0);
});

test('media generation cannot silently modify already published content', function (): void {
    $request = new MultimodalOrchestrationRequest(topic: 'Published', options: ['actor_id' => $this->actor->id]);
    $service = app(AiDraftPersistenceService::class);
    $id = $service->create('post', $request, ['title' => 'Published', 'slug' => 'published', 'content' => 'Body'], []);
    Post::findOrFail($id)->update(['is_published' => true]);
    expect(fn () => $service->attachFeaturedImage('post', $id, '/storage/new.png', $this->actor->id))->toThrow(AuthorizationException::class, 'confirmation');
});

test('a non-admin with explicit AI and entity grants can persist its own draft', function (): void {
    $editor = User::factory()->create();
    $role = Role::firstOrCreate(['slug' => 'ai-draft-editor'], ['name' => 'AI draft editor']);
    foreach (['use:ai', 'manage:posts'] as $slug) {
        $permission = Permission::firstOrCreate(['slug' => $slug], ['name' => $slug]);
        $role->permissions()->syncWithoutDetaching([$permission->id]);
    }
    $editor->roles()->attach($role);
    $request = new MultimodalOrchestrationRequest(topic: 'Editor', options: ['actor_id' => $editor->id]);
    $id = app(AiDraftPersistenceService::class)->create('post', $request, ['title' => 'Editor', 'slug' => 'editor', 'content' => 'Body'], []);
    expect($editor->isAdmin())->toBeFalse()->and(Post::findOrFail($id)->user_id)->toBe($editor->id);
});
