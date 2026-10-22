<?php

declare(strict_types=1);

use App\Contracts\Services\LocalizationServiceInterface;
use App\Core\Capabilities\ActorContext;
use App\Core\Capabilities\CapabilityExecutor;
use App\Core\Content\Contracts\EntitySchemaRegistryInterface;
use App\Core\Sections\SectionEditorService;
use App\Enums\OAuthScope;
use App\Http\Middleware\EnsureCmsIsInstalled;
use App\Models\Language;
use App\Models\Market;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Capabilities\ActorContextFactory;
use App\Services\Capabilities\EntityMutationService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Passport\ClientRepository;
use Modules\AiAssistant\Jobs\ProcessAiGeneration;
use Modules\AiAssistant\Models\AiGenerationTask;
use Modules\AiAssistant\Services\AiTaskService;
use Modules\Blog\Models\Post;
use Tests\Support\AiContentComposition;
use Tests\Support\PassportTestKeys;

uses(AiContentComposition::class);

beforeEach(function (): void {
    PassportTestKeys::configure();
    Http::preventStrayRequests();
    config(['services.gemini.key' => 'fake-translation-key', 'queue.default' => 'sync']);
    $role = Role::firstOrCreate(['slug' => 'translation-editor'], ['name' => 'Translation editor']);
    foreach (['access:admin', 'manage:posts', 'use:ai'] as $slug) {
        $permission = Permission::firstOrCreate(['slug' => $slug], ['name' => $slug]);
        $role->permissions()->syncWithoutDetaching([$permission->getKey()]);
    }
    $this->translationEditor = User::factory()->create();
    $this->translationEditor->roles()->attach($role);
    $source = Language::updateOrCreate(['code' => 'es'], ['name' => 'Spanish', 'native_name' => 'Español', 'is_active' => true]);
    $target = Language::updateOrCreate(['code' => 'en'], ['name' => 'English', 'native_name' => 'English', 'is_active' => true]);
    $this->translationMarket = Market::updateOrCreate(['code' => 'qa'], ['name' => 'Translation QA', 'currency' => 'EUR', 'default_locale' => 'es', 'is_active' => true]);
    $this->translationMarket->languages()->sync([$source->id, $target->id]);
    app(LocalizationServiceInterface::class)->forgetCache();
    $this->translationPost = Post::create(['user_id' => $this->translationEditor->id, 'is_published' => true, 'published_at' => now(), 'featured_image' => '/storage/original.webp']);
    $this->translationPost->markets()->attach($this->translationMarket);
    $this->translationPost->translations()->create(['locale' => 'es', 'title' => 'Biblioteca', 'slug' => 'biblioteca', 'content' => '<p>Texto original.</p>']);
    $this->translationPayload = [
        'action' => 'translate_entity', 'entity_type' => 'post', 'entity_id' => $this->translationPost->id, 'text_provider' => 'gemini', 'text_model' => 'gemini-fixture',
        'source_locale' => 'es', 'target_locale' => 'en', 'editor_market_ids' => [$this->translationMarket->id],
        'fields' => ['title' => 'Biblioteca', 'body' => '<p class="intro">Texto original.</p>', 'meta_title' => 'Libros'],
        'sections' => [['id' => 'qa-text', 'type' => 'text', 'content' => '## Ordenar libros', 'image_url' => '/original.webp', 'animation' => 'fadeInUp']],
    ];
    Http::fake(function ($request) {
        $input = json_decode($request['contents'][0]['parts'][0]['text'], true, 512, JSON_THROW_ON_ERROR);
        $segments = array_map(static fn (array $segment): array => ['id' => $segment['id'], 'text' => 'Translated '.$segment['text']], $input['segments']);

        return Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode(['segments' => $segments], JSON_THROW_ON_ERROR)]]]]], 'usageMetadata' => ['promptTokenCount' => 10, 'candidatesTokenCount' => 10]]);
    });
    // Installation has its own integration suite. The HTTP adapter fixture must
    // work with a fresh checkout, independently of an installation marker.
    $this->withoutMiddleware(EnsureCmsIsInstalled::class);
    $this->withSession(['_token' => 'translation-csrf-fixture'])->withHeader('X-CSRF-TOKEN', 'translation-csrf-fixture');
});

