<?php

declare(strict_types=1);

use App\Content\Pages\Models\Page;
use App\Contracts\Services\LocalizationServiceInterface;
use App\Contracts\Services\ModuleSettingsServiceInterface;
use App\Core\Modules\ModuleManager;
use App\Http\Controllers\Admin\Seo\SeoAuditGetController;
use App\Models\CmsModule;
use App\Models\Language;
use App\Models\Market;
use App\Models\User;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\Post;
use Modules\Blog\Services\PostSeoSchemaService;
use Modules\Shop\Models\Product;
use Modules\Shop\Models\ProductCategory;
use Tests\Support\PublicContentComposition;

uses(PublicContentComposition::class);

beforeEach(function (): void {
    app(ModuleManager::class)->discoverModules();
    CmsModule::query()->update(['is_active' => true]);
    config(['cms.page_cache.enabled' => false]);
    $es = Language::firstOrCreate(['code' => 'es'], ['name' => 'Spanish', 'native_name' => 'Español', 'is_default' => true, 'is_active' => true]);
    $en = Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'native_name' => 'English', 'is_active' => true]);
    $this->spain = Market::firstOrCreate(['code' => 'es'], ['name' => 'Spain', 'default_locale' => 'es', 'currency' => 'EUR', 'is_default' => true, 'is_active' => true]);
    $this->uk = Market::firstOrCreate(['code' => 'uk'], ['name' => 'United Kingdom', 'default_locale' => 'en', 'currency' => 'GBP', 'is_active' => true]);
    $this->spain->languages()->sync([$es->getKey(), $en->getKey()]);
    $this->uk->languages()->sync([$en->getKey()]);
});

test('GEO exports stay outside the public directory and reject a public destination', function (): void {
    $directory = storage_path('app/private/final-discovery-test-export');
    try {
        $this->artisan('geo:generate-llms-txt', ['--output' => $directory])->assertSuccessful();
        expect(File::exists($directory.'/llms.txt'))->toBeTrue();
        expect(File::exists($directory.'/llms-full.txt'))->toBeTrue();
        $this->artisan('geo:generate-llms-txt', ['--output' => public_path('unsafe-geo-export')])->assertFailed();
        expect(File::exists(public_path('unsafe-geo-export')))->toBeFalse();
    } finally {
        File::deleteDirectory($directory);
    }
});

test('production Nginx always sends GEO documents to the dynamic controller', function (): void {
    $nginx = File::get(base_path('docker/production/nginx.conf'));
    foreach (['llms.txt', 'llms-full.txt'] as $filename) {
        expect($nginx)->toContain('location = /'.$filename);
    }
});

test('taxonomy discovery and routes honour activity and market restrictions', function (): void {
    foreach ([Category::class, ProductCategory::class] as $class) {
        $category = $class::create(['is_active' => true]);
        $category->markets()->sync([$this->spain->getKey()]);
        $category->translations()->createMany([
            ['locale' => 'es', 'name' => 'Categoría reservada', 'slug' => 'categoria-reservada'],
            ['locale' => 'en', 'name' => 'Restricted taxonomy', 'slug' => 'restricted-taxonomy'],
        ]);
    }
    $this->get('/uk/en/llms.txt')->assertOk()->assertDontSee('restricted-taxonomy');
    $this->get('/uk/en/sitemap.xml')->assertOk()->assertDontSee('restricted-taxonomy');
    $this->get('/uk/en/blog/restricted-taxonomy')->assertNotFound();
    $this->get('/uk/en/shop/restricted-taxonomy')->assertNotFound();
    $category = Category::firstOrFail();
    $category->update(['is_active' => false]);
    $this->get('/es/es/blog/categoria-reservada')->assertNotFound();
});

test('the semantic corpus respects hidden prices and contains canonical sources and only published blocks', function (): void {
    app(ModuleSettingsServiceInterface::class)->set('shop', 'shop_mode', 'catalog');
    app(ModuleSettingsServiceInterface::class)->set('shop', 'catalog_show_prices', false);
    $author = User::factory()->create();
    $post = Post::withoutEvents(fn () => Post::create(['user_id' => $author->getKey(), 'is_published' => true, 'published_at' => now()]));
    $post->translations()->create(['locale' => 'es', 'title' => 'Public block article', 'slug' => 'public-block-article', 'content' => 'Legacy article text']);
    $product = Product::withoutEvents(fn () => Product::create(['sku' => 'SOURCE-SKU', 'price' => 8471.63, 'is_active' => true]));
    $product->translations()->create(['locale' => 'es', 'name' => 'Public block product', 'slug' => 'public-block-product']);
    $page = Page::withoutEvents(fn () => Page::create(['is_published' => true, 'published_at' => now(), 'template' => 'default']));
    $page->translations()->create(['locale' => 'es', 'title' => 'Public source page', 'slug' => 'public-source-page']);
    foreach ([$post, $product] as $entity) {
        foreach ([['published', 'Verified public block answer'], ['draft', 'PRIVATE DRAFT BLOCK']] as [$status, $answer]) {
            $section = $entity->sections()->create(['type' => 'faq', 'order' => 0, 'is_active' => true, 'status' => $status, 'styling_json' => []]);
            $section->translations()->create(['locale' => 'es', 'content_json' => ['items' => [['question' => 'What must citizens verify?', 'answer' => $answer]]]]);
        }
    }
    $this->get('/es/es/llms.txt')->assertOk()->assertDontSee('8471.63');
    $this->get('/es/es/llms-full.txt')->assertOk()->assertSee('Verified public block answer')->assertDontSee('PRIVATE DRAFT BLOCK')->assertDontSee('8471.63')
        ->assertSee(url('/es/es/blog/public-block-article'))
        ->assertSee(url('/es/es/tienda/public-block-product'))
        ->assertSee(url('/es/es/public-source-page'));
});

