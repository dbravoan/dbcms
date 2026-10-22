<?php

declare(strict_types=1);

use App\Contracts\Services\ModuleSettingsServiceInterface;
use App\Models\Market;
use Illuminate\Support\Facades\File;
use Modules\Shop\Models\Product;
use Modules\Shop\Models\ProductCategory;
use Tests\Support\CmsFixtures;
use Tests\Support\ShopComposition;

uses(ShopComposition::class);

beforeEach(function (): void {
    $this->withoutVite();
    CmsFixtures::localization(['es', 'en'], ['es']);
    config(['cms.page_cache.enabled' => false]);
});

function publicShopProduct(string $name, string $locale = 'es'): Product
{
    $product = Product::create(['sku' => 'CONTRACT-'.str()->random(8), 'price' => 100, 'is_active' => true, 'track_stock' => false]);
    $product->translations()->create(['locale' => $locale, 'name' => $name, 'slug' => str()->slug($name)]);
    $product->markets()->sync([Market::where('code', 'es')->sole()->id => ['is_active' => true, 'custom_price' => 12.5]]);

    return $product->load('markets');
}

test('catalogue related products and categories never substitute another public locale', function (): void {
    $spanish = publicShopProduct('SOLO CASTELLANO');
    $english = publicShopProduct('ENGLISH PRODUCT', 'en');
    $category = ProductCategory::create(['is_active' => true, 'order' => 0]);
    $category->translations()->create(['locale' => 'es', 'name' => 'CATEGORIA CASTELLANA', 'slug' => 'categoria-castellana']);
    $this->get('/es/en/shop')->assertOk()->assertSee('ENGLISH PRODUCT')->assertDontSee('SOLO CASTELLANO')->assertDontSee('CATEGORIA CASTELLANA');
    $this->get('/es/en/shop/solo-castellano')->assertNotFound();
    $english->update(['category_id' => $category->id]);
    $this->get('/es/en/shop/english-product')->assertOk()->assertDontSee('SOLO CASTELLANO')->assertDontSee('CATEGORIA CASTELLANA');
    $category->translations()->create(['locale' => 'en', 'name' => 'PRIVATE INACTIVE CATEGORY', 'slug' => 'private-category']);
    $category->update(['is_active' => false]);
    $this->get('/es/en/shop/english-product')->assertOk()->assertDontSee('PRIVATE INACTIVE CATEGORY');
});

test('distributed themes preserve public sections market price canonical filters and actual cart lines', function (string $theme): void {
    config(['cms.theme' => $theme]);
    $product = publicShopProduct('PRODUCTO REAL CONTRATO');
    $section = $product->sections()->create(['type' => 'text', 'status' => 'published', 'is_active' => true, 'order' => 0]);
    $section->translations()->create(['locale' => 'es', 'content_json' => ['type' => 'text', 'content' => 'SECCION PUBLICADA CONTRATO']]);
    $market = Market::where('code', 'es')->sole();
    $detail = $this->get('/es/es/tienda/producto-real-contrato')->assertOk()->assertSee('SECCION PUBLICADA CONTRATO')->assertSee($product->getFormattedPrice($market))->assertDontSee('100.00 EUR');
    $this->get('/es/es/tienda')->assertOk()->assertSee($product->getFormattedPrice($market))->assertDontSee('name="q"', false)->assertSee('name="search"', false);
    $this->post('/es/es/cart/add', ['product_id' => $product->id, 'quantity' => 2])->assertRedirect(route('shop.cart', ['market' => 'es', 'locale' => 'es']));
    $this->get('/es/es/cart')->assertOk()->assertSee('PRODUCTO REAL CONTRATO')->assertSee($product->getFormattedPrice($market))->assertDontSee('1 × 0.00');
    $this->get('/es/es/checkout')->assertOk();
})->with(['default-clean', 'db-cyber', 'db-brutalist', 'db-minimal', 'db-sunset', 'db-editorial', 'db-enterprise']);