test('web sync translation returns a structured preview without modifying a published item', function (): void {
    $before = $this->translationPost->fresh()->getAttributes();
    $response = $this->actingAs($this->translationEditor)->postJson(route('admin.ai_assistant.generate'), $this->translationPayload);
    $response->assertOk()->assertJsonPath('success', true)->assertJsonPath('data.source_locale', 'es')->assertJsonPath('data.target_locale', 'en')
        ->assertJsonPath('data.fields.title', 'Translated Biblioteca')->assertJsonPath('data.fields.body', '<p class="intro">Translated Texto original.</p>')
        ->assertJsonPath('data.sections.0.id', 'qa-text')->assertJsonPath('data.sections.0.image_url', '/original.webp')->assertJsonPath('data.sections.0.content', '## Translated Ordenar libros');
    expect($this->translationPost->fresh()->getAttributes())->toBe($before);
    expect($this->translationPost->translations()->count())->toBe(1);
    expect($this->translationPost->translations()->first()->slug)->toBe('biblioteca');
    Http::assertSentCount(1);
});

test('queued translation returns the same preview shape and never repeats a completed generation', function (): void {
    Queue::fake();
    $response = $this->actingAs($this->translationEditor)->postJson(route('admin.ai_assistant.generate_async'), $this->translationPayload);
    $response->assertOk()->assertJsonPath('success', true);
    $task = AiGenerationTask::findOrFail($response->json('task_id'));
    Queue::assertPushed(ProcessAiGeneration::class);
    $job = new ProcessAiGeneration($task->id);
    $job->handle(app(AiTaskService::class));
    $this->getJson(route('admin.ai_assistant.generate_status', $task->id))->assertOk()->assertJsonPath('status', 'completed')
        ->assertJsonPath('data.source_locale', 'es')->assertJsonPath('data.target_locale', 'en')->assertJsonPath('data.resolved_context.market_ids.0', $this->translationMarket->id);
    $job->handle(app(AiTaskService::class));
    Http::assertSentCount(1);
    expect($this->translationPost->translations()->count())->toBe(1);
});

test('the OAuth API uses the same preview and requires AI plus content write scopes', function (): void {
    app(ClientRepository::class)->createPersonalAccessGrantClient('Translation API fixture');
    $token = $this->translationEditor->createToken('Translation preview', [OAuthScope::AI_USE->value, OAuthScope::WRITE_CONTENT->value])->accessToken;
    $this->withToken($token)->postJson('/api/ai-assistant/translate-entity', $this->translationPayload)->assertOk()
        ->assertJsonPath('success', true)->assertJsonPath('data.fields.title', 'Translated Biblioteca')->assertJsonPath('data.target_locale', 'en');
    Http::assertSentCount(1);
    expect($this->translationPost->translations()->count())->toBe(1);
});

test('a token limited to AI use cannot translate content through the API', function (): void {
    app(ClientRepository::class)->createPersonalAccessGrantClient('Translation scope fixture');
    $token = $this->translationEditor->createToken('Insufficient content scope', [OAuthScope::AI_USE->value])->accessToken;
    $this->withToken($token)->postJson('/api/ai-assistant/translate-entity', $this->translationPayload)->assertForbidden()->assertJsonPath('success', false);
    Http::assertNothingSent();
});

test('the common executor supports MCP identity and preserves structure', function (): void {
    mcpTestBindIdentity($this->translationEditor);
    $actor = app(ActorContextFactory::class)->fromRequest(request(), 'mcp');
    $result = app(CapabilityExecutor::class)->execute('cms_ai_translate_entity', $this->translationPayload, $actor);
    expect($result['success'] ?? false)->toBeTrue()->and($result['target_locale'])->toBe('en')
        ->and($result['sections'][0]['animation'])->toBe('fadeInUp')->and($result['source_fingerprint'])->toHaveLength(64);
    Http::assertSentCount(1);
    expect($this->translationPost->translations()->count())->toBe(1);
});

test('a user without AI authority is refused before contacting a model', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user)->postJson(route('admin.ai_assistant.generate'), $this->translationPayload)->assertForbidden();
    $result = app(CapabilityExecutor::class)->execute('cms_ai_translate_entity', $this->translationPayload, new ActorContext($user->id));
    expect($result['error'] ?? null)->not->toBeNull();
    Http::assertNothingSent();
});

test('AI permission alone does not grant the entity permission', function (): void {
    $role = Role::create(['slug' => 'translation-ai-only', 'name' => 'AI only']);
    $role->permissions()->sync(Permission::whereIn('slug', ['access:admin', 'use:ai'])->pluck('id'));
    $user = User::factory()->create();
    $user->roles()->attach($role);
    $this->actingAs($user)->postJson(route('admin.ai_assistant.generate'), $this->translationPayload)->assertJsonPath('success', false)->assertJsonPath('data.denied', true);
    Http::assertNothingSent();
});

