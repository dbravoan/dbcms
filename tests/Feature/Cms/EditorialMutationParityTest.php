<?php

declare(strict_types=1);

use App\Content\Pages\Contracts\PageDomainServiceInterface;
use App\Content\Pages\Models\Page;
use App\Content\Pages\Schema\PageEntitySchemas;
use App\Core\Capabilities\ExecutionContext;
use App\Core\Content\Contracts\EntitySchemaRegistryInterface;
use App\Core\Sections\SectionEditorService;
use App\DTOs\Content\EntityMutationData;
use App\Models\Language;
use App\Models\Market;
use App\Models\Role;
use App\Models\User;
use App\Services\Capabilities\EntityMutationService;
use Modules\Blog\Contracts\PostDomainServiceInterface;
use Modules\Blog\Models\Post;
use Modules\Blog\Schema\BlogEntitySchemas;
use Modules\ContentAudit\Services\EditorialWorkflow;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\BlogAuditComposition;

uses(BlogAuditComposition::class);

beforeEach(function (): void {
    foreach (array_merge(BlogEntitySchemas::all(), PageEntitySchemas::all()) as $schema) {
        app(EntitySchemaRegistryInterface::class)->register($schema);
    }
    Language::firstOrCreate(['code' => 'es'], ['name' => 'Spanish', 'native_name' => 'Español', 'is_default' => true, 'is_active' => true]);
    $this->market = Market::firstOrCreate(['code' => 'es'], ['name' => 'Spain', 'default_locale' => 'es', 'is_default' => true, 'is_active' => true]);
    $this->otherMarket = Market::firstOrCreate(['code' => 'mx'], ['name' => 'Mexico', 'default_locale' => 'es', 'is_active' => true]);
    $this->actor = User::factory()->create();
    $admin = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
    $this->actor->roles()->attach($admin->getKey());
    $this->actingAs($this->actor);
});

test('post domain and shared CRUD apply the same derived content defaults and author', function (): void {
    $data = ['user_id' => 999, 'market_ids' => [$this->market->getKey()], 'translations' => ['es' => ['title' => 'Same editorial input', 'content' => str_repeat('word ', 410)]]];
    $domain = app(PostDomainServiceInterface::class)->createPost($data);
    $shared = app(EntityMutationService::class)->create('post', EntityMutationData::fromForm($data)->arguments, $this->actor);
    $core = Post::findOrFail($shared['id']);
    foreach (['is_published', 'allow_comments', 'is_featured', 'reading_time_minutes', 'user_id'] as $field) {
        expect($domain->getAttribute($field))->toBe($core->getAttribute($field));
    }
    expect($domain->is_published)->toBeFalse();
    expect($domain->user_id)->toBe($this->actor->getKey());
    expect($domain->reading_time_minutes)->toBe(3);
    expect($domain->translations->first()->only(['summary', 'meta_title', 'meta_description', 'schema_type']))
        ->toBe($core->translations->first()->only(['summary', 'meta_title', 'meta_description', 'schema_type']));
    expect($domain->markets->modelKeys())->toBe([$this->market->getKey()]);
});

test('backoffice post creation uses shared block body and ignores forged authors', function (): void {
    $other = User::factory()->create();
    $this->post(route('admin.blog.posts.store'), ['user_id' => $other->getKey(), 'translations' => ['es' => ['title' => 'Block body', 'content' => '']], 'market_ids' => [$this->market->getKey()], 'sections_json' => json_encode(['es' => [['type' => 'text', 'content' => '<p>Body from blocks</p>']]])])
        ->assertRedirect(route('admin.blog.posts.index'))->assertSessionHasNoErrors();
    $post = Post::query()->latest('id')->firstOrFail();
    expect($post->user_id)->toBe($this->actor->getKey());
    expect($post->is_published)->toBeFalse();
    expect($post->translations->first()->content)->toBe('<p>Body from blocks</p>');
    expect($post->translations->first()->summary)->toBe('Body from blocks');
});