test('canonical search and category controls lead to the selected product subset', function (): void {
    config(['cms.theme' => 'db-cyber']);
    $alpha = publicShopProduct('ALFA CONTRATO');
    publicShopProduct('BETA CONTRATO');
    $category = ProductCategory::create(['is_active' => true, 'order' => 0]);
    $category->translations()->create(['locale' => 'es', 'name' => 'Categoria A', 'slug' => 'categoria-a']);
    $alpha->update(['category_id' => $category->id]);
    $search = $this->get('/es/es/tienda?search=ALFA')->assertStatus(301);
    $this->get($search->headers->get('Location'))->assertOk()->assertSee('ALFA CONTRATO')->assertDontSee('BETA CONTRATO');
    $selection = $this->get('/es/es/tienda?category=categoria-a')->assertStatus(301);
    $this->get($selection->headers->get('Location'))->assertOk()->assertSee('ALFA CONTRATO')->assertDontSee('BETA CONTRATO');
});

test('native cart form rejects invalid stock and unavailable shop without exposing a JSON document', function (): void {
    $product = publicShopProduct('CARRITO NATIVO');
    $product->update(['track_stock' => true, 'stock_quantity' => 1]);
    $this->from('/es/es/tienda/carrito-nativo')->post('/es/es/cart/add', ['product_id' => $product->id, 'quantity' => 2])->assertRedirect('/es/es/tienda/carrito-nativo')->assertSessionHasErrors('cart');
    app(ModuleSettingsServiceInterface::class)->set('shop', 'shop_mode', 'catalog_only');
    $this->post('/es/es/cart/add', ['product_id' => $product->id, 'quantity' => 1])->assertNotFound();
});

test('a freshly generated theme completes its native cart and checkout journey', function (): void {
    $id = 'journey-shop-'.strtolower(str()->random(8));
    try {
        $this->artisan('make:front-theme', ['id' => $id])->assertSuccessful();
        config(['cms.theme' => $id]);
        $product = publicShopProduct('PRODUCTO TEMA GENERADO');
        $this->get('/es/es/tienda')->assertOk()->assertSee('12,50 €')->assertSee('/es/tienda/producto-tema-generado', false);
        $detail = $this->get('/es/es/tienda/producto-tema-generado')->assertOk()->assertSee('12,50 €')->assertSee('/es/es/cart/add', false);
        $this->post('/es/es/cart/add', ['product_id' => $product->id, 'quantity' => 1])->assertRedirect();
        $this->get('/es/es/cart')->assertOk()->assertSee('PRODUCTO TEMA GENERADO')->assertSee('12,50 €');
        $this->get('/es/es/checkout')->assertOk()->assertSee('name="country"', false)->assertSee('name="payment_method"', false);
        $response = $this->post('/es/es/checkout/process', ['customer_name' => 'Test buyer', 'customer_email' => 'buyer@example.test', 'address' => 'Test street', 'city' => 'Test city', 'postal_code' => '12345', 'country' => 'Spain', 'payment_method' => 'bank_transfer'])->assertRedirect()->assertSessionHasNoErrors();
        $this->get($response->headers->get('Location'))->assertOk()->assertSee('buyer@example.test');
        $this->assertDatabaseHas('orders', ['customer_email' => 'buyer@example.test', 'subtotal' => 12.5]);
    } finally {
        File::deleteDirectory(base_path('themes/'.$id));
    }
});

test('a theme without Shop overrides uses functional owner-provided views', function (): void {
    $id = 'journey-fallback-'.strtolower(str()->random(8));
    try {
        $this->artisan('make:front-theme', ['id' => $id])->assertSuccessful();
        File::deleteDirectory(base_path('themes/'.$id.'/shop'));
        config(['cms.theme' => $id]);
        publicShopProduct('PRODUCTO FALLBACK');
        $this->get('/es/es/tienda')->assertOk()->assertSee('PRODUCTO FALLBACK')->assertSee('name="search"', false)->assertDontSee('name="q"', false);
        $this->get('/es/es/tienda/producto-fallback')->assertOk()->assertSee('12,50 €');
    } finally {
        File::deleteDirectory(base_path('themes/'.$id));
    }
});
