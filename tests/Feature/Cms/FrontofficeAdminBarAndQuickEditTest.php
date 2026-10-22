<?php

declare(strict_types=1);

namespace Tests\Feature\Cms;

use App\Content\Pages\Models\Page;
use App\Contracts\Services\AdminThemeServiceInterface;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Modules\Blog\Models\Post;
use Modules\Shop\Models\Product;
use Tests\Support\BlogFixtures;
use Tests\Support\BlogShopComposition;
use Tests\Support\CmsFixtures;
use Tests\Support\ShopFixtures;
use Tests\TestCase;

class FrontofficeAdminBarAndQuickEditTest extends TestCase
{
    use BlogShopComposition;
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        CmsFixtures::localization();
        CmsFixtures::identities();
        $home = CmsFixtures::home();
        $section = $home->sections()->create(['type' => 'text', 'order' => 1, 'is_active' => true]);
        $section->translations()->create(['locale' => 'es', 'content_json' => ['content' => '<p>Editable home.</p>']]);
        BlogFixtures::post();
        ShopFixtures::product();

        /** @var User $admin */
        $admin = User::where('email', 'admin@db-cms.io')->first();
        $this->adminUser = $admin;
    }

    protected function tearDown(): void
    {
        config(['cms.theme' => 'db-cyber']);
        if (app()->bound(AdminThemeServiceInterface::class)) {
            app(AdminThemeServiceInterface::class)->setActiveTheme('dbcms');
        }
        parent::tearDown();
    }

    public function test_unauthenticated_visitor_does_not_see_admin_bar_or_seo_drawer(): void
    {
        $response = $this->get('/es/es/');

        $response->assertOk();
        $response->assertDontSee('cms-frontoffice-admin-bar-root', false);
        $response->assertDontSee('frontoffice-bar-edit-link', false);
    }

    public function test_authenticated_user_without_backoffice_roles_does_not_see_admin_bar(): void
    {
        $regularUser = User::create([
            'name' => 'Visitante Estándar',
            'email' => 'visitor@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->actingAs($regularUser, 'web')->get('/es/es/');

        $response->assertOk();
        $response->assertDontSee('cms-frontoffice-admin-bar-root', false);
        $response->assertDontSee('frontoffice-bar-edit-link', false);
    }

    public function test_authenticated_admin_can_open_the_home_page_editor(): void
    {
        $homePage = Page::whereHas('translations', function ($q): void {
            $q->where('slug', 'home');
        })->first();

        $response = $this->actingAs($this->adminUser, 'web')->get('/es/es/');

        $response->assertOk();
        $response->assertSee('cms-frontoffice-admin-bar-root', false);
        $response->assertSee(route('admin.pages.edit', $homePage->id), false);
        $response->assertSee('data-cms-edit-url', false);
    }

    public function test_authenticated_admin_can_open_the_blog_post_editor(): void
    {
        /** @var Post $post */
        $post = Post::sole();

        $slugEs = $post->translations->firstWhere('locale', 'es')?->slug;

        $response = $this->actingAs($this->adminUser, 'web')
            ->get('/es/es/blog/'.$slugEs);

        $response->assertOk();
        $response->assertSee('cms-frontoffice-admin-bar-root', false);
        $response->assertSee(route('admin.blog.posts.edit', $post->id), false);
        $response->assertSee('data-cms-edit-url', false);
    }

    public function test_authenticated_admin_sees_direct_edit_link_for_shop_product(): void
    {
        /** @var Product $product */
        $product = Product::sole();

        $response = $this->actingAs($this->adminUser, 'web')
            ->get('/es/es/tienda/prd-edge-1000');

        $response->assertOk();
        $response->assertSee('cms-frontoffice-admin-bar-root', false);
        $response->assertSee(route('admin.shop.products.edit', $product->id), false);
        $response->assertSee('data-cms-edit-url', false);
    }

    public function test_admin_bar_renders_cleanly_across_all_six_themes_when_authenticated(): void
    {
        $themes = [
            'db-cyber',
            'db-editorial',
            'db-enterprise',
            'db-minimal',
            'db-sunset',
            'db-brutalist',
        ];

        foreach ($themes as $theme) {
            config(['cms.theme' => $theme]);

            $response = $this->actingAs($this->adminUser, 'web')->get('/es/es/');
            $response->assertOk();
            $response->assertSee('cms-frontoffice-admin-bar-root', false);
        }
    }

    public function test_admin_bar_loads_the_active_brand_stylesheet(): void
    {
        $this->requireAdministrativeBrand('termocms');
        /** @var AdminThemeServiceInterface $themeService */
        $themeService = app(AdminThemeServiceInterface::class);
        $themeService->setActiveTheme('termocms');
        config(['cms.theme' => 'db-enterprise']);

        $response = $this->actingAs($this->adminUser, 'web')->get('/es/es/');

        $response->assertOk();
        $response->assertSee('cms-frontoffice-admin-bar-root', false);

        $response->assertSee('theme-assets/termocms/admin-bar.css', false);
    }

    public function test_admin_bar_and_seo_drawer_render_dbcms_cyberpunk_when_dbcms_is_active(): void
    {
        $this->requireAdministrativeBrand('dbcms');
        /** @var AdminThemeServiceInterface $themeService */
        $themeService = app(AdminThemeServiceInterface::class);
        $themeService->setActiveTheme('dbcms');
        config(['cms.theme' => 'db-cyber']);

        $response = $this->actingAs($this->adminUser, 'web')->get('/es/es/');

        $response->assertOk();
        $response->assertSee('cms-frontoffice-admin-bar-root', false);
    }

    public function test_theme_manifest_exposes_the_brand_logo_for_the_front_bar(): void
    {
        $this->requireAdministrativeBrand('termocms');
        $themes = app(AdminThemeServiceInterface::class);

        $termo = $themes->getThemeManifest('termocms');

        $this->assertSame('Termosalud', $termo['brand']['name']);
        $this->assertSame('termosalud-logo.png', $termo['brand']['logo']);
        $this->assertSame('termosalud-favicon.png', $termo['brand']['icon']);

    }

    public function test_the_dbcms_manifest_keeps_the_text_wordmark(): void
    {
        $this->requireAdministrativeBrand('dbcms');
        $this->assertNull(app(AdminThemeServiceInterface::class)->getThemeManifest('dbcms')['brand']['logo']);
    }

    public function test_frontoffice_admin_bar_renders_the_theme_brand_logo_instead_of_the_text_wordmark(): void
    {
        $this->requireAdministrativeBrand('termocms');
        app(AdminThemeServiceInterface::class)->setActiveTheme('termocms');
        $this->actingAs($this->adminUser, 'web');

        $rendered = Blade::render('<x-frontoffice-admin-bar />');

        $this->assertStringContainsString('admin-bar-brand-logo', $rendered);
        $this->assertStringContainsString('termosalud-logo.png', $rendered);
    }

    private function requireAdministrativeBrand(string $brand): void
    {
        if (! is_file(resource_path('views/themes/admin/'.$brand.'/theme.json'))) {
            $this->markTestSkipped('Administrative brand '.$brand.' is not distributed in this child.');
        }
    }
}
