<?php

declare(strict_types=1);

use App\Core\Blocks\BlockEditorCatalogue;
use App\Core\Blocks\Contracts\BlockRegistryInterface;
use App\Core\Content\PublicSemanticRegistry;
use App\Core\Sections\UnavailableBlockGuard;
use App\Models\CmsModule;
use App\Models\Language;
use App\Models\Market;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\Post;
use Modules\Forms\Models\CustomForm;
use Modules\Gallery\Models\Gallery;
use Modules\Shop\Models\Product;
use Modules\Shop\Models\ProductCategory;
use Tests\Support\VisualModulesComposition;

uses(VisualModulesComposition::class);

beforeEach(function (): void {
    $language = Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'native_name' => 'English', 'is_active' => true]);
    $this->market = Market::firstOrCreate(['code' => 'us'], ['name' => 'United States', 'default_locale' => 'en', 'currency' => 'USD', 'is_default' => true, 'is_active' => true]);
    $this->market->languages()->sync([$language->getKey()]);
    $this->blocks = app(BlockRegistryInterface::class);
    $this->semantic = app(PublicSemanticRegistry::class);
    config(['cms.theme' => 'db-cyber']);
    app()->setLocale('es');
});

test('canonical blog selection shares publication ordering and featured filtering with semantics', function (): void {
    $author = User::factory()->create();
    foreach ([['Newest publication', now()->subMinute(), now()->subDays(4), false], ['Featured publication', now()->subDay(), now(), true]] as [$title, $published, $created, $featured]) {
        $post = Post::create(['user_id' => $author->getKey(), 'is_published' => true, 'published_at' => $published, 'is_featured' => $featured]);
        $post->forceFill(['created_at' => $created])->save();
        $post->translations()->create(['locale' => 'en', 'title' => $title, 'slug' => str($title)->slug()->toString(), 'content' => 'Public content']);
    }
    foreach (['latest' => 'Newest publication', 'featured' => 'Featured publication'] as $selection => $expected) {
        $payload = ['type' => 'widget_posts', 'selection' => $selection, 'limit' => 1];
        $html = $this->blocks->render('widget_posts', $payload, $this->market, 'en');
        $text = $this->semantic->widgetText($payload, $this->market, 'en');
        expect($html)->toContain($expected)->and($text)->toContain($expected);
        $other = $selection === 'latest' ? 'Featured publication' : 'Newest publication';
        expect($html)->not->toContain($other)->and($text)->not->toContain($other);
    }
});

test('canonical shop fallback excludes untranslated featured products and uses market prices in each brand theme', function (string $theme): void {
    config(['cms.theme' => $theme]);
    $hidden = Product::create(['sku' => 'NO-TRANSLATION', 'price' => 99, 'is_featured' => true, 'is_active' => true]);
    $hidden->translations()->create(['locale' => 'es', 'name' => 'Untranslated featured', 'slug' => 'untranslated']);
    $public = Product::create(['sku' => 'PUBLIC', 'price' => 100, 'is_active' => true]);
    $public->translations()->create(['locale' => 'en', 'name' => 'Market product', 'slug' => 'market-product']);
    $public->markets()->sync([$this->market->getKey() => ['is_active' => true, 'custom_price' => 12.5]]);
    $payload = ['type' => 'widget_products', 'selection' => 'featured', 'title' => 'Chosen products', 'subtitle' => 'Context subtitle'];
    $html = $this->blocks->render('widget_products', $payload, $this->market, 'en');
    $text = $this->semantic->widgetText($payload, $this->market, 'en');
    expect($html)->toContain('Market product', '$12.50', 'Chosen products', 'Context subtitle')->not->toContain('Untranslated featured', '100,00');
    expect($text)->toContain('Market product', '$12.50')->not->toContain('Untranslated featured');
    $public->update(['is_featured' => true]);
    $other = Product::create(['sku' => 'OTHER', 'price' => 10, 'is_active' => true]);
    $other->translations()->create(['locale' => 'en', 'name' => 'Nonfeatured alternative', 'slug' => 'alternative']);
    expect($this->blocks->render('widget_products', $payload, $this->market, 'en'))->not->toContain('Nonfeatured alternative');
    expect($this->semantic->widgetText($payload, $this->market, 'en'))->not->toContain('Nonfeatured alternative');
})->with(['db-cyber', 'db-minimal']);

test('canonical gallery keeps selected images and limits identical in HTML and semantics', function (): void {
    foreach (['default', 'exhibition'] as $slug) {
        $gallery = Gallery::create(['title' => $slug, 'slug' => $slug, 'is_active' => true]);
        $gallery->translations()->create(['locale' => 'en', 'title' => $slug, 'slug' => $slug]);
        foreach ([1, 2] as $order) {
            $gallery->items()->create(['title' => $slug.' image '.$order, 'image_url' => 'https://example.test/'.$slug.$order.'.jpg', 'order' => $order]);
        }
    }
    $payload = ['type' => 'gallery', 'album_slug' => 'exhibition', 'limit' => 1];
    foreach ([$this->blocks->render('gallery', $payload, $this->market, 'en'), $this->semantic->widgetText($payload, $this->market, 'en')] as $output) {
        expect($output)->toContain('exhibition image 1')->not->toContain('default image', 'exhibition image 2');
    }
    $payload['album_slug'] = 'absent';
    expect($this->semantic->widgetText($payload, $this->market, 'en'))->toBe('');
    expect($this->blocks->render('gallery', $payload, $this->market, 'en'))->not->toContain('default image', 'exhibition image');
});

