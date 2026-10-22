<?php

declare(strict_types=1);

namespace Tests\Feature\Cms;

use App\Content\Pages\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Blog\Models\Post;
use Modules\ContentAudit\Services\EditorialWorkflow;
use Modules\Shop\Models\Product;
use Modules\Shop\Models\ProductCategory;
use Tests\Support\BlogFixtures;
use Tests\Support\CmsFixtures;
use Tests\Support\MultilingualEditorialComposition;
use Tests\Support\ShopFixtures;
use Tests\TestCase;

class BackofficeCrudConsistencyTest extends TestCase
{
    use MultilingualEditorialComposition;
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        CmsFixtures::localization(['es', 'en', 'fr']);
        CmsFixtures::identities();
        CmsFixtures::home(['es', 'en', 'fr']);
        BlogFixtures::post(['es', 'en', 'fr']);
        ShopFixtures::product(locales: ['es', 'en', 'fr']);

        /** @var User $admin */
        $admin = User::where('email', 'admin@db-cms.io')->firstOrFail();
        $this->adminUser = $admin;
    }

    public function test_all_admin_index_views_render_unified_datatable_header_and_structure(): void
    {
        $routes = [
            'admin.pages.index',
            'admin.blog.posts.index',
            'admin.blog.categories.index',
            'admin.shop.products.index',
            'admin.shop.categories.index',
            'admin.shop.orders.index',
            'admin.galleries.index',
            'admin.forms.index',
            'admin.users.index',
        ];

        foreach ($routes as $routeName) {
            $response = $this->actingAs($this->adminUser, 'web')->get(route($routeName));
            $response->assertOk();
            $response->assertSee('cyber-card', false);
            $response->assertSee('name="search"', false);
            $response->assertSee('name="per_page"', false);
        }
    }

    public function test_shop_categories_and_blog_categories_share_identical_ux_and_components(): void
    {
        $blogRes = $this->actingAs($this->adminUser, 'web')->get(route('admin.blog.categories.index'));
        $blogRes->assertOk();
        $blogRes->assertSee('open-create-category-modal', false);
        $blogRes->assertSee('confirm-delete', false);

        $shopRes = $this->actingAs($this->adminUser, 'web')->get(route('admin.shop.categories.index'));
        $shopRes->assertOk();
        $shopRes->assertSee('open-create-shop-category-modal', false);
        $shopRes->assertSee('confirm-delete', false);
        $shopRes->assertSee('Volver a productos', false);
        $shopRes->assertSee('Ruta o Slug', false);
    }

    public function test_admin_can_create_and_update_shop_category_with_multilingual_data(): void
    {
        // 1. Create shop category
        $createRes = $this->actingAs($this->adminUser, 'web')->post(route('admin.shop.categories.store'), [
            'translations' => [
                'es' => ['name' => 'Redes Cuánticas', 'slug' => 'redes-cuanticas'],
                'en' => ['name' => 'Quantum Networking', 'slug' => 'quantum-networking'],
            ],
            'icon' => '🛰️',
            'order' => 5,
            'is_active' => '1',
        ]);

        $createRes->assertRedirect(route('admin.shop.categories.index'));

        /** @var ProductCategory $cat */
        $cat = ProductCategory::whereHas('translations', function ($q): void {
            $q->where('slug', 'redes-cuanticas');
        })->firstOrFail();

        $this->assertEquals('🛰️', $cat->icon);
        $this->assertTrue($cat->is_active);
        $this->assertEquals('Redes Cuánticas', $cat->translations->where('locale', 'es')->first()?->name);
        $this->assertEquals('Quantum Networking', $cat->translations->where('locale', 'en')->first()?->name);

        // Published changes remain pending until a human approves them.
        $updateRes = $this->actingAs($this->adminUser, 'web')->put(route('admin.shop.categories.update', $cat->id), [
            'translations' => [
                'es' => ['name' => 'Redes Cuánticas Avanzadas', 'slug' => 'redes-cuanticas-avanzadas'],
                'en' => ['name' => 'Advanced Quantum Networking', 'slug' => 'advanced-quantum-networking'],
            ],
            'parent_id' => null,
            'icon' => '🔬',
            'order' => 10,
            'is_active' => '1',
        ]);

        $updateRes->assertRedirect(route('admin.shop.categories.index'));

        $cat->refresh();
        $this->assertEquals('🛰️', $cat->icon);
        $this->assertEquals('Redes Cuánticas', $cat->translations->where('locale', 'es')->first()?->name);
        app(EditorialWorkflow::class)->approve($cat, $this->adminUser);
        $cat->refresh();
        $this->assertNull($cat->parent_id);
        $this->assertEquals('🔬', $cat->icon);
        $this->assertEquals(10, $cat->order);
        $this->assertEquals('Redes Cuánticas Avanzadas', $cat->translations->where('locale', 'es')->first()?->name);
    }

    public function test_shop_category_rejects_self_parent_without_saving_other_changes(): void
    {
        $category = ProductCategory::create(['icon' => '🛰️', 'order' => 5, 'is_active' => false]);
        $category->translations()->create(['locale' => 'es', 'name' => 'Original', 'slug' => 'original']);
        $response = $this->actingAs($this->adminUser, 'web')->put(route('admin.shop.categories.update', $category->id), [
            'parent_id' => $category->id,
            'icon' => '🔬',
            'order' => 10,
            'is_active' => '0',
            'translations' => ['es' => ['name' => 'Changed', 'slug' => 'changed']],
        ]);
        $response->assertSessionHasErrors('parent_id');
        $category->refresh();
        $this->assertNull($category->parent_id);
        $this->assertEquals('🛰️', $category->icon);
        $this->assertEquals(5, $category->order);
        $this->assertEquals('Original', $category->translations->first()?->name);
    }

    public function test_handles_datatable_query_filters_products_and_orders(): void
    {
        // Search products
        $prodRes = $this->actingAs($this->adminUser, 'web')->get(route('admin.shop.products.index', [
            'search' => 'edge',
        ]));
        $prodRes->assertOk();

        // Filter products by status
        $prodActiveRes = $this->actingAs($this->adminUser, 'web')->get(route('admin.shop.products.index', [
            'is_active' => '1',
        ]));
        $prodActiveRes->assertOk();

        // Search orders
        $orderRes = $this->actingAs($this->adminUser, 'web')->get(route('admin.shop.orders.index', [
            'search' => 'ORD-',
        ]));
        $orderRes->assertOk();
    }

    public function test_form_header_is_rendered_across_create_and_edit_views(): void
    {
        /** @var Page $page */
        $page = Page::firstOrFail();
        /** @var Post $post */
        $post = Post::firstOrFail();
        /** @var Product $prod */
        $prod = Product::firstOrFail();

        // Create forms
        $this->actingAs($this->adminUser, 'web')->get(route('admin.pages.create'))->assertSee('Volver al listado');
        $this->actingAs($this->adminUser, 'web')->get(route('admin.blog.posts.create'))->assertSee('Volver al listado');
        $this->actingAs($this->adminUser, 'web')->get(route('admin.shop.products.create'))->assertSee('Volver al catálogo');
        $this->actingAs($this->adminUser, 'web')->get(route('admin.galleries.create'))->assertSee('Volver al listado');

        // Edit forms
        $this->actingAs($this->adminUser, 'web')->get(route('admin.pages.edit', $page->id))->assertSee('Volver al listado');
        $this->actingAs($this->adminUser, 'web')->get(route('admin.blog.posts.edit', $post->id))->assertSee('Volver al listado');
        $this->actingAs($this->adminUser, 'web')->get(route('admin.shop.products.edit', $prod->id))->assertSee('Volver al catálogo');
    }
}