test('hreflang never advertises a product in a disabled market pivot', function (): void {
    $product = Product::withoutEvents(fn () => Product::create(['sku' => 'DISABLED-ALT', 'price' => 10, 'is_active' => true]));
    $product->markets()->sync([$this->spain->getKey() => ['is_active' => true], $this->uk->getKey() => ['is_active' => false]]);
    $links = app(LocalizationServiceInterface::class)->buildHreflangCluster('{shopPrefix}/{slug}', ['es' => 'alternativo', 'en' => 'alternative'], $product);
    expect($links)->toHaveKey('es-ES')->toHaveKey('en-ES')->not->toHaveKey('en-UK');
});

test('consecutive page requests cannot inherit a previous entity hreflang cluster', function (): void {
    foreach (['first-source', 'second-source'] as $slug) {
        $page = Page::withoutEvents(fn () => Page::create(['is_published' => true, 'published_at' => now(), 'template' => 'default']));
        $page->translations()->createMany([
            ['locale' => 'es', 'title' => $slug, 'slug' => $slug],
            ['locale' => 'en', 'title' => $slug.' english', 'slug' => $slug.'-en'],
        ]);
    }
    $this->get('/es/es/first-source')->assertOk();
    $this->get('/es/es/second-source')->assertOk()->assertSee('href="'.url('/es/en/second-source-en').'"', false)
        ->assertDontSee('href="'.url('/es/en/first-source-en').'"', false);
});

test('the SEO overview counts publicly discoverable articles and labels its limits honestly', function (): void {
    $author = User::factory()->create();
    foreach ([true, false] as $published) {
        $post = Post::withoutEvents(fn () => Post::create(['user_id' => $author->getKey(), 'is_published' => $published, 'published_at' => now()]));
        $post->translations()->create(['locale' => 'es', 'title' => $published ? 'Public audited article' : 'Private draft article', 'slug' => $published ? 'audited-public' : 'audited-draft', 'content' => 'Auditable content']);
    }
    $data = app(SeoAuditGetController::class)(request())->getData();
    expect($data['counts']['post']['count'])->toBe(1);
    $auditTemplate = File::get(resource_path('views/admin/seo/audit.blade.php'));
    expect($auditTemplate)->not->toContain('>100%</div>')->toContain('editorial_heuristic');
    $metaBox = Blade::render('<x-admin.seo-meta-box schemaType="WebPage" />');
    expect($metaBox)->not->toContain(__('admin.seo.synced'))->not->toContain('value="FAQPage"')->not->toContain('value="Product"');
});

test('article schema options are implemented and preserve the technical article type', function (): void {
    $author = User::factory()->create();
    $post = Post::withoutEvents(fn () => Post::create(['user_id' => $author->getKey(), 'is_published' => true, 'published_at' => now()]));
    $translation = $post->translations()->create(['locale' => 'es', 'title' => 'Technical source', 'slug' => 'technical-source', 'schema_type' => 'TechArticle', 'content' => 'Technical source text']);
    $schema = app(PostSeoSchemaService::class)->entity($post, $translation, url('/es/es/blog/technical-source'));
    expect($schema['@type'])->toBe('TechArticle');
    $metaBox = Blade::render('<x-admin.seo-meta-box schemaType="TechArticle" />');
    expect($metaBox)->toContain('value="Article"')->toContain('value="TechArticle"')->toContain('value="NewsArticle"')->not->toContain('value="Product"')->not->toContain('value="FAQPage"');
});

test('publication timestamps in the future do not expose pages or articles', function (): void {
    $author = User::factory()->create();
    $post = Post::withoutEvents(fn () => Post::create(['user_id' => $author->getKey(), 'is_published' => true, 'published_at' => now()->addDay()]));
    $post->translations()->create(['locale' => 'es', 'title' => 'Future confidential article', 'slug' => 'future-confidential-article', 'content' => 'Content under embargo']);
    $page = Page::withoutEvents(fn () => Page::create(['is_published' => true, 'published_at' => now()->addDay(), 'template' => 'default']));
    $page->translations()->create(['locale' => 'es', 'title' => 'Future confidential page', 'slug' => 'future-confidential-page']);
    $this->get('/es/es/blog/future-confidential-article')->assertNotFound();
    $this->get('/es/es/future-confidential-page')->assertNotFound();
    $this->get('/es/es/llms-full.txt')->assertOk()->assertDontSee('Future confidential');
    $this->get('/sitemaps/posts.xml')->assertOk()->assertDontSee('future-confidential-article');
    $this->get('/sitemaps/pages.xml')->assertOk()->assertDontSee('future-confidential-page');
    $this->get('/feed.xml')->assertOk()->assertDontSee('future-confidential-article');
});

test('blog semantic exports readable paragraph text while preserving teaching code', function (): void {
    $post = Post::create(['user_id' => User::factory()->create()->getKey(), 'is_published' => true, 'published_at' => now()]);
    $post->translations()->create([
        'locale' => 'es',
        'title' => 'Visible practical answer',
        'slug' => 'visible-practical-answer',
        'summary' => 'Verified public summary.',
        'content' => "## Human verification\n\n<p>Review &amp; verify the original source.</p><p>Keep the approved copy.</p>\n\n```html\n<script>TEACHING CODE EXAMPLE</script>\n```",
    ]);

    $response = $this->get('/es/es/llms-full.txt')->assertOk();
    expect((string) $response->getContent())
        ->toContain("Review & verify the original source.\nKeep the approved copy.")
        ->toContain('Human verification')->toContain('<script>TEACHING CODE EXAMPLE</script>')
        ->not->toContain('<p>')->not->toContain('<h2>')->not->toContain('<pre>');
});
