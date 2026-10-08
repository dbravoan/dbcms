<?php

declare(strict_types=1);

namespace Tests\Feature\Cms;

use App\Contracts\Services\AdminThemeServiceInterface;
use App\Models\User;
use Database\Seeders\PlatformShowcaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Modules\Blog\Models\Post;
use Modules\Pages\Models\Page;
use Modules\Shop\Models\Product;
use Tests\TestCase;

class FrontofficeAdminBarAndQuickEditTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlatformShowcaseSeeder::class);

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
        $response->assertDontSee('Auditoría SEO & GEO en Tiempo Real', false);
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

    public function test_authenticated_admin_sees_frontoffice_bar_with_page_edit_and_seo_geo_score_on_home(): void
    {
        $homePage = Page::whereHas('translations', function ($q): void {
            $q->where('slug', 'home');
        })->first();

        $response = $this->actingAs($this->adminUser, 'web')->get('/es/es/');

        $response->assertOk();
        $response->assertSee('cms-frontoffice-admin-bar-root', false);
        $response->assertSee('Backoffice');
        $response->assertSee('Editar Página');
        $response->assertSee(route('admin.pages.edit', $homePage->id), false);
        $response->assertSee('Clic para Editar:');
        $response->assertSee('SEO / GEO:');
        $response->assertSee('Auditoría SEO', false);
        $response->assertSee('Consejos y Mejoras Accionables');
        $response->assertSee('data-cms-edit-url', false);
        $response->assertDontSee('+ Nuevo');
    }

    public function test_authenticated_admin_sees_direct_edit_link_for_blog_post_and_seo_geo_tips(): void
    {
        /** @var Post $post */
        $post = Post::whereHas('translations', function ($q): void {
            $q->where('locale', 'es')->where('title', 'like', 'Arquitectura limpia%');
        })->firstOrFail();

        $slugEs = $post->translations->firstWhere('locale', 'es')?->slug;

        $response = $this->actingAs($this->adminUser, 'web')
            ->get('/es/es/blog/'.$slugEs);

        $response->assertOk();
        $response->assertSee('cms-frontoffice-admin-bar-root', false);
        $response->assertSee('Editar Artículo');
        $response->assertSee(route('admin.blog.posts.edit', $post->id), false);
        $response->assertSee('Estructura editorial');
        $response->assertSee('Criterios editoriales SEO');
        $response->assertSee('Superficie disponible; sin citas medidas');
        $response->assertSee('Heurística editorial local. No mide posicionamiento, factualidad ni citas de IA.');
        $response->assertSee('criterios editoriales locales; prioriza las necesidades de tus lectores.');
        $response->assertDontSee('Citabilidad GEO');
        $response->assertDontSee('/llms.txt OK');
        $response->assertDontSee('elevar el score al 100%');
        $response->assertDontSee('citabilidad en ChatGPT, Gemini y Perplexity');
        $response->assertSee('data-cms-edit-url', false);
    }

    public function test_authenticated_admin_sees_direct_edit_link_for_shop_product(): void
    {
        /** @var Product $product */
        $product = Product::whereHas('translations', function ($q): void {
            $q->where('slug', 'servidor-edge-cyber-kv-10g');
        })->first();

        $response = $this->actingAs($this->adminUser, 'web')
            ->get('/es/es/tienda/servidor-edge-cyber-kv-10g');

        $response->assertOk();
        $response->assertSee('cms-frontoffice-admin-bar-root', false);
        $response->assertSee('Editar Producto');
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
            $response->assertSee('Backoffice');
            $response->assertSee('Editar Página');
            $response->assertSee('SEO / GEO:', false);
            $response->assertSee('fa-bolt', false);
        }
    }

    public function test_admin_bar_and_seo_drawer_render_isolated_termocms_design_when_termocms_is_active(): void
    {
        /** @var AdminThemeServiceInterface $themeService */
        $themeService = app(AdminThemeServiceInterface::class);
        $themeService->setActiveTheme('termocms');
        config(['cms.theme' => 'db-enterprise']);

        $response = $this->actingAs($this->adminUser, 'web')->get('/es/es/');

        $response->assertOk();
        $response->assertSee('cms-frontoffice-admin-bar-root', false);
        $response->assertSee('admin-bar-surface', false);
        $response->assertSee('admin-bar-btn-primary', false);
        $response->assertSee('admin-bar-drawer', false);
        $response->assertSee('admin-bar-score-btn', false);
        $response->assertSee('Backoffice', false);

        // The bar loads the active theme's token stylesheet and maps its chrome
        // to the --admin-bar-* variables instead of hardcoded dbCMS colors.
        $response->assertSee('theme-assets/termocms/admin-bar.css', false);
        $response->assertSee('var(--admin-bar-accent', false);

        $tokens = file_get_contents(resource_path('views/themes/admin/termocms/assets/admin-bar.css'));
        expect($tokens)->toContain('html.theme-termocms')
            ->toContain('--admin-bar-accent: #007B90')
            ->toContain('--admin-bar-bg:')
            ->toContain("--admin-bar-font: 'Plus Jakarta Sans'");
    }

    public function test_admin_bar_and_seo_drawer_render_dbcms_cyberpunk_when_dbcms_is_active(): void
    {
        /** @var AdminThemeServiceInterface $themeService */
        $themeService = app(AdminThemeServiceInterface::class);
        $themeService->setActiveTheme('dbcms');
        config(['cms.theme' => 'db-cyber']);

        $response = $this->actingAs($this->adminUser, 'web')->get('/es/es/');

        $response->assertOk();
        $response->assertSee('cms-frontoffice-admin-bar-root', false);
        $response->assertSee('dbCMS', false);
        $response->assertSee('Backoffice', false);
        $response->assertSee('#00FF99', false);
        $response->assertSee('admin-bar-drawer', false);
    }

    public function test_theme_manifest_exposes_the_brand_logo_for_the_front_bar(): void
    {
        $themes = app(AdminThemeServiceInterface::class);

        $termo = $themes->getThemeManifest('termocms');

        $this->assertSame('Termosalud', $termo['brand']['name']);
        $this->assertSame('termosalud-logo.png', $termo['brand']['logo']);
        $this->assertSame('termosalud-favicon.png', $termo['brand']['icon']);

        // Themes without a declared brand keep the text wordmark.
        $this->assertNull($themes->getThemeManifest('dbcms')['brand']['logo']);
    }

    public function test_frontoffice_admin_bar_renders_the_theme_brand_logo_instead_of_the_text_wordmark(): void
    {
        app(AdminThemeServiceInterface::class)->setActiveTheme('termocms');
        $this->actingAs($this->adminUser, 'web');

        $rendered = Blade::render('<x-frontoffice-admin-bar />');

        $this->assertStringContainsString('admin-bar-brand-logo', $rendered);
        $this->assertStringContainsString('termosalud-logo.png', $rendered);
    }

    public function test_seo_drawer_styles_are_fully_token_driven_like_the_topbar(): void
    {
        app(AdminThemeServiceInterface::class)->setActiveTheme('termocms');
        $this->actingAs($this->adminUser, 'web');

        $rendered = Blade::render('<x-frontoffice-admin-bar />');

        // The SEO drawer (showSeoDrawer) must follow the active theme: every
        // panel, its short `admin-btn-primary` CTA alias, the mono-font reset and
        // the neon-accent catch-all are bound to --admin-bar-* tokens.
        $this->assertStringContainsString('.admin-bar-drawer', $rendered);
        $this->assertStringContainsString('.admin-stat-box', $rendered);
        $this->assertStringContainsString('.admin-btn-primary', $rendered);
        $this->assertStringContainsString('.admin-bar-root .font-mono', $rendered);
        $this->assertStringContainsString('.admin-bar-root .text-\[\#00FF99\]', $rendered);
        $this->assertStringContainsString('var(--admin-bar-panel)', $rendered);
    }
}
