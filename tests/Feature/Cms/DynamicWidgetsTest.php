<?php

declare(strict_types=1);

use App\Content\Pages\Services\PageDomainService;
use App\Models\Language;
use App\Models\Market;
use App\Models\Role;
use App\Models\User;
use BlockPackages\Charts\Blocks\KpiBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Blog\Services\PostDomainService;
use Tests\Support\BlogComposition;

uses(RefreshDatabase::class, BlogComposition::class);

beforeEach(function (): void {
    $this->spanish = Language::firstOrCreate(['code' => 'es'], ['name' => 'Spanish', 'native_name' => 'Español', 'is_default' => true, 'is_active' => true]);
    $this->marketEs = Market::firstOrCreate(['code' => 'es'], ['name' => 'Spain', 'default_locale' => 'es', 'is_default' => true, 'is_active' => true]);
    $this->marketEs->languages()->sync([$this->spanish->id => ['is_default' => true]]);

    $this->user = User::create(['name' => 'Editor', 'email' => 'editor@example.com', 'password' => bcrypt('password')]);

    $admin = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
    $this->user->roles()->attach($admin->getKey());
    $this->actingAs($this->user);

    // Create a featured post so the widget has content
    app(PostDomainService::class)->createPost([
        'user_id' => $this->user->id,
        'market_ids' => [$this->marketEs->id],
        'is_featured' => true,
        'is_published' => true,
        'published_at' => now(),
        'translations' => [
            'es' => ['title' => 'Destacado de Inteligencia Artificial', 'content' => 'Texto del post destacado'],
        ],
    ]);

    $this->pageService = app(PageDomainService::class);
});

test('it renders a composite page containing Hero, Before/After slider, KPI counters, and Featured Blog widget', function (): void {
    $page = $this->pageService->createPage([
        'is_published' => true,
        'translations' => [
            'es' => [
                'title' => 'Página Principal Innovadora',
                'slug' => 'inicio',
                'meta_description' => 'Portada con widgets interactivos',
            ],
        ],
        'market_ids' => [$this->marketEs->id],
        'sections_json' => json_encode(['es' => [
            ['type' => 'hero', 'title' => 'Bienvenido al CMS Agentic', 'content' => 'Arquitectura Modular Multi-Mercado', 'cta_text' => 'Explorar', 'cta_url' => '/es/es/blog', 'scroll_magic' => ['behavior' => 'fadeInUp', 'delay' => '0.2s']],
            ['type' => 'kpi', 'items' => [['value' => '7', 'label' => 'Revisiones QA documentadas', 'description' => 'Dato de fixture']]],
            ['type' => 'before_after', 'title' => 'Comparación de imágenes', 'image_url' => '/before.jpg', 'after_image_url' => '/after.jpg', 'before_label' => 'Antes', 'after_label' => 'Después'],
            ['type' => 'widget_posts', 'selection' => 'featured', 'limit' => 3],
        ]], JSON_THROW_ON_ERROR),
    ]);

    $response = $this->get('/es/es/inicio');

    $response->assertStatus(200)
        ->assertSee('Bienvenido al CMS Agentic')
        ->assertSee('Arquitectura Modular Multi-Mercado')
        ->assertSee('data-scroll-magic="fadeInUp"', false)
        ->assertSee('Revisiones QA documentadas')
        ->assertSee('Dato de fixture')
        ->assertSee('src="/before.jpg"', false)
        ->assertSee('src="/after.jpg"', false)
        ->assertSee('Comparación de imágenes')
        ->assertSee('type="range"', false)
        ->assertSee('aria-label="Antes / Después"', false)
        ->assertDontSee('Uptime Guarantee')
        ->assertSee('Destacado de Inteligencia Artificial');
});

test('KPI widgets do not invent metrics when no statistics are configured', function (): void {
    $block = new KpiBlock;
    expect($block->render([]))->not->toContain('Uptime Guarantee', '99.9%', '10M+');
});
