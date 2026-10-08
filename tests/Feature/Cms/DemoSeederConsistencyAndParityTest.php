<?php

declare(strict_types=1);

namespace Tests\Feature\Cms;

use App\Models\Market;
use App\Models\Menu;
use App\Models\User;
use App\Services\Cms\MenuService;
use Database\Seeders\PlatformShowcaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Pages\Models\Page;
use Tests\TestCase;

class DemoSeederConsistencyAndParityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlatformShowcaseSeeder::class);
    }

    public function test_all_demo_pages_exist_and_return_ok(): void
    {
        $pages = [
            '/eu/es',
            '/eu/es/como-funciona',
            '/eu/es/modulos',
            '/eu/es/contacto',
            '/eu/es/privacidad',
            '/eu/es/terminos',
            '/eu/en',
            '/eu/en/how-it-works',
            '/eu/en/modules',
            '/eu/en/contact',
            '/eu/en/privacy',
            '/eu/en/terms',
        ];

        foreach ($pages as $url) {
            $response = $this->get($url);
            $response->assertOk();
        }
    }

    public function test_clean_canonical_page_urls_render_successfully(): void
    {
        $cleanUrls = [
            '/eu/es/como-funciona',
            '/eu/es/modulos',
            '/eu/es/contacto',
            '/eu/en/how-it-works',
            '/eu/en/contact',
        ];

        foreach ($cleanUrls as $url) {
            $response = $this->get($url);
            $response->assertOk();
        }
    }

    public function test_all_menus_are_populated_with_items(): void
    {
        $headerMenu = Menu::where('slug', 'header')->with('rootItems')->firstOrFail();
        $footerQuick = Menu::where('slug', 'footer_quick')->with('rootItems')->firstOrFail();
        $footerLegal = Menu::where('slug', 'footer_legal')->with('rootItems')->firstOrFail();

        $this->assertGreaterThanOrEqual(6, $headerMenu->rootItems->count());
        $this->assertGreaterThanOrEqual(3, $footerQuick->rootItems->count());
        $this->assertGreaterThanOrEqual(2, $footerLegal->rootItems->count());
    }

    public function test_menu_service_resolves_clean_urls_without_duplication(): void
    {
        /** @var MenuService $menuService */
        $menuService = app(MenuService::class);
        $market = Market::where('code', 'es')->firstOrFail();

        $items = $menuService->getMenu('header', $market, 'es');
        $this->assertNotEmpty($items);

        foreach ($items as $item) {
            $this->assertStringNotContainsString('/page/page/', $item['url']);
            $this->assertStringNotContainsString('//page', $item['url']);
            $this->assertStringNotContainsString('/page/', $item['url']);
        }
    }

    public function test_block_manager_blade_does_not_leak_raw_javascript(): void
    {
        $admin = User::where('email', 'admin@db-cms.io')->firstOrFail();
        $page = Page::firstOrFail();

        $response = $this->actingAs($admin)->get("/admin/pages/{$page->id}/edit");
        $response->assertOk();

        // Ensure the Alpine component is cleanly encapsulated via blockManager
        $response->assertSee('x-data="blockManager(', false);
        $response->assertDontSee('" : \'\') + text;', false);
    }

    public function test_multi_theme_parity_renders_cleanly_across_all_themes(): void
    {
        $themes = ['db-cyber', 'db-editorial', 'db-enterprise'];

        foreach ($themes as $theme) {
            config(['cms.theme' => $theme]);

            $response = $this->get('/eu/es/como-funciona');
            $response->assertOk();

            $responseShop = $this->get('/eu/es/tienda');
            $responseShop->assertOk();
        }
    }
}
