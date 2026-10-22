<?php

declare(strict_types=1);

namespace Tests\Feature\Cms;

use App\Content\Pages\Models\Page;
use App\Content\Pages\Models\PageSection;
use App\Content\Pages\Models\PageTranslation;
use App\Core\Blocks\Contracts\BlockRegistryInterface;
use App\Models\Market;
use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\Cms\MenuService;
use Database\Seeders\PlatformShowcaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Forms\Models\CustomForm;
use Modules\Forms\Models\FormFieldTranslation;
use Modules\Gallery\Blocks\GalleryAlbumBlock;
use Modules\Gallery\Models\GalleryItem;
use Modules\Gallery\Models\GalleryTranslation;
use Modules\Shop\Blocks\ShopProductsBlock;
use Modules\Shop\Models\Product;
use Modules\Shop\Models\ProductTranslation;
use Symfony\Component\Process\Process;
use Tests\Support\DemoComposition;
use Tests\TestCase;

class DemoSeederConsistencyAndParityTest extends TestCase
{
    use DemoComposition, RefreshDatabase;

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

    public function test_explicit_demo_rerun_restores_owned_rows_without_replacing_passwords_or_foreign_content(): void
    {
        $pageTranslation = PageTranslation::where('locale', 'en')->where('slug', 'how-it-works')->firstOrFail();
        $page = $pageTranslation->page;
        $section = $page->sections()->firstOrFail();
        $sectionType = $section->type;
        $product = Product::where('sku', 'PRD-EDGE-1000')->firstOrFail();
        $user = User::where('email', 'editor@db-cms.io')->firstOrFail();
        $user->update(['name' => 'Edited name', 'email' => 'renamed@example.com', 'password' => 'my-changed-password']);
        $password = $user->fresh()->password;
        $pageTranslation->update(['slug' => 'edited-guide', 'title' => 'Edited guide']);
        $page->update(['is_published' => false, 'template' => 'edited']);
        $page->markets()->detach();
        $section->update(['type' => 'edited', 'order' => 999, 'is_active' => false, 'status' => 'draft', 'published_at' => null]);
        $menuItems = Menu::where('slug', 'header')->firstOrFail()->rootItems()->orderBy('order')->take(2)->get();
        $menuItem = $menuItems[1];
        $menuItem->update(['parent_id' => $menuItems[0]->id, 'target_id' => $page->id, 'target_blank' => true]);
        $product->update(['sku' => 'EDITED-SKU', 'is_featured' => false, 'price' => 1]);
        $foreign = Page::create(['template' => 'default', 'layout' => 'app', 'is_published' => false]);
        $foreignTranslation = PageTranslation::create(['page_id' => $foreign->id, 'locale' => 'es', 'title' => 'Private page', 'slug' => 'private-page']);
        $foreignGrant = Permission::create(['slug' => 'private:permission', 'name' => 'Private permission']);
        $adminRole = Role::where('slug', 'admin')->firstOrFail();
        $adminRole->permissions()->attach($foreignGrant);
        $viewer = User::where('email', 'viewer@db-cms.io')->firstOrFail();
        $viewer->roles()->sync([$adminRole->id]);
        $originalIds = collect(Setting::getValue(PlatformShowcaseSeeder::INVENTORY_KEY))->map(fn (array $row): int => $row['id'])->all();

        $this->seed(PlatformShowcaseSeeder::class);

        $this->assertSame($originalIds, collect(Setting::getValue(PlatformShowcaseSeeder::INVENTORY_KEY))->map(fn (array $row): int => $row['id'])->all());
        $this->assertSame('how-it-works', $pageTranslation->fresh()->slug);
        $this->assertTrue($page->fresh()->is_published);
        $this->assertTrue($page->markets()->where('markets.code', 'eu')->exists());
        $this->assertSame($sectionType, $section->fresh()->type);
        $this->assertNotSame(999, $section->fresh()->order);
        $this->assertTrue($section->fresh()->is_active);
        $this->assertSame('published', $section->fresh()->status);
        $this->assertNotNull($section->fresh()->published_at);
        $this->assertTrue($page->publishedSections()->whereKey($section->id)->exists());
        $this->assertNull($menuItem->fresh()->parent_id);
        $this->assertNull($menuItem->fresh()->target_id);
        $this->assertSame(0, (int) $menuItem->fresh()->target_blank);
        $this->assertTrue($menuItem->load('menu')->menu->rootItems()->whereKey($menuItem->id)->exists());
        $this->assertSame('PRD-EDGE-1000', $product->fresh()->sku);
        $this->assertTrue($product->fresh()->is_featured);
        $this->assertSame('editor@db-cms.io', $user->fresh()->email);
        $this->assertSame($password, $user->fresh()->password);
        $this->assertSame('private-page', $foreignTranslation->fresh()->slug);
        $this->assertFalse($foreign->fresh()->is_published);
        $this->assertTrue($adminRole->permissions()->whereKey($foreignGrant->id)->exists());
        $this->assertSame(['viewer'], $viewer->roles()->pluck('slug')->all());
    }