test('published post saves from backoffice preserve live content and edit the pending revision', function (): void {
    $service = app(PostDomainServiceInterface::class);
    $post = $service->createPost(['is_published' => true, 'market_ids' => [$this->market->getKey()], 'translations' => ['es' => ['title' => 'Live post', 'content' => 'Live body']]]);
    $this->put(route('admin.blog.posts.update', $post), ['is_published' => '1', 'market_ids' => [$this->otherMarket->getKey()], 'translations' => ['es' => ['title' => 'Pending title', 'content' => 'Pending body']]])
        ->assertRedirect(route('admin.blog.posts.index'))->assertSessionHasNoErrors();
    $live = $post->fresh();
    expect($live->translations->first()->content)->toBe('Live body');
    expect($live->markets->modelKeys())->toBe([$this->market->getKey()]);
    $this->get(route('admin.blog.posts.edit', $post))->assertOk()->assertViewHas('post', fn (Post $draft): bool => $draft->translations->first()->content === 'Pending body' && $draft->markets->modelKeys() === [$this->otherMarket->getKey()]);
    expect($post->fresh()->translations->first()->content)->toBe('Live body');
    app(EditorialWorkflow::class)->approve($live, $this->actor);
    expect($post->fresh()->translations->first()->content)->toBe('Pending body');
    expect($post->fresh()->markets->modelKeys())->toBe([$this->otherMarket->getKey()]);
});

test('page and shared mutation preserve the same canonical layout and widget content', function (): void {
    $data = ['translations' => ['es' => ['title' => 'Widget page']], 'sections_json' => json_encode(['es' => [
        ['type' => 'hero', 'title' => 'First hero'],
        ['type' => 'blog.featured_posts', 'grid_columns' => 2, 'limit' => 3],
    ]], JSON_THROW_ON_ERROR)];
    $page = app(PageDomainServiceInterface::class)->createPage($data);
    $core = app(EntityMutationService::class)->create('page', EntityMutationData::fromForm($data)->arguments, $this->actor);
    $payload = app(SectionEditorService::class)->payload($page);
    $shared = app(SectionEditorService::class)->payload(Page::findOrFail($core['id']));
    expect($page->is_published)->toBeFalse();
    expect($payload['es'][0]['type'])->toBe('hero');
    $stripIds = function (array $rows): array {
        foreach ($rows as &$row) {
            unset($row['id']);
        }

        return $rows;
    };
    expect($stripIds($payload['es']))->toBe($stripIds($shared['es']));
    expect(json_encode($payload))->toContain('blog.featured_posts')->toContain('First hero');
});

test('published page backoffice saves keep live sections and render pending sections in the editor', function (): void {
    $page = app(PageDomainServiceInterface::class)->createPage(['is_published' => true, 'translations' => ['es' => ['title' => 'Live page']], 'market_ids' => [$this->market->getKey()], 'sections_json' => json_encode(['es' => [['type' => 'text', 'content' => 'Live block']]])]);
    $this->put(route('admin.pages.update', $page), ['is_published' => '1', 'layout' => 'pending-layout', 'translations' => ['es' => ['title' => 'Pending page']], 'sections_json' => json_encode(['es' => [['type' => 'text', 'content' => 'Pending block']]])])
        ->assertRedirect(route('admin.pages.index'))->assertSessionHasNoErrors();
    expect($page->fresh()->layout)->toBe('app');
    expect(json_encode(app(SectionEditorService::class)->payload($page->fresh())))->toContain('Live block')->not->toContain('Pending block');
    $this->get(route('admin.pages.edit', $page))->assertOk()->assertViewHas('page', fn (Page $draft): bool => $draft->layout === 'pending-layout' && str_contains(json_encode(app(SectionEditorService::class)->payload($draft)), 'Pending block'));
    expect(json_encode(app(SectionEditorService::class)->payload($page->fresh())))->toContain('Live block');
    app(EditorialWorkflow::class)->approve($page->fresh(), $this->actor);
    expect($page->fresh()->layout)->toBe('pending-layout');
    expect(json_encode(app(SectionEditorService::class)->payload($page->fresh())))->toContain('Pending block');
});

test('post creation cannot select an author from an input payload without an authenticated actor', function (): void {
    auth()->forgetGuards();
    app(ExecutionContext::class)->actor = null;
    expect(fn () => app(PostDomainServiceInterface::class)->createPost(['user_id' => $this->actor->getKey(), 'translations' => ['es' => ['title' => 'Forged author']]]))
        ->toThrow(HttpException::class);
});
