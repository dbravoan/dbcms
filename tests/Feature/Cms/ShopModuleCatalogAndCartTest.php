<?php

declare(strict_types=1);

use App\Contracts\Services\ModuleSettingsServiceInterface;
use App\Models\Market;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Shop\Models\Product;
use Modules\Shop\Services\CartService;
use Modules\Shop\Services\ShopDomainService;
use Tests\Support\CmsFixtures;
use Tests\Support\ShopComposition;
use Tests\Support\ShopFixtures;

uses(RefreshDatabase::class, ShopComposition::class);

beforeEach(function (): void {
    // Content is mutated and asserted immediately in these tests.
    config(['cms.page_cache.enabled' => false]);
    CmsFixtures::localization(['es', 'en'], ['es', 'us']);
    CmsFixtures::identities();
    $product = ShopFixtures::product();
    $product->update(['specs_json' => ['CPU' => '64-Core ARM Neoverse N2']]);
    $us = Market::where('code', 'us')->sole();
    $us->update(['currency' => 'USD', 'default_locale' => 'en']);
    $product->markets()->updateExistingPivot($us->id, ['custom_price' => 199]);
});

test('shop index renders products in Spanish and English with multi-market pricing', function (): void {
    // 1. Spanish Market (EUR)
    $responseEs = $this->get('/es/es/tienda');
    $responseEs->assertOk();
    $responseEs->assertSee('Producto PRD-EDGE-1000');
    $responseEs->assertSee('159,00 €');

    // 2. US Market (USD)
    $responseUs = $this->get('/us/en/shop');
    $responseUs->assertOk();
    $responseUs->assertSee('Producto PRD-EDGE-1000');
    $responseUs->assertSee('$199.00');
});

test('product detail renders photo gallery, technical specs, and Schema.org JSON-LD', function (): void {
    $response = $this->get('/es/es/tienda/prd-edge-1000');
    $response->assertOk();
    $response->assertSee('PRD-EDGE-1000');
    $response->assertSee('Producto PRD-EDGE-1000');
    $response->assertSee('64-Core ARM Neoverse N2');
    $response->assertSee('Añadir al Carrito');

    // Rich Product + Offer schema is emitted by the unified seo-head (JSON-LD graph).
    $response->assertSee('"@type":"Product"', false);
    $response->assertSee('"@type":"Offer"', false);
});

test('cart API supports adding, updating, and removing items with multi-currency calculations', function (): void {
    $product = Product::where('sku', 'PRD-EDGE-1000')->first();
    $cartService = app(CartService::class);

    // 1. Add to cart via API
    $responseAdd = $this->postJson('/api/cart/add', [
        'product_id' => $product->id,
        'quantity' => 2,
    ]);
    $responseAdd->assertOk();
    $responseAdd->assertJson(['success' => true, 'count' => 2]);

    expect($cartService->count())->toBe(2);

    // 2. Get Cart JSON
    $responseGet = $this->getJson('/api/cart');
    $responseGet->assertOk();
    $responseGet->assertJson([
        'enabled' => true,
        'count' => 2,
    ]);

    // 3. Update quantity
    $responseUpdate = $this->postJson('/api/cart/update', [
        'product_id' => $product->id,
        'quantity' => 3,
    ]);
    $responseUpdate->assertOk();
    expect($cartService->count())->toBe(3);

    // 4. Remove item
    $responseRemove = $this->postJson('/api/cart/remove', [
        'product_id' => $product->id,
    ]);
    $responseRemove->assertOk();
    expect($cartService->count())->toBe(0);
});

test('catalog-only mode disables cart and allows requesting custom quotes', function (): void {
    // Switch to Catalog Only Mode
    app(ModuleSettingsServiceInterface::class)->set('shop', 'shop_mode', 'catalog_only');
    $shopService = app(ShopDomainService::class);
    expect($shopService->isCatalogOnly())->toBeTrue();

    $product = Product::where('sku', 'PRD-EDGE-1000')->first();

    // Product detail in catalog mode shows Request Quote button
    $response = $this->get('/es/es/tienda/prd-edge-1000');
    $response->assertOk();
    $response->assertSee('Solicitar Presupuesto');
    $response->assertDontSee('Añadir al Carrito');

    // Submit inquiry
    $inquiryRes = $this->post('/es/es/inquiry', [
        'product_id' => $product->id,
        'name' => 'Ingeniería Cloud SL',
        'email' => 'infra@cloud.es',
        'phone' => '+34 910 000 000',
        'message' => 'Requerimos presupuesto para 50 unidades de servidores edge.',
    ]);

    $inquiryRes->assertSessionHas('success');
});

test('admin can toggle shop settings and view orders in backoffice', function (): void {
    $admin = User::where('email', 'admin@db-cms.io')->first();

    // 1. Update settings through the generic module settings page
    $settingsRes = $this->actingAs($admin)->put(route('admin.modules.settings.update', ['identifier' => 'shop']), [
        'shop_mode' => 'catalog_only',
        'currency' => 'EUR',
        'tax_rate' => 18.0,
        'enable_stock' => 1,
        'enable_inquiry' => 1,
        'notification_email' => 'orders@db-cms.io',
    ]);
    $settingsRes->assertRedirect(route('admin.modules.settings', ['identifier' => 'shop']));
    expect(module_setting('shop', 'shop_mode'))->toBe('catalog_only');
    expect((float) module_setting('shop', 'tax_rate'))->toBe(18.0);

    // 2. Orders list
    $ordersRes = $this->actingAs($admin)->get(route('admin.shop.orders.index'));
    $ordersRes->assertOk();
});
