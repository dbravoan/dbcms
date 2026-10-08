<?php

declare(strict_types=1);

use App\Models\Language;
use App\Models\Market;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Blog\Services\PostDomainService;
use Modules\Charts\Widgets\KpiCountersWidget;
use Modules\Pages\Services\PageDomainService;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->spanish = Language::firstOrCreate(['code' => 'es'], ['name' => 'Spanish', 'native_name' => 'Español', 'is_default' => true, 'is_active' => true]);
    $this->marketEs = Market::firstOrCreate(['code' => 'es'], ['name' => 'Spain', 'default_locale' => 'es', 'is_default' => true, 'is_active' => true]);
    $this->marketEs->languages()->sync([$this->spanish->id => ['is_default' => true]]);

    $this->user = User::create(['name' => 'Editor', 'email' => 'editor@example.com', 'password' => bcrypt('password')]);

    $admin = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
    $this->user->roles()->attach($admin->getKey());
    $this->actingAs($this->user);

    // Create a featured post so the widget has content
    (new PostDomainService)->createPost([
        'user_id' => $this->user->id,
        'is_featured' => true,
        'is_published' => true,
        'published_at' => now(),
        'translations' => [
            'es' => ['title' => 'Destacado de Inteligencia Artificial', 'content' => 'Texto del post destacado'],
        ],
    ]);

    $this->pageService = new PageDomainService;
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
        'sections' => [
            [
                'type' => 'hero',
                'order' => 1,
                'styling_json' => [
                    'layout' => ['is_fullwidth' => true],
                    'appearance' => ['background_color' => '#f8fafc'],
                    'scroll_magic' => ['behavior' => 'fade-up', 'delay' => '0.2s'],
                ],
                'translations' => [
                    'es' => ['title' => 'Bienvenido al CMS Agentic', 'subtitle' => 'Arquitectura Modular Multi-Mercado', 'cta_text' => 'Explorar', 'cta_url' => '/es/es/blog'],
                ],
            ],
            [
                'type' => 'widget',
                'widget_key' => 'charts.kpi_counters',
                'order' => 2,
                'styling_json' => [
                    'layout' => ['is_fullwidth' => false],
                    'scroll_magic' => ['behavior' => 'reveal-left'],
                ],
                'translations' => [
                    'es' => ['stats' => [['value' => '7', 'label' => 'Revisiones QA documentadas', 'description' => 'Dato de fixture']]],
                ],
            ],
            [
                'type' => 'widget',
                'widget_key' => 'before_after.comparison_slider',
                'order' => 3,
                'styling_json' => [
                    'layout' => ['is_fullwidth' => false],
                ],
                'translations' => [
                    'es' => [
                        'before_label' => 'Antes',
                        'after_label' => 'Después',
                    ],
                ],
            ],
            [
                'type' => 'widget',
                'widget_key' => 'blog.featured_posts',
                'order' => 4,
                'styling_json' => [
                    'layout' => ['is_fullwidth' => false],
                ],
                'translations' => [
                    'es' => ['limit' => 3],
                ],
            ],
        ],
    ]);

    $response = $this->get('/es/es/inicio');

    $response->assertStatus(200)
        ->assertSee('Bienvenido al CMS Agentic')
        ->assertSee('data-scroll-magic="fade-up"', false)
        ->assertSee('Revisiones QA documentadas')
        ->assertSee('Dato de fixture')
        ->assertDontSee('Uptime Guarantee')
        ->assertSee('Destacado de Inteligencia Artificial');
});

test('KPI widgets do not invent metrics when no statistics are configured', function (): void {
    $widget = new KpiCountersWidget;
    $view = $widget->render();
    expect($view)->toBeInstanceOf(View::class);
    expect($view->getData()['stats'])->toBe([]);
    expect($view->render())->not->toContain('Uptime Guarantee', '99.9%', '10M+');
});