test('queued translation revalidates permissions if its owner loses the role', function (): void {
    Queue::fake();
    $response = $this->actingAs($this->translationEditor)->postJson(route('admin.ai_assistant.generate_async'), $this->translationPayload);
    $response->assertOk();
    $task = AiGenerationTask::findOrFail($response->json('task_id'));
    $this->translationEditor->roles()->detach();
    (new ProcessAiGeneration($task->id))->handle(app(AiTaskService::class));
    expect($task->fresh()->status)->toBe('failed');
    expect($this->translationPost->translations()->count())->toBe(1);
    Http::assertNothingSent();
});

test('invalid market and language contexts are rejected before generation', function (string $invalid): void {
    $payload = $this->translationPayload;
    if ($invalid === 'missing market') {
        $payload['editor_market_ids'] = [];
    } elseif ($invalid === 'unknown market') {
        $payload['editor_market_ids'] = [999999];
    } elseif ($invalid === 'same language') {
        $payload['target_locale'] = 'es';
    } else {
        $payload['target_locale'] = 'zz';
    }
    $result = app(CapabilityExecutor::class)->execute('cms_ai_translate_entity', $payload, new ActorContext($this->translationEditor->id));
    expect($result['validation'] ?? null)->toBeArray();
    Http::assertNothingSent();
})->with(['missing market', 'unknown market', 'same language', 'inactive language']);

test('translation rejects structural roles instead of changing the item slug', function (): void {
    $payload = $this->translationPayload;
    $payload['fields']['slug'] = 'replace-slug';
    $result = app(CapabilityExecutor::class)->execute('cms_ai_translate_entity', $payload, new ActorContext($this->translationEditor->id));
    expect($result['validation'] ?? null)->toBeArray();
    Http::assertNothingSent();
});

test('the HTTP translation service supports page and product schemas while protecting nested blocks', function (string $type): void {
    $role = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
    $this->translationEditor->roles()->attach($role);
    $payload = $this->translationPayload;
    $payload['entity_type'] = $type;
    unset($payload['entity_id'], $payload['fields']['body']);
    $payload['sections'] = [['id' => 'columns-1', 'type' => 'text', 'columns' => [['width' => '1/2', 'blocks' => [
        ['id' => 'child-1', 'type' => 'text', 'content' => '<strong>Texto anidado</strong>'],
        ['id' => 'custom-1', 'type' => 'qa_custom_unknown', 'content' => 'No modificar', 'code' => '{{ retained() }}'],
    ]]]]];
    $result = app(CapabilityExecutor::class)->execute('cms_ai_translate_entity', $payload, new ActorContext($this->translationEditor->id));
    expect($result['success'] ?? false)->toBeTrue()
        ->and($result['sections'][0]['columns'][0]['blocks'][0]['content'])->toBe('<strong>Translated Texto anidado</strong>')
        ->and($result['sections'][0]['columns'][0]['blocks'][1])->toBe($payload['sections'][0]['columns'][0]['blocks'][1])
        ->and($result['protected_block_types'])->toContain('qa_custom_unknown');
    Http::assertSentCount(1);
})->with(['page', 'product']);

