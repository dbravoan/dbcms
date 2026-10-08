<?php

declare(strict_types=1);

use App\Core\Sections\SectionEditorService;
use App\Core\Sections\SectionSyncService;
use App\Models\Language;
use App\Models\Market;
use App\Models\Role;
use App\Models\User;
use App\Services\Capabilities\EntityCrudService;
use Modules\Blog\Models\Post;
use Modules\Mcp\Services\McpServerService;

beforeEach(function (): void {
    Language::firstOrCreate(['code' => 'es'], ['name' => 'Spanish', 'native_name' => 'Español', 'is_default' => true, 'is_active' => true]);
    Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'native_name' => 'English', 'is_active' => true]);
    $this->market = Market::firstOrCreate(['code' => 'uk'], ['name' => 'United Kingdom', 'default_locale' => 'en', 'is_active' => true]);
    $this->actor = User::factory()->create();
    $role = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
    $this->actor->roles()->attach($role);
    $this->actingAs($this->actor);
    $payload = app(EntityCrudService::class)->create('post', [
        'translations' => ['en' => ['title' => 'English-only draft', 'slug' => 'english-only-draft', 'content' => 'English body']],
        'market_ids' => [$this->market->getKey()],
    ], $this->actor);
    $this->postDraft = Post::findOrFail($payload['id']);
});

test('saving English editor content does not create blank Spanish translations or duplicate blocks', function (): void {
    $this->put(route('admin.blog.posts.update', $this->postDraft), [
        'translations' => [
            'es' => ['title' => '', 'slug' => '', 'summary' => '', 'content' => '', 'meta_title' => '', 'meta_description' => '', 'schema_type' => 'TechArticle'],
            'en' => ['title' => 'English-only draft', 'content' => 'English body'],
        ],
        'market_ids' => [$this->market->getKey()],
        'sections_json' => json_encode(['es' => [], 'en' => [['type' => 'text', 'content' => 'English editor block']]], JSON_THROW_ON_ERROR),
    ])->assertRedirect(route('admin.blog.posts.index'))->assertSessionHasNoErrors();

    $post = $this->postDraft->fresh(['translations', 'sections.translations', 'markets']);
    expect($post->translations->pluck('locale')->all())->toBe(['en']);
    expect($post->sections)->toHaveCount(1);
    expect($post->sections->first()->translations->pluck('locale')->all())->toBe(['en']);
    expect($post->markets->modelKeys())->toBe([$this->market->getKey()]);
    expect($post->is_published)->toBeFalse();
});

test('the English editor renders only the actual English section translation', function (): void {
    $section = $this->postDraft->sections()->create(['type' => 'text', 'order' => 1, 'is_active' => true, 'status' => 'draft', 'styling_json' => ['type' => 'text', 'content' => 'EDITOR_ONLY_EN_BLOCK']]);
    $section->translations()->create(['locale' => 'en', 'content_json' => ['type' => 'text', 'content' => 'EDITOR_ONLY_EN_BLOCK']]);
    $response = $this->get(route('admin.blog.posts.edit', $this->postDraft));
    $response->assertOk();
    expect(substr_count($response->getContent(), 'EDITOR_ONLY_EN_BLOCK'))->toBe(1);
});

test('the shared editor and MCP reader expose only actual section translations', function (): void {
    $section = $this->postDraft->sections()->create(['type' => 'text', 'order' => 1, 'is_active' => true, 'status' => 'draft', 'styling_json' => ['type' => 'text', 'content' => 'Actual English section']]);
    $section->translations()->create(['locale' => 'en', 'content_json' => ['type' => 'text', 'content' => 'Actual English section']]);
    $payload = app(SectionEditorService::class)->payload($this->postDraft->fresh(['sections.translations']));
    expect($payload['es'])->toBe([]);
    expect($payload['en'])->toHaveCount(1);
    expect($payload['en'][0]['content'])->toBe('Actual English section');
    $withoutTranslations = $this->postDraft->fresh(['sections']);
    expect($withoutTranslations->sections->first()->relationLoaded('translations'))->toBeFalse();
    expect(app(SectionEditorService::class)->payload($withoutTranslations))->toBe($payload);

    mcpTestBindIdentity($this->actor);
    $response = app(McpServerService::class)->handle([
        'jsonrpc' => '2.0', 'id' => 'localized-reader', 'method' => 'tools/call',
        'params' => ['name' => 'cms_content_sections_get', 'arguments' => ['type' => 'post', 'id' => $this->postDraft->getKey()]],
    ]);
    $read = json_decode($response['result']['content'][0]['text'], true, 512, JSON_THROW_ON_ERROR);
    expect(array_keys($read['sections'][0]['translations']))->toBe(['en']);
    expect($read['sections'][0]['translations']['en']['content'])->toBe($payload['en'][0]['content']);
});

test('localized section dictionaries retain unequal counts without filling missing languages', function (): void {
    app(SectionSyncService::class)->sync($this->postDraft, json_encode([
        'es' => [['type' => 'text', 'content' => 'Spanish first']],
        'en' => [['type' => 'text', 'content' => 'English first'], ['type' => 'text', 'content' => 'English second']],
    ], JSON_THROW_ON_ERROR), 'draft');

    $sections = $this->postDraft->fresh(['sections.translations'])->sections->sortBy('order')->values();
    expect($sections)->toHaveCount(2);
    expect($sections[0]->translations->pluck('locale')->sort()->values()->all())->toBe(['en', 'es']);
    expect($sections[1]->translations->pluck('locale')->all())->toBe(['en']);
});

test('an empty section list does not delete an existing entity translation', function (): void {
    app(EntityCrudService::class)->update('post', $this->postDraft->getKey(), ['translations' => ['es' => ['title' => 'Título español', 'content' => 'Contenido español']]]);
    $this->put(route('admin.blog.posts.update', $this->postDraft), [
        'translations' => ['en' => ['title' => 'English updated', 'content' => 'English body']],
        'sections_json' => json_encode(['es' => [], 'en' => [['type' => 'text', 'content' => 'English retained']]], JSON_THROW_ON_ERROR),
    ])->assertRedirect(route('admin.blog.posts.index'))->assertSessionHasNoErrors();

    $post = $this->postDraft->fresh(['translations', 'sections.translations']);
    expect($post->translations->firstWhere('locale', 'es')->content)->toBe('Contenido español');
    expect($post->translations->firstWhere('locale', 'en')->title)->toBe('English updated');
    expect($post->sections->first()->translations->pluck('locale')->all())->toBe(['en']);
});

test('existing translations accept explicit blank updates without creating other languages', function (): void {
    app(EntityCrudService::class)->update('post', $this->postDraft->getKey(), ['translations' => ['en' => ['title' => '', 'content' => '', 'summary' => '', 'meta_title' => '', 'meta_description' => '']]]);
    $post = $this->postDraft->fresh(['translations']);
    expect($post->translations->pluck('locale')->all())->toBe(['en']);
    expect($post->translations->first()->title)->toBe('');
    expect($post->translations->first()->content)->toBe('');
});

test('legacy flat section lists retain their shared-language compatibility', function (): void {
    app(SectionSyncService::class)->sync($this->postDraft, json_encode([['type' => 'text', 'content' => 'Legacy shared block']], JSON_THROW_ON_ERROR), 'draft');
    expect($this->postDraft->fresh(['sections.translations'])->sections->first()->translations->pluck('locale')->sort()->values()->all())->toBe(['en', 'es']);
});
