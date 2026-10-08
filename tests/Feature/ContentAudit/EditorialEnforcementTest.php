<?php

declare(strict_types=1);

use App\Contracts\Services\ModuleSettingsServiceInterface;
use App\Core\Modules\ModuleManager;
use App\Models\Language;
use App\Models\Market;
use App\Models\Orchestration\Execution;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\Post;
use Modules\Mcp\Services\McpServerService;

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
    $settings->set('content_audit', 'enforce_editorial_quality', true);
    $settings->set('content_audit', 'min_anti_ai_score', 85);
});

test('enforced editorial quality blocks robotic content from restricted roles', function (): void {
    $authorRole = Role::where('slug', 'author')->first();
    $author = User::factory()->create();
    $author->roles()->sync([$authorRole->id]);

    $category = Category::create();

    $roboticTitle = 'La Transformación Digital De Las Empresas';
    $roboticContent = 'En la era digital actual, la tecnología avanza. Las empresas deben adaptarse. Los cambios son constantes. La innovación es clave. Los datos son importantes. Es crucial destacar la relevancia de la transformación digital en las organizaciones modernas.';

    $response = $this->actingAs($author)
        ->withoutMiddleware(ValidateCsrfToken::class)
        ->post(route('admin.blog.posts.store'), [
            'translations' => ['es' => ['title' => $roboticTitle, 'content' => $roboticContent]],
            'category_id' => $category->id,
            'is_published' => 1,
        ]);

    $response->assertSessionHasErrors('editorial_quality');

    expect(Post::whereHas('translations', fn ($q) => $q->where('title', $roboticTitle))->exists())->toBeFalse();
});

test('approvers are exempt from the editorial gate', function (): void {
    $adminRole = Role::where('slug', 'admin')->first();
    $admin = User::factory()->create();
    $admin->roles()->sync([$adminRole->id]);

    $category = Category::create();

    $roboticTitle = 'La Transformación Digital De Las Empresas';
    $roboticContent = 'En la era digital actual, la tecnología avanza. Las empresas deben adaptarse. Los cambios son constantes. La innovación es clave. Los datos son importantes. Es crucial destacar la relevancia de la transformación digital en las organizaciones modernas.';

    $response = $this->actingAs($admin)
        ->withoutMiddleware(ValidateCsrfToken::class)
        ->post(route('admin.blog.posts.store'), [
            'translations' => ['es' => ['title' => $roboticTitle, 'content' => $roboticContent]],
            'category_id' => $category->id,
            'is_published' => 1,
        ]);

    $response->assertRedirect(route('admin.blog.posts.index'));

    expect(Post::whereHas('translations', fn ($q) => $q->where('title', $roboticTitle))->exists())->toBeTrue();
});

test('mcp publishing rejects robotic content when enforcement is active', function (): void {
    /** @var McpServerService $mcp */
    $mcp = app(McpServerService::class);
    $admin = User::factory()->create();
    $admin->roles()->sync([Role::where('slug', 'admin')->firstOrFail()->getKey()]);
    mcpTestBindIdentity($admin);

    $response = $mcp->handle([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => 'cms_publish_post',
            'arguments' => [
                'title' => 'La Transformación Digital De Las Empresas',
                'content' => 'En la era digital actual, la tecnología avanza. Las empresas deben adaptarse. Los cambios son constantes. La innovación es clave. Los datos son importantes. Es crucial destacar la relevancia de la transformación digital en las organizaciones modernas.',
                'locale' => 'es',
                'confirm' => true,
            ],
        ],
    ]);

    $data = json_decode($response['result']['content'][0]['text'], true);

    expect($data['confirmation_required'])->toBeTrue();
    expect(Post::count())->toBe(0);
    $execution = Execution::findOrFail($data['execution']['id']);
    $step = $execution->steps()->firstOrFail();
    $this->actingAs($admin, 'web')->postJson(route('admin.orchestration.approve', $execution->getKey()), ['step' => $step->key, 'binding_hash' => $step->binding_hash])->assertSuccessful();
    $step->refresh();
    expect($step->status)->toBe('failed');
    expect($step->result['success'])->toBeFalse()
        ->and($step->result['error'])->toBe('editorial_quality_rejected');
    expect(Post::count())->toBe(0);
});

test('mcp publishing accepts human-grade content when enforcement is active', function (): void {
    /** @var McpServerService $mcp */
    $mcp = app(McpServerService::class);
    $admin = User::factory()->create();
    $admin->roles()->sync([Role::where('slug', 'admin')->firstOrFail()->getKey()]);
    mcpTestBindIdentity($admin);

    $response = $mcp->handle([
        'jsonrpc' => '2.0',
        'id' => 2,
        'method' => 'tools/call',
        'params' => [
            'name' => 'cms_publish_post',
            'arguments' => [
                'title' => 'El impacto del nuevo reglamento europeo de IA en las pymes',
                'content' => "## Análisis de la nueva directiva\n\nEl Parlamento Europeo establece requisitos estrictos. No hay marcha atrás.\n\n### Preguntas frecuentes\n¿Afecta a todas las empresas? Principalmente a aquellas que desarrollan o integran modelos de alto riesgo, aunque el alcance se ampliará con el tiempo.",
                'locale' => 'es',
                'confirm' => true,
            ],
        ],
    ]);

    $data = json_decode($response['result']['content'][0]['text'], true);

    expect($data['confirmation_required'])->toBeTrue();
    expect(Post::count())->toBe(0);
    $approved = mcpTestHumanApproval($data, $admin);
    expect($approved['success'])->toBeTrue();
    expect(Post::findOrFail($approved['post_id'])->is_published)->toBeTrue();
});