test('a translated preview saves through the owner service preserving the source structure and draft state', function (string $type): void {
    $role = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
    $this->translationEditor->roles()->attach($role);
    $this->actingAs($this->translationEditor);
    $schema = app(EntitySchemaRegistryInterface::class)->get($type);
    expect($schema)->not->toBeNull();
    $fields = ['title' => 'Biblioteca', 'meta_title' => 'Libros'];
    if (isset($schema->fields['body'])) {
        $fields['body'] = '<p>Descripción original.</p>';
    }
    $toColumns = static function (array $roles) use ($schema): array {
        $columns = [];
        foreach ($roles as $role => $value) {
            preg_match('/\[([^\]]+)\]$/', $schema->fields[$role]['input'], $match);
            $columns[$match[1]] = $value;
        }

        return $columns;
    };
    $sections = [['type' => 'text', 'columns_layout' => '2', 'animation' => 'fadeInUp', 'columns' => [
        ['id' => 'left-column', 'blocks' => [['id' => 'text-child', 'type' => 'text', 'content' => '<strong>Contenido original.</strong>', 'image_url' => '/storage/unchanged.webp']]],
        ['id' => 'right-column', 'blocks' => [['id' => 'link-child', 'type' => 'cta', 'title' => 'Contacto', 'cta_text' => 'Consultar', 'cta_url' => 'https://example.com/contact']]],
    ]]];
    $crud = app(EntityMutationService::class);
    $created = $crud->create($type, [
        'attributes' => $type === 'product' ? ['sku' => 'translation-persistence-product', 'price' => 12, 'stock_quantity' => 4] : [],
        'translations' => ['es' => $toColumns($fields) + ['slug' => 'translation-source-'.$type]],
        'market_ids' => [$this->translationMarket->id], 'sections_json' => ['es' => $sections],
    ], $this->translationEditor);
    $model = $schema->model;
    $entity = $model::findOrFail($created['id']);
    $before = app(SectionEditorService::class)->payload($entity);
    $sourceTranslation = $entity->translations()->where('locale', 'es')->firstOrFail()->getAttributes();
    $sourceIds = $entity->sections()->pluck('id')->all();
    $payload = $this->translationPayload;
    $payload['entity_type'] = $type;
    $payload['entity_id'] = $entity->getKey();
    $payload['fields'] = $fields;
    $payload['sections'] = $before['es'];
    $preview = app(CapabilityExecutor::class)->execute('cms_ai_translate_entity', $payload, new ActorContext($this->translationEditor->id));
    expect($preview['success'] ?? false)->toBeTrue();
    expect($preview['sections'][0]['id'])->toBe($before['es'][0]['id']);
    $crud->update($type, $entity->getKey(), [
        'translations' => ['en' => $toColumns($preview['fields']) + ['slug' => 'translation-target-'.$type]],
        'market_ids' => [$this->translationMarket->id], 'sections_json' => ['es' => $before['es'], 'en' => $preview['sections']],
    ]);
    $saved = $entity->fresh(['translations', 'sections.translations', 'markets']);
    $after = app(SectionEditorService::class)->payload($saved);
    expect($saved->sections()->pluck('id')->all())->toBe($sourceIds);
    expect($saved->translations->firstWhere('locale', 'es')->getAttributes())->toBe($sourceTranslation);
    expect($after['es'])->toBe($before['es']);
    expect($after['en'])->toBe($preview['sections']);
    expect($saved->translations->firstWhere('locale', 'en')->getAttribute($type === 'product' ? 'name' : 'title'))->toBe('Translated Biblioteca');
    expect($after['en'][0]['columns'][0]['blocks'][0]['image_url'])->toBe('/storage/unchanged.webp');
    expect($after['en'][0]['columns'][1]['blocks'][0]['cta_url'])->toBe('https://example.com/contact');
    expect($saved->markets->modelKeys())->toBe([$this->translationMarket->id]);
    expect((bool) $saved->getAttribute($type === 'product' ? 'is_active' : 'is_published'))->toBeFalse();
    expect($saved->sections->pluck('status')->unique()->all())->toBe(['draft']);
    Http::assertSentCount(1);
})->with(['post', 'page', 'product']);

test('saving a translated structure cannot reuse a section belonging to another item', function (): void {
    $foreign = $this->translationPost->sections()->create(['type' => 'text', 'order' => 1, 'is_active' => true, 'status' => 'published', 'styling_json' => ['type' => 'text', 'content' => 'Original ajeno']]);
    $foreign->translations()->create(['locale' => 'es', 'content_json' => ['type' => 'text', 'content' => 'Original ajeno']]);
    $crud = app(EntityMutationService::class);
    $created = $crud->create('post', ['translations' => ['es' => ['title' => 'Otro borrador', 'content' => 'Contenido propio']], 'market_ids' => [$this->translationMarket->id]], $this->translationEditor);
    $crud->update('post', $created['id'], ['sections_json' => ['es' => [['id' => $foreign->id, 'type' => 'text', 'content' => 'Texto propio']], 'en' => [['id' => $foreign->id, 'type' => 'text', 'content' => 'Own text']]]]);
    $other = Post::findOrFail($created['id'])->fresh(['sections.translations']);
    expect($other->sections)->toHaveCount(1);
    expect($other->sections->first()->getKey())->not->toBe($foreign->getKey());
    expect($foreign->fresh()->post_id)->toBe($this->translationPost->id);
    expect($foreign->translations()->where('locale', 'es')->firstOrFail()->content_json['content'])->toBe('Original ajeno');
    Http::assertNothingSent();
});

test('saving an editor structure retains the kept section id and removes only omitted sections', function (): void {
    $crud = app(EntityMutationService::class);
    $created = $crud->create('post', [
        'translations' => ['es' => ['title' => 'Borrador de secciones', 'content' => 'Contenido propio']],
        'market_ids' => [$this->translationMarket->id],
        'sections_json' => ['es' => [['type' => 'text', 'content' => 'Conservar'], ['type' => 'text', 'content' => 'Retirar']]],
    ], $this->translationEditor);
    $entity = Post::findOrFail($created['id']);
    $source = app(SectionEditorService::class)->payload($entity)['es'];
    $keptId = $source[0]['id'];
    $crud->update('post', $created['id'], ['sections_json' => ['es' => [$source[0]]]]);
    $saved = $entity->fresh(['sections.translations']);
    expect($saved->sections()->pluck('id')->all())->toBe([$keptId]);
    expect($saved->sections()->whereKey($source[1]['id'])->exists())->toBeFalse();
    Http::assertNothingSent();
});