    public function test_demo_rerun_restores_swapped_owned_order_and_unique_identities(): void
    {
        $sections = PageTranslation::where('locale', 'es')->where('slug', 'home')->firstOrFail()->page->sections()->orderBy('order')->take(2)->get();
        [$first, $second] = [$sections[0], $sections[1]];
        $firstOrder = $first->order;
        $secondOrder = $second->order;
        $first->update(['order' => $secondOrder]);
        $second->update(['order' => $firstOrder]);
        $editor = User::where('email', 'editor@db-cms.io')->firstOrFail();
        $author = User::where('email', 'author@db-cms.io')->firstOrFail();
        $editor->update(['password' => 'edited-password', 'email' => 'temporary@example.com']);
        $password = $editor->fresh()->password;
        $author->update(['email' => 'editor@db-cms.io']);
        $editor->update(['email' => 'author@db-cms.io']);
        $modules = PageTranslation::where('locale', 'en')->where('slug', 'modules')->firstOrFail();
        $guide = PageTranslation::where('locale', 'en')->where('slug', 'how-it-works')->firstOrFail();
        $modules->update(['slug' => 'temporary-slug']);
        $guide->update(['slug' => 'modules']);
        $modules->update(['slug' => 'how-it-works']);
        $server = Product::where('sku', 'PRD-EDGE-1000')->firstOrFail();
        $core = Product::where('sku', 'DBCMS-CORE')->firstOrFail();
        $server->update(['sku' => 'TEMP-SKU']);
        $core->update(['sku' => 'PRD-EDGE-1000']);
        $server->update(['sku' => 'DBCMS-CORE']);

        $this->seed(PlatformShowcaseSeeder::class);

        $this->assertSame($firstOrder, $first->fresh()->order);
        $this->assertSame($secondOrder, $second->fresh()->order);
        $this->assertSame('editor@db-cms.io', $editor->fresh()->email);
        $this->assertSame('author@db-cms.io', $author->fresh()->email);
        $this->assertSame($password, $editor->fresh()->password);
        $this->assertSame('modules', $modules->fresh()->slug);
        $this->assertSame('how-it-works', $guide->fresh()->slug);
        $this->assertSame('PRD-EDGE-1000', $server->fresh()->sku);
        $this->assertSame('DBCMS-CORE', $core->fresh()->sku);
    }

    public function test_localized_demo_menu_links_and_blocks_resolve_real_resources(): void
    {
        $market = Market::where('code', 'eu')->firstOrFail();
        $items = app(MenuService::class)->getMenu('header', $market, 'en');
        $urls = array_column($items, 'url');
        $this->assertContains(url('/eu/en/how-it-works'), $urls);
        $this->assertContains(url('/eu/en/modules'), $urls);
        $this->assertContains(url('/eu/en/contact'), $urls);
        $this->assertNotContains(url('/eu/en/como-funciona'), $urls);
        $this->assertNotContains(url('/eu/en/contacto'), $urls);
        $frenchItems = app(MenuService::class)->getMenu('header', $market, 'fr');
        $frenchTitles = array_column($frenchItems, 'title');
        $this->assertContains(url('/eu/fr/comment-ca-marche'), array_column($frenchItems, 'url'));
        $this->assertNotContains('Contact', $frenchTitles);
        $gallery = app(GalleryAlbumBlock::class);
        $album = GalleryTranslation::where('locale', 'en')->where('slug', 'platform-tour')->firstOrFail();
        $image = GalleryItem::where('gallery_id', $album->gallery_id)->firstOrFail();
        $this->assertStringContainsString($image->image_url, $gallery->text(['album_slug' => 'platform-tour'], $market, 'en'));
        $this->assertStringContainsString(htmlspecialchars($image->image_url, ENT_QUOTES, 'UTF-8'), $gallery->render(['album_slug' => 'platform-tour'], $market, 'en'));
        $products = app(ShopProductsBlock::class)->text(['selection' => 'featured'], $market, 'en');
        $this->assertStringContainsString('Cyber-KV 10G Edge Server', $products);
        $this->assertStringNotContainsString('dbCMS Core (included)', $products);
        $this->assertStringNotContainsString('MCP connector', $products);
        foreach (PageSection::whereIn('type', ['gallery', 'widget_products'])->with('translations')->get() as $section) {
            foreach ($section->translations as $translation) {
                $content = $translation->content_json;
                if ($section->type === 'gallery') {
                    $album = GalleryTranslation::where('locale', $translation->locale)->where('slug', $content['album_slug'])->firstOrFail();
                    $image = GalleryItem::where('gallery_id', $album->gallery_id)->firstOrFail();
                    $this->assertNotEmpty($image->image_url);
                    $this->assertNotEmpty($image->alt_text);
                } else {
                    $this->assertSame('featured', $content['selection']);
                    $this->assertTrue(ProductTranslation::where('locale', $translation->locale)->whereHas('product', fn ($query) => $query->where('is_featured', true)->where('is_active', true))->exists());
                }
            }
        }
        $form = CustomForm::where('slug', 'contacto')->firstOrFail();
        $this->assertSame('Full name', FormFieldTranslation::where('locale', 'en')->whereHas('field', fn ($query) => $query->where('custom_form_id', $form->id)->where('field_key', 'nombre'))->firstOrFail()->label);
    }

