<?php

declare(strict_types=1);

namespace Tests\Feature\Cms;

use App\Services\Cms\ThemeManagerService;
use Database\Seeders\PlatformShowcaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\Post;
use Modules\Shop\Models\Product;
use Modules\Shop\Services\CartService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ThemeParityAndIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlatformShowcaseSeeder::class);
    }

    protected function tearDown(): void
    {
        $this->useTheme('db-cyber');
        parent::tearDown();
    }

    /**
     * Switches the active front theme the way the runtime does.
     *
     * Front themes are resolved per request; within a single test process the view
     * finder/Blade compiled maps must be dropped when the theme changes.
     */
    protected function useTheme(string $theme): void
    {
        config(['cms.theme' => $theme]);

        // Mirrors what the runtime does when the theme changes in a long-lived
        // process (see ThemeManagerService::syncActiveTheme).
        app(ThemeManagerService::class)->syncActiveTheme();
    }

    public function test_all_front_themes_are_discovered_with_valid_manifests(): void
    {
        $themes = app(ThemeManagerService::class)->getAvailableThemes();

        $this->assertNotEmpty($themes, 'At least one front theme must be available.');

        foreach (array_keys($themes) as $themeId) {
            $this->assertArrayHasKey($themeId, $themes, "Theme '{$themeId}' must be registered.");
            $this->assertNotEmpty($themes[$themeId]['name'], "Theme '{$themeId}' must have a name.");
        }
    }

    public function test_all_front_themes_have_complete_sixteen_view_component_manifest(): void
    {
        $themeNames = array_keys(app(ThemeManagerService::class)->getAvailableThemes());

        $requiredViews = [
            'layouts/app.blade.php',
            'partials/header.blade.php',
            'partials/footer.blade.php',
            'pages/show.blade.php',
            'blog/index.blade.php',
            'blog/show.blade.php',
            'blog/category.blade.php',
            'gallery/index.blade.php',
            'gallery/show.blade.php',
            'shop/index.blade.php',
            'shop/show.blade.php',
            'shop/cart.blade.php',
            'shop/checkout.blade.php',
            'shop/order_success.blade.php',
            'shop/widgets/featured_products.blade.php',
            'theme.json',
        ];

        foreach ($themeNames as $theme) {
            $baseDir = base_path("themes/{$theme}");
            $this->assertDirectoryExists($baseDir, "Theme directory 'themes/{$theme}' must exist.");

            foreach ($requiredViews as $viewFile) {
                $filePath = "{$baseDir}/{$viewFile}";
                $this->assertFileExists($filePath, "Theme '{$theme}' is missing view file: {$viewFile}");
            }
        }
    }

    public function test_all_front_themes_render_all_core_routes_successfully(): void
    {
        $themes = array_keys(app(ThemeManagerService::class)->getAvailableThemes());

        // Routes are derived from the seeded showcase content instead of
        // hardcoded demo slugs: the demo data must be allowed to evolve.
        /** @var Post $post */
        $post = Post::whereHas('translations', fn ($q) => $q->where('locale', 'es')->where('title', 'like', 'Qué es dbCMS%'))->firstOrFail();
        $postSlug = (string) $post->translations->firstWhere('locale', 'es')?->slug;

        /** @var Category $category */
        $category = Category::whereHas('translations', fn ($q) => $q->where('slug', 'tecnologia'))->firstOrFail();
        $categorySlug = (string) $category->translations->firstWhere('locale', 'es')?->slug;

        /** @var Product $product */
        $product = Product::whereHas('translations', fn ($q) => $q->where('slug', 'servidor-edge-cyber-kv-10g'))->firstOrFail();
        $productSlug = (string) $product->translations->firstWhere('locale', 'es')?->slug;

        $routesToTest = [
            '/' => 302, // Redirects to /es/es/
            '/es/es/' => 200,
            '/es/es/blog' => 200,
            "/es/es/blog/{$postSlug}" => 200,
            "/es/es/blog/{$categorySlug}" => 200,
            '/es/es/tienda' => 200,
            "/es/es/tienda/{$productSlug}" => 200,
            '/es/es/tienda/cart' => 200,
            '/es/es/tienda/checkout' => 200,
            '/es/es/bloques' => 200,
        ];

        app(CartService::class)->add($product->id, 1);

        foreach ($themes as $theme) {
            config(['cms.theme' => $theme]);

            foreach ($routesToTest as $route => $expectedStatus) {
                $response = $this->get($route);
                $this->assertEquals(
                    $expectedStatus,
                    $response->getStatusCode(),
                    "Theme [{$theme}] failed route [{$route}] with status {$response->getStatusCode()}"
                );
            }
        }
    }

    public function test_non_cyber_themes_do_not_leak_cyber_styles(): void
    {
        $nonCyberThemes = array_values(array_diff(
            array_keys(app(ThemeManagerService::class)->getAvailableThemes()),
            ['db-cyber']
        ));

        foreach ($nonCyberThemes as $theme) {
            config(['cms.theme' => $theme]);

            foreach (['/es/es/blog', '/es/es/tienda', '/es/es/bloques'] as $path) {
                $response = $this->get($path);
                $response->assertOk();

                // `cyber-card` is the dbCMS theme marker and must never appear in
                // another theme's markup.
                $response->assertDontSee('cyber-card', false);

                // The public <main> of the theme must not carry the neon cyber
                // accent; shared chrome (admin bar, modals, section editor), which is
                // themed separately, is excluded.
                $main = $this->mainContent($response->getContent());
                $this->assertStringNotContainsString('#00FF99', $main, "Theme [{$theme}] leaked the cyber accent inside <main> on {$path}.");

                // Shared blocks and the section renderer consume design tokens,
                // so the showcase must render semantic component classes.
                $this->assertStringContainsString('cms-', $main, "Theme [{$theme}] rendered shared components without design tokens on {$path}.");
            }
        }
    }

    /**
     * Extracts the theme's public <main> content from the rendered page.
     */
    private function mainContent(string $html): string
    {
        if (preg_match('/<main\b[^>]*>(.*?)<\/main>/s', $html, $matches) === 1) {
            return $matches[1];
        }

        return $html;
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function designMarkers(): array
    {
        return [
            'db-editorial' => ['db-editorial', 'font-serif'],
            'db-minimal' => ['db-minimal', 'tracking-widest'],
            'db-sunset' => ['db-sunset', 'sunset-card'],
            'db-brutalist' => ['db-brutalist', 'shadow-['],
        ];
    }

    #[DataProvider('designMarkers')]
    public function test_themes_display_their_distinct_design_markers(string $theme, string $marker): void
    {
        $this->useTheme($theme);

        $response = $this->get('/es/es/blog');
        $response->assertOk();
        $response->assertSee($marker, false);

        // Per-theme rules ship inside the compiled bundle (not inline anymore).
        $bundle = (string) file_get_contents(base_path('resources/css/themes.css'));
        $expectations = [
            'db-editorial' => '.front-db-editorial .editorial-card',
            'db-minimal' => '.front-db-minimal .minimal-card',
            'db-sunset' => '.front-db-sunset .sunset-card',
            'db-brutalist' => '.front-db-brutalist .brutalist-card',
        ];

        expect($bundle)->toContain($expectations[$theme]);
    }
}
