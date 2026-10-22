<?php

declare(strict_types=1);

use App\Contracts\Services\AdminThemeServiceInterface;
use App\Core\Capabilities\ActorContext;
use App\Core\Capabilities\CallableCapability;
use App\Core\Capabilities\CapabilityRegistry;
use App\Core\Integrations\IntegrationGuideRegistry;
use App\Models\FilterPage;
use App\Models\Market;
use App\Models\User;
use App\Services\Orchestration\ExecutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Modules\CreativeStudio\Services\CreativeStudioDomainService;
use Modules\Marketing\DTOs\CreativePackage;
use Modules\Shop\Models\Order;
use Modules\SocialPublishing\Models\SocialAccount;
use Modules\SocialPublishing\Services\PublicationService;
use Tests\Support\CmsFixtures;
use Tests\Support\ReleaseSurfaceComposition;
use Tests\Support\SocialAccountFixture;
use Tests\Support\VisualContentFixtures;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class, ReleaseSurfaceComposition::class);

beforeEach(function (): void {
    CmsFixtures::localization();
    CmsFixtures::identities();
});

test('all registered static backoffice get surfaces respond coherently in both themes', function (string $theme): void {
    app(AdminThemeServiceInterface::class)->setActiveTheme($theme);
    $user = User::whereHas('roles', fn ($roles) => $roles->where('slug', 'admin'))->firstOrFail();
    $this->actingAs($user);
    $evidence = [];
    foreach (Route::getRoutes() as $route) {
        $name = (string) $route->getName();
        if (! str_starts_with($name, 'admin.') || ! in_array('GET', $route->methods(), true) || str_contains($route->uri(), '{')) {
            continue;
        }
        $response = in_array($name, ['admin.api.entity-search', 'admin.ai_assistant.providers.models', 'admin.creative-studio.sources'], true)
            ? $this->getJson('/'.$route->uri())
            : $this->get('/'.$route->uri());
        $status = $response->getStatusCode();
        $redirectTarget = match (true) {
            $name === 'admin.login', str_ends_with($route->uri(), '/dashboard') => route('admin.dashboard'),
            str_ends_with($route->uri(), '/seo') => route('admin.seo.audit'),
            $name === 'admin.shop.settings' => route('admin.modules.settings', ['identifier' => 'shop']),
            default => null,
        };
        $expected = match (true) {
            // These endpoints require explicit query arguments or an enabled feature.
            in_array($name, ['admin.api.entity-search', 'admin.ai_assistant.providers.models', 'admin.creative-studio.sources'], true) => [422],
            in_array($name, ['admin.orchestration.catalog', 'admin.maintenance.preview'], true) => [200, 404],
            $redirectTarget !== null => [302],
            default => [200],
        };
        expect($status)->toBeIn($expected, "{$theme}: {$name} /{$route->uri()} returned {$status}.");
        if ($redirectTarget !== null) {
            $response->assertRedirect($redirectTarget);
        }
        $evidence[] = ['name' => $name, 'uri' => $route->uri(), 'status' => $status, 'expected' => $expected];
    }
    expect(array_column($evidence, 'name'))->toContain('admin.dashboard', 'admin.pages.index', 'admin.blog.posts.index');
    if (getenv('CMS_FINAL_AUDIT_EVIDENCE') === '1') {
        $directory = storage_path('app/private/final-system-audit');
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        file_put_contents($directory.'/backoffice-'.$theme.'.json', json_encode($evidence, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
    }
})->with(['dbcms', 'termocms']);

test('every discovered module settings screen renders in both themes', function (string $theme): void {
    app(AdminThemeServiceInterface::class)->setActiveTheme($theme);
    $user = User::whereHas('roles', fn ($roles) => $roles->where('slug', 'admin'))->firstOrFail();
    $this->actingAs($user);
    foreach (self::TEST_MODULES as $identifier) {
        $response = $this->get(route('admin.modules.settings', ['identifier' => $identifier]));
        expect($response->getStatusCode())->toBe(200, "{$theme}: module {$identifier} settings must render.");
        $response->assertSee('data-admin-shell', false);
    }
})->with(['dbcms', 'termocms']);

// Dynamic HTML screens require real model identities. Binary exports, API/status
// endpoints have their own workflow/security tests. The callback test below uses
// a real session-bound authorization with only the remote provider boundary faked.
test('existing content and workflow details render in both backoffice brands', function (string $theme): void {
    Http::preventStrayRequests();
    app(AdminThemeServiceInterface::class)->setActiveTheme($theme);
    $user = User::whereHas('roles', fn ($roles) => $roles->where('slug', 'admin'))->firstOrFail();
    $this->actingAs($user);
    $page = VisualContentFixtures::home();
    $post = VisualContentFixtures::post();
    $product = VisualContentFixtures::product();
    $gallery = VisualContentFixtures::gallery();
    $form = VisualContentFixtures::form();
    $form->submissions()->create(['locale' => 'es', 'payload' => ['nombre' => 'Persona fixture'], 'is_reviewed' => false]);
    $filter = FilterPage::create(['module' => 'blog', 'market_code' => 'es', 'locale' => 'es', 'signature' => 'search=fixture', 'path' => '/es/es/blog?search=fixture', 'title' => 'Filtro fixture', 'is_indexable' => false]);
    $order = Order::create(['order_number' => 'SURFACE-ORDER-1', 'market_id' => Market::where('code', 'es')->sole()->id, 'customer_name' => 'Cliente fixture', 'customer_email' => 'customer@example.test', 'grand_total' => 29]);
    $order->items()->create(['product_id' => $product->id, 'product_name' => 'Línea fixture', 'sku' => $product->sku, 'quantity' => 1, 'unit_price' => 29, 'subtotal' => 29, 'total' => 29]);
    $actor = new ActorContext($user->id);
    app(CapabilityRegistry::class)->register(new CallableCapability(
        'surface_editorial_review',
        'Revisar un cambio editorial antes de publicar',
        [],
        [],
        'manage:pages',
        'write',
        static fn (): array => throw new LogicException('The surface fixture must wait for human approval.'),
    ), effect: 'publish');
    $execution = app(ExecutionService::class)->startCapability('surface_editorial_review', [], $actor);
    expect($execution->status)->toBe('approval_required');
    $executionReviewUrl = route('admin.executions.review', $execution->id);

    $campaign = app(CreativeStudioDomainService::class)->createCampaign(['title' => 'Campaña fixture', 'brief' => 'Un brief explícito para la revisión visual', 'mode' => 'free', 'objective' => 'Informar', 'audience' => 'Lectores', 'locale' => 'es', 'market_ids' => [Market::where('code', 'es')->sole()->id], 'budget_limit' => 0, 'currency' => 'EUR'], $actor);
    $account = SocialAccount::create(['provider' => 'facebook', 'remote_id' => 'surface-page', 'identity' => 'facebook:surface-page', 'label' => 'Cuenta fixture', 'authorization_generation' => 1, 'status' => 'connected', 'access_token' => 'isolated-token', 'grants' => ['pages_show_list', 'pages_manage_posts', 'pages_read_engagement'], 'metadata' => SocialAccountFixture::metadata('facebook'), 'expires_at' => now()->addDay()]);
    $publication = app(PublicationService::class)->create(new CreativePackage('surface-package', 'surface-revision', null, 'es', 'es', 'Publicación fixture', null, []), $account->id, [], $actor);

    $screens = [
        [route('admin.pages.edit', $page), 'Fixture section'],
        [route('admin.blog.posts.edit', $post), 'Artículo fixture'],
        [route('admin.shop.products.edit', $product), 'Producto fixture'],
        [route('admin.galleries.edit', $gallery), 'Proyectos'],
        [route('admin.forms.edit', $form), 'Contacto'],
        [route('admin.forms.submissions'), 'Persona fixture'],
        [route('admin.blog.categories.index'), 'Noticias'],
        [route('admin.shop.categories.index'), 'Productos'],
        [route('admin.shop.orders.show', $order), 'Línea fixture'],
        [route('admin.seo.filters.index', ['new' => 1]), 'name="module"'],
        [route('admin.seo.filters.index', ['edit' => $filter->id]), 'Filtro fixture'],
        [route('admin.audit.review', ['type' => 'posts', 'id' => $post->id]), 'Artículo fixture'],
        [route('admin.audit.review', ['type' => 'products', 'id' => $product->id]), 'Producto fixture'],
        [route('admin.creative-studio.show', $campaign->id), 'Campaña fixture'],
        [route('admin.social-publishing.preview', $publication->id), 'Publicación fixture'],
        [$executionReviewUrl, 'Revisa la acción solicitada'],
    ];
    foreach ($screens as [$url, $content]) {
        $response = $this->get($url);
        expect($response->status())->toBe(200, "{$theme}: {$url} must render its existing entity.");
        $response->assertSee('data-admin-shell', false)->assertSee('theme-'.$theme, false)->assertSee($content, false);
        if ($url === $executionReviewUrl) {
            $response->assertViewHas('executionId', $execution->id)
                ->assertSee($execution->id, false)
                ->assertSee('Aprobar este cambio')
                ->assertSee('Actualizar revisión')
                ->assertDontSee('Preparar y ejecutar');
        }
    }
})->with(['dbcms', 'termocms']);

test('registered integration guides render their actual instructions in both brands', function (string $theme): void {
    app(AdminThemeServiceInterface::class)->setActiveTheme($theme);
    $this->actingAs(User::whereHas('roles', fn ($roles) => $roles->where('slug', 'admin'))->firstOrFail());
    foreach (app(IntegrationGuideRegistry::class)->all() as $identifier => $guides) {
        $this->get(route('admin.modules.guides.index', ['identifier' => $identifier]))->assertOk()->assertSee('data-admin-shell', false);
        foreach ($guides as $id => $guide) {
            $this->get(route('admin.modules.guides.show', ['identifier' => $identifier, 'guide' => $id]))->assertOk()->assertSee('data-admin-shell', false);
        }
    }
})->with(['dbcms', 'termocms']);

test('the signed social callback renders account selection without exposing provider tokens in both brands', function (string $theme): void {
    app(AdminThemeServiceInterface::class)->setActiveTheme($theme);
    $this->actingAs(User::whereHas('roles', fn ($roles) => $roles->where('slug', 'admin'))->firstOrFail());
    SocialAccountFixture::metadata('facebook');
    Http::preventStrayRequests();
    Http::fake([
        'https://graph.facebook.com/*/oauth/access_token' => Http::response(['access_token' => 'PRIVATE_PROVIDER_TOKEN', 'expires_in' => 3600]),
        'https://graph.facebook.com/*/me/permissions' => Http::response(['data' => array_map(static fn (string $permission): array => ['permission' => $permission, 'status' => 'granted'], ['pages_show_list', 'pages_manage_posts', 'pages_read_engagement'])]),
        'https://graph.facebook.com/*/me/accounts*' => Http::response(['data' => [['id' => 'fixture-page', 'name' => 'Página autorizada fixture', 'access_token' => 'PRIVATE_PAGE_TOKEN', 'tasks' => ['CREATE_CONTENT']]]]),
    ]);
    $begin = $this->post(route('admin.social-publishing.oauth.begin', ['provider' => 'facebook']))->assertRedirect();
    $this->withCookie((string) config('session.cookie'), session()->getId());
    parse_str((string) parse_url((string) $begin->headers->get('Location'), PHP_URL_QUERY), $query);
    $response = $this->get(route('admin.social-publishing.oauth.callback', ['provider' => 'facebook', 'state' => $query['state'], 'code' => 'fixture-code']))->assertOk();
    $response->assertSee('data-admin-shell', false)->assertSee('Página autorizada fixture')->assertSee('name="remote_id"', false)
        ->assertSee('name="state_id"', false)->assertDontSee('PRIVATE_PROVIDER_TOKEN')->assertDontSee('PRIVATE_PAGE_TOKEN');
})->with(['dbcms', 'termocms']);
