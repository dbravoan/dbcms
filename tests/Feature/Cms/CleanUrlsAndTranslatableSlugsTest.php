<?php

declare(strict_types=1);

namespace Tests\Feature\Cms;

use App\Contracts\Services\LocalizationServiceInterface;
use App\Contracts\Services\ModuleSettingsServiceInterface;
use App\Core\Modules\ModuleManager;
use App\Models\Market;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Blog\Models\Category;
use Modules\Pages\Services\SectionRendererService;
use Modules\Shop\Models\ProductCategory;
use PlatformShowcaseSeeder;
use Tests\TestCase;

class CleanUrlsAndTranslatableSlugsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlatformShowcaseSeeder::class);

        $role = Role::where('slug', 'admin')->firstOrFail();
        $this->admin = User::factory()->create();
        $this->admin->roles()->syncWithoutDetaching([$role->id]);
    }

    public function test_blog_category_resolves_directly_without_categoria_prefix(): void
    {
        $category = Category::whereHas('translations', function ($q): void {
            $q->where('slug', 'tecnologia')->where('locale', 'es');
        })->firstOrFail();

        $response = $this->get('/es/es/blog/tecnologia');

        $response->assertOk();
        $response->assertSee('Tecnología', false);
        $response->assertSee('Inicio');
        $response->assertSee('Blog');
    }

    public function test_shop_category_resolves_directly_without_categoria_prefix(): void
    {
        $category = ProductCategory::whereHas('translations', function ($q): void {
            $q->where('slug', 'servidores-edge')->where('locale', 'es');
        })->firstOrFail();

        $response = $this->get('/es/es/tienda/servidores-edge');

        $response->assertOk();
        $response->assertSee('Servidores y computación perimetral', false);
    }

    public function test_localization_service_returns_configurable_module_prefixes(): void
    {
        /** @var LocalizationServiceInterface $localization */
        $localization = app(LocalizationServiceInterface::class);

        // Default prefixes
        expect($localization->getModulePrefix('blog', 'es'))->toBe('blog');
        expect($localization->getModulePrefix('shop', 'es'))->toBe('tienda');
        expect($localization->getModulePrefix('shop', 'en'))->toBe('shop');

        /** @var ModuleSettingsServiceInterface $moduleSettings */
        $moduleSettings = app(ModuleSettingsServiceInterface::class);
        $moduleSettings->set('blog', 'slug_es', 'noticias');
        $moduleSettings->set('shop', 'slug_es', 'tienda-online');

        expect($localization->getModulePrefix('blog', 'es'))->toBe('noticias');
        expect($localization->getModulePrefix('shop', 'es'))->toBe('tienda-online');

        $allBlogSlugs = $localization->getAllModuleSlugs('blog');
        expect($allBlogSlugs)->toContain('noticias');

        $allShopSlugs = $localization->getAllModuleSlugs('shop');
        expect($allShopSlugs)->toContain('tienda-online');
    }

    public function test_module_impact_assessment_endpoint_returns_json_report(): void
    {
        $response = $this->actingAs($this->admin, 'web')->get(route('admin.modules.impact', ['identifier' => 'pages']));

        $response->assertOk();
        $response->assertJsonStructure([
            'identifier',
            'is_core_risk',
            'warning',
            'affected_menus',
            'affected_pages',
            'has_impact',
        ]);

        $data = $response->json();
        expect($data['is_core_risk'])->toBeTrue();
        expect($data['has_impact'])->toBeTrue();
    }

    public function test_section_renderer_service_gracefully_silences_inactive_module_widgets(): void
    {
        /** @var SectionRendererService $renderer */
        $renderer = app(SectionRendererService::class);
        $market = Market::where('code', 'es')->first();

        // When blog is active, widget_posts renders articles
        $htmlActive = $renderer->renderSection([
            'type' => 'widget_posts',
            'title' => 'Artículos Destacados',
            'post_count' => 3,
        ], $market, 'es');

        expect($htmlActive)->toContain('Artículos Destacados');

        // Deactivate blog module in memory
        $moduleManager = app(ModuleManager::class);
        $moduleManager->deactivate('blog');

        // When blog is inactive, section renderer silences without 500 error
        $htmlInactive = $renderer->renderSection([
            'type' => 'widget_posts',
            'title' => 'Artículos Destacados',
            'post_count' => 3,
        ], $market, 'es');

        expect($htmlInactive)->not->toContain('Artículos Destacados');
        expect($htmlInactive)->toContain('blog module inactive');
    }
}