test('canonical form uses its explicit locale and market for labels and submission URL', function (): void {
    Market::create(['code' => 'es', 'name' => 'Spain', 'default_locale' => 'es', 'currency' => 'EUR', 'is_active' => true]);
    $form = CustomForm::create(['slug' => 'request', 'is_active' => true]);
    $form->translations()->create(['locale' => 'en', 'name' => 'Request information', 'submit_button_text' => 'Send request']);
    $field = $form->fields()->create(['field_key' => 'name', 'type' => 'text', 'is_required' => true, 'order' => 0]);
    $field->translations()->create(['locale' => 'en', 'label' => 'Your name']);
    $html = $this->blocks->render('widget_form', ['form_slug' => 'request'], $this->market, 'en');
    expect($html)->toContain('Request information', 'Send request', 'Your name', '/us/en/forms/request/submit');
});

test('retired variants remain unavailable and stored instances survive sibling changes', function (string $type): void {
    $stored = ['type' => 'text', 'columns' => [['id' => 'column', 'blocks' => [['id' => 'old', 'type' => $type, 'title' => 'Keep'], ['id' => 'current', 'type' => 'text', 'content' => 'Before']]]]];
    $incoming = $stored;
    $incoming['columns'][0]['blocks'][1]['content'] = 'After';
    expect(app(UnavailableBlockGuard::class)->reconcile($incoming, $stored)['columns'][0]['blocks'][0])->toBe($stored['columns'][0]['blocks'][0]);
    expect($this->blocks->render($type, [], $this->market, 'en'))->toBe('');
    expect(fn () => $this->blocks->validate($type, []))->toThrow(ValidationException::class);
})->with(['blog.featured_posts', 'blog.latest_posts_grid', 'shop.featured_products', 'gallery.grid', 'forms.embed_form']);

test('editor schemas do not query data and choices are prepared once per catalogue', function (): void {
    $form = CustomForm::create(['slug' => 'editor-choice', 'is_active' => true]);
    $form->translations()->create(['locale' => 'es', 'name' => 'Editor choice']);
    DB::enableQueryLog();
    DB::flushQueryLog();
    foreach (['widget_posts', 'widget_products', 'gallery', 'widget_form'] as $type) {
        $this->blocks->get($type)->getEditorSchema();
    }
    expect(DB::getQueryLog())->toBe([]);
    $catalogue = app(BlockEditorCatalogue::class)->data();
    $queries = DB::getQueryLog();
    DB::disableQueryLog();
    $definition = collect($catalogue['availableTypesJs'])->firstWhere('type', 'widget_form');
    expect($definition['schema']['form_slug']['options'])->toHaveKey('editor-choice');
    $formSelections = array_filter($queries, static fn (array $query): bool => str_starts_with($query['query'], 'select * from `custom_forms`'));
    expect($formSelections)->toHaveCount(1);
    CmsModule::where('identifier', 'forms')->update(['is_active' => false]);
    expect($this->blocks->getActiveBlocks())->not->toHaveKey('widget_form');
    CmsModule::where('identifier', 'forms')->update(['is_active' => true]);
    expect($this->blocks->getActiveBlocks())->toHaveKey('widget_form');
});

test('editor category choices preserve selected content across translated slugs', function (string $type): void {
    $spanish = Language::firstOrCreate(['code' => 'es'], ['name' => 'Spanish', 'native_name' => 'Español', 'is_active' => true]);
    $this->market->languages()->syncWithoutDetaching([$spanish->getKey()]);
    $category = $type === 'widget_posts' ? Category::create(['is_active' => true]) : ProductCategory::create(['is_active' => true]);
    $category->translations()->createMany([
        ['locale' => 'es', 'name' => 'Categoría elegida', 'slug' => 'categoria-elegida'],
        ['locale' => 'en', 'name' => 'Chosen category', 'slug' => 'chosen-category'],
    ]);
    if ($type === 'widget_posts') {
        $entity = Post::create(['user_id' => User::factory()->create()->getKey(), 'category_id' => $category->getKey(), 'is_published' => true, 'published_at' => now()]);
        $entity->translations()->createMany([
            ['locale' => 'es', 'title' => 'Artículo elegido', 'slug' => 'articulo-elegido', 'content' => 'Contenido'],
            ['locale' => 'en', 'title' => 'Chosen article', 'slug' => 'chosen-article', 'content' => 'Content'],
        ]);
        $expected = ['es' => 'Artículo elegido', 'en' => 'Chosen article'];
    } else {
        $entity = Product::create(['sku' => 'BILINGUAL', 'category_id' => $category->getKey(), 'price' => 10, 'is_active' => true]);
        $entity->translations()->createMany([
            ['locale' => 'es', 'name' => 'Producto elegido', 'slug' => 'producto-elegido'],
            ['locale' => 'en', 'name' => 'Chosen product', 'slug' => 'chosen-product'],
        ]);
        $expected = ['es' => 'Producto elegido', 'en' => 'Chosen product'];
    }
    $catalogue = app(BlockEditorCatalogue::class)->data();
    $definition = collect($catalogue['availableTypesJs'])->firstWhere('type', $type);
    $options = $definition['schema']['category']['options'];
    unset($options['']);
    $selection = array_key_first($options);
    expect($selection)->not->toBeNull();
    $payload = ['type' => $type, 'category' => $selection, 'post_id' => null, 'product_id' => null];
    $this->blocks->validate($type, $payload);
    foreach ($expected as $locale => $title) {
        expect($this->blocks->render($type, $payload, $this->market, $locale))->toContain($title);
        expect($this->semantic->widgetText($payload, $this->market, $locale))->toContain($title);
    }
    expect($selection)->toBe($category->getKey());
})->with(['widget_posts', 'widget_products']);
