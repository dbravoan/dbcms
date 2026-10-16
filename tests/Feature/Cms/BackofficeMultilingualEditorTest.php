<?php

declare(strict_types=1);

namespace Tests\Feature\Cms;

use App\Content\Pages\Contracts\PageDomainServiceInterface;
use App\Content\Pages\Models\Page;
use App\Core\Sections\SectionEditorService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Blog\Contracts\PostDomainServiceInterface;
use Modules\Blog\Models\Post;
use Modules\ContentAudit\Services\EditorialWorkflow;
use Modules\Shop\Contracts\ShopEditorialServiceInterface;
use Modules\Shop\Models\Product;
use Tests\Support\BlogFixtures;
use Tests\Support\CmsFixtures;
use Tests\Support\MultilingualEditorialComposition;
use Tests\Support\ShopFixtures;
use Tests\TestCase;

class BackofficeMultilingualEditorTest extends TestCase
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
        $admin = User::where('email', 'admin@db-cms.io')->first();
        $this->adminUser = $admin;
    }

    protected function tearDown(): void
    {
        config(['cms.theme' => 'db-cyber']);
        parent::tearDown();
    }

    public function test_page_editor_defaults_to_primary_language_and_loads_translations_per_locale(): void
    {
        /** @var Page $page */
        $page = Page::whereHas('translations', function ($q): void {
            $q->where('slug', 'home');
        })->first();

        $response = $this->actingAs($this->adminUser, 'web')
            ->get(route('admin.pages.edit', $page->id));

        $response->assertOk();
        $response->assertSee('activeLocale: \'es\'', false);
        $response->assertSee('Copiar contenidos y bloques', false);
    }

    public function test_admin_can_save_distinct_sections_per_locale_using_localized_dictionary(): void
    {
        /** @var Page $page */
        $page = Page::first();

        $liveSections = app(SectionEditorService::class)->payload($page);

        $sectionsByLocale = [
            'es' => [
                [
                    'id' => 'sec_es_1',
                    'type' => 'text',
                    'title' => 'Sección en Español',
                    'content' => 'Contenido auténtico en castellano.',
                ],
            ],
            'en' => [
                [
                    'id' => 'sec_en_1',
                    'type' => 'text',
                    'title' => 'English Section',
                    'content' => 'Authentic English content.',
                ],
            ],
            'fr' => [
                [
                    'id' => 'sec_fr_1',
                    'type' => 'text',
                    'title' => 'Section en Français',
                    'content' => 'Contenu authentique en français.',
                ],
            ],
        ];

        $payload = [
            'translations' => [
                'es' => ['title' => 'Página Multi-Idioma', 'slug' => 'pagina-multi-idioma', 'meta_title' => 'SEO ES', 'meta_description' => 'Desc ES'],
                'en' => ['title' => 'Multi-Language Page', 'slug' => 'multi-language-page', 'meta_title' => 'SEO EN', 'meta_description' => 'Desc EN'],
                'fr' => ['title' => 'Page Multi-Langue', 'slug' => 'page-multi-langue', 'meta_title' => 'SEO FR', 'meta_description' => 'Desc FR'],
            ],
            'sections_json' => json_encode($sectionsByLocale),
            'is_published' => '1',
            'template' => 'default',
        ];

        $response = $this->actingAs($this->adminUser, 'web')
            ->put(route('admin.pages.update', $page->id), $payload);

        $response->assertRedirect();

        // Refresh and verify distinct translations exist
        $response->assertSessionHasNoErrors();
        $live = $page->fresh();
        $this->assertTrue($live->is_published);
        $this->assertSame($liveSections, app(SectionEditorService::class)->payload($live));
        $page = app(PageDomainServiceInterface::class)->editData($live)['page'];
        $pendingSections = app(SectionEditorService::class)->payload($page);
        $this->assertNotEmpty($page->sections);

        $firstSection = $page->sections->first();
        $this->assertNotNull($firstSection);

        $transEs = $firstSection->translations->where('locale', 'es')->first();
        $transEn = $firstSection->translations->where('locale', 'en')->first();
        $transFr = $firstSection->translations->where('locale', 'fr')->first();

        $this->assertNotNull($transEs);
        $this->assertEquals('Sección en Español', $transEs->content_json['title'] ?? null);

        $this->assertNotNull($transEn);
        $this->assertEquals('English Section', $transEn->content_json['title'] ?? null);

        $this->assertNotNull($transFr);
        $this->assertEquals('Section en Français', $transFr->content_json['title'] ?? null);
        app(EditorialWorkflow::class)->approve($live, $this->adminUser);
        $this->assertSame($pendingSections, app(SectionEditorService::class)->payload($live->fresh()));
        $this->assertTrue($live->fresh()->is_published);
    }

    public function test_product_editor_defaults_to_primary_language_and_supports_multilingual_sections(): void
    {
        /** @var Product $product */
        $product = Product::first();

        $response = $this->actingAs($this->adminUser, 'web')
            ->get(route('admin.shop.products.edit', $product->id));

        $response->assertOk();
        $response->assertSee('activeLocale: \'es\'', false);
        $response->assertSee('Copiar contenidos y bloques', false);

        $liveSections = app(SectionEditorService::class)->payload($product);

        $sectionsByLocale = [
            'es' => [
                [
                    'id' => 'sec_prod_es_1',
                    'type' => 'text',
                    'title' => 'Especificaciones ES',
                    'content' => 'Detalle del producto en español.',
                ],
            ],
            'en' => [
                [
                    'id' => 'sec_prod_en_1',
                    'type' => 'text',
                    'title' => 'Specifications EN',
                    'content' => 'Product details in English.',
                ],
            ],
        ];

        $updateResponse = $this->actingAs($this->adminUser, 'web')
            ->put(route('admin.shop.products.update', $product->id), [
                'sku' => $product->sku,
                'price' => 199.99,
                'translations' => [
                    'es' => ['name' => 'Producto Multi-Idioma', 'slug' => 'producto-multi-idioma'],
                    'en' => ['name' => 'Multi-Language Product', 'slug' => 'multi-language-product'],
                ],
                'sections_json' => json_encode($sectionsByLocale),
            ]);

        $updateResponse->assertRedirect();

        $updateResponse->assertSessionHasNoErrors();
        $live = $product->fresh();
        $this->assertTrue($live->is_active);
        $this->assertSame($liveSections, app(SectionEditorService::class)->payload($live));
        $product = app(ShopEditorialServiceInterface::class)->editProduct($live)['product'];
        $pendingSections = app(SectionEditorService::class)->payload($product);
        $firstSection = $product->sections->first();
        $this->assertNotNull($firstSection);

        $transEs = $firstSection->translations->where('locale', 'es')->first();
        $transEn = $firstSection->translations->where('locale', 'en')->first();

        $this->assertNotNull($transEs);
        $this->assertEquals('Especificaciones ES', $transEs->content_json['title'] ?? null);

        $this->assertNotNull($transEn);
        $this->assertEquals('Specifications EN', $transEn->content_json['title'] ?? null);
        app(EditorialWorkflow::class)->approve($live, $this->adminUser);
        $this->assertSame($pendingSections, app(SectionEditorService::class)->payload($live->fresh()));
        $this->assertTrue($live->fresh()->is_active);
    }

    public function test_post_editor_defaults_to_primary_language_and_supports_multilingual_sections(): void
    {
        /** @var Post $post */
        $post = Post::first();

        $response = $this->actingAs($this->adminUser, 'web')
            ->get(route('admin.blog.posts.edit', $post->id));

        $response->assertOk();
        $response->assertSee('activeLocale: \'es\'', false);
        $response->assertSee('Copiar contenidos y bloques', false);

        $liveSections = app(SectionEditorService::class)->payload($post);

        $sectionsByLocale = [
            'es' => [
                [
                    'id' => 'sec_post_es_1',
                    'type' => 'text',
                    'title' => 'Párrafo Principal ES',
                    'content' => 'Noticia en español.',
                ],
            ],
            'en' => [
                [
                    'id' => 'sec_post_en_1',
                    'type' => 'text',
                    'title' => 'Main Paragraph EN',
                    'content' => 'Article in English.',
                ],
            ],
        ];

        $updateResponse = $this->actingAs($this->adminUser, 'web')
            ->put(route('admin.blog.posts.update', $post->id), [
                'translations' => [
                    'es' => ['title' => 'Post Multi-Idioma', 'slug' => 'post-multi-idioma'],
                    'en' => ['title' => 'Multi-Language Post', 'slug' => 'multi-language-post'],
                ],
                'sections_json' => json_encode($sectionsByLocale),
            ]);

        $updateResponse->assertRedirect();

        $updateResponse->assertSessionHasNoErrors();
        $live = $post->fresh();
        $this->assertTrue($live->is_published);
        $this->assertSame($liveSections, app(SectionEditorService::class)->payload($live));
        $post = app(PostDomainServiceInterface::class)->editData($live)['post'];
        $pendingSections = app(SectionEditorService::class)->payload($post);
        $firstSection = $post->sections->first();
        $this->assertNotNull($firstSection);

        $transEs = $firstSection->translations->where('locale', 'es')->first();
        $transEn = $firstSection->translations->where('locale', 'en')->first();

        $this->assertNotNull($transEs);
        $this->assertEquals('Párrafo Principal ES', $transEs->content_json['title'] ?? null);

        $this->assertNotNull($transEn);
        $this->assertEquals('Main Paragraph EN', $transEn->content_json['title'] ?? null);
        app(EditorialWorkflow::class)->approve($live, $this->adminUser);
        $this->assertSame($pendingSections, app(SectionEditorService::class)->payload($live->fresh()));
        $this->assertTrue($live->fresh()->is_published);
    }

    public function test_seo_meta_box_and_block_manager_rendered_with_proper_spanish_and_bindings(): void
    {
        /** @var Page $page */
        $page = Page::first();
        /** @var Post $post */
        $post = Post::first();
        /** @var Product $product */
        $product = Product::first();

        // 1. Page Edit View
        $pageResponse = $this->actingAs($this->adminUser, 'web')
            ->get(route('admin.pages.edit', $page->id));
        $pageResponse->assertOk();
        $pageResponse->assertSee('Copiloto SEO y optimización GEO', false);
        $pageResponse->assertSee('Gestor de bloques', false);
        $pageResponse->assertSee('activeMetaTitle', false);
        $pageResponse->assertSee('activeMetaDesc', false);
        $pageResponse->assertSee('Añadir sección', false);

        // 2. Post Edit View
        $postResponse = $this->actingAs($this->adminUser, 'web')
            ->get(route('admin.blog.posts.edit', $post->id));
        $postResponse->assertOk();
        $postResponse->assertSee('Copiloto SEO y optimización GEO', false);
        $postResponse->assertSee('Gestor de bloques', false);
        $postResponse->assertSee('activeMetaTitle', false);

        // 3. Product Edit View
        $productResponse = $this->actingAs($this->adminUser, 'web')
            ->get(route('admin.shop.products.edit', $product->id));
        $productResponse->assertOk();
        $productResponse->assertSee('Copiloto SEO y optimización GEO', false);
        $productResponse->assertSee('Gestor de bloques', false);
        $productResponse->assertSee('activeMetaTitle', false);
    }
}