    public function test_every_seeded_block_matches_its_editor_schema_and_renders_populated_kpis(): void
    {
        $registry = app(BlockRegistryInterface::class);
        $market = Market::where('code', 'eu')->firstOrFail();
        $localeMap = [];
        foreach (PageSection::with('translations')->get() as $section) {
            foreach ($section->translations as $translation) {
                $this->assertSeededBlock($registry, $section->type, $translation->content_json, $market, $translation->locale);
                $localeMap[$translation->locale][] = ['id' => $section->id, 'type' => $section->type, ...$translation->content_json];
            }
        }
        $types = array_values(array_map(fn ($definition): array => ['type' => $definition->getType(), 'schema' => $definition->getEditorSchema()], $registry->all()));
        $editor = new Process(['node', '--input-type=module', '-e', <<<'JS'
import { createBlockManager } from './resources/js/block-manager.js';
let input = '';
for await (const chunk of process.stdin) input += chunk;
const { sections, types } = JSON.parse(input);
const editor = createBlockManager(sections, 'es', types);
editor.blocks = editor.sectionsByLocale.es;
process.stdout.write(editor.getCleanSerializedSections());
JS
        ], base_path());
        $editor->setInput(json_encode(['sections' => $localeMap, 'types' => $types], JSON_THROW_ON_ERROR));
        $editor->mustRun();
        $this->assertSame($localeMap, json_decode($editor->getOutput(), true, flags: JSON_THROW_ON_ERROR), 'Opening and saving the real block editor must preserve seeded content.');
    }

    /** @param array<string, mixed> $payload */
    private function assertSeededBlock(BlockRegistryInterface $registry, string $type, array $payload, Market $market, string $locale): void
    {
        $definition = $registry->get($type);
        $this->assertNotNull($definition, $type);
        $registry->validate($type, $payload);
        $this->assertSchemaKeys($definition->getEditorSchema(), array_diff_key($payload, array_flip(['columns', 'columns_layout', 'id', 'type'])), $type.':'.$locale);
        $html = $registry->render($type, $payload, $market, $locale);
        if ($type === 'kpi') {
            $this->assertNotEmpty($payload['items']);
            foreach ($payload['items'] as $item) {
                $this->assertNotEmpty($item['value']);
                $this->assertNotEmpty($item['label']);
                $this->assertStringContainsString('>'.e($item['value']).'</div>', $html);
                $this->assertStringContainsString('>'.e($item['label']).'</div>', $html);
            }
        }
        foreach ($payload['columns'] ?? [] as $column) {
            foreach ($column['blocks'] ?? [] as $block) {
                $this->assertSeededBlock($registry, $block['type'], $block, $market, $locale);
            }
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $schema
     * @param  array<string, mixed>  $payload
     */
    private function assertSchemaKeys(array $schema, array $payload, string $path): void
    {
        $this->assertSame([], array_values(array_diff(array_keys($payload), array_keys($schema))), $path.' contains fields absent from its editor schema');
        foreach ($schema as $key => $field) {
            if (($field['type'] ?? '') !== 'repeater' || ! isset($payload[$key])) {
                continue;
            }
            foreach ($payload[$key] as $index => $row) {
                $this->assertIsArray($row, $path.'.'.$key);
                $this->assertFalse(array_is_list($row), $path.'.'.$key.' requires named editor fields');
                $this->assertSchemaKeys($field['fields'], $row, $path.'.'.$key.'.'.$index);
            }
        }
    }
}
