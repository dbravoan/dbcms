<?php

declare(strict_types=1);

use App\Models\Market;
use App\Models\User;
use Database\Seeders\PlatformShowcaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Shop\Models\Order;
use Modules\Shop\Models\Product;
use Modules\Shop\Models\ProductCategory;
use Modules\Shop\Models\ShopSetting;
use Modules\Shop\Services\CartService;
use Modules\Shop\Services\ShopDomainService;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Content is mutated and asserted immediately in these tests.
    config(['cms.page_cache.enabled' => false]);
    $this->seed(PlatformShowcaseSeeder::class);
});

test('shop index renders products in Spanish and English with multi-market pricing', function (): void {
    // 1. Spanish Market (EUR)
    $responseEs = $this->get('/es/es/tienda');
    $responseEs->assertOk();
    $responseEs->assertSee('Servidor Edge Cyber-KV 10G');
    $responseEs->assertSee('1.299,00 €');

    // 2. US Market (USD)
    $responseUs = $this->get('/us/en/shop');
    $responseUs->assertOk();
    $responseUs->assertSee('Cyber-KV 10G Edge Server');
    $responseUs->assertSee('$1,399.00');
});

test('product detail renders photo gallery, technical specs, and Schema.org JSON-LD', function (): void {
    $response = $this->get('/es/es/tienda/servidor-edge-cyber-kv-10g');
    $response->assertOk();
    $response->assertSee('PRD-EDGE-1000');
    $response->assertSee('Servidor Edge Cyber-KV 10G');
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

test('full shop checkout flow creates order, decrements stock, and redirects to confirmation', function (): void {
    $product = Product::where('sku', 'PRD-EDGE-1000')->first();
    $initialStock = $product->stock_quantity;

    // Add item to cart
    $this->postJson('/api/cart/add', [
        'product_id' => $product->id,
        'quantity' => 2,
    ])->assertOk();

    // Process checkout
    $response = $this->post('/es/es/checkout/process', [
        'customer_name' => 'John Doe',
        'customer_email' => 'john.doe@enterprise.com',
        'customer_phone' => '+34 600 111 222',
        'address' => 'Paseo de la Castellana 100',
        'city' => 'Madrid',
        'postal_code' => '28046',
        'country' => 'España',
        'payment_method' => 'bank_transfer',
        'notes' => 'Entrega urgente en horario de oficina.',
    ]);
    $response->assertSessionHasNoErrors();

    $order = Order::where('customer_email', 'john.doe@enterprise.com')->first();
    expect($order)->not->toBeNull();
    expect($order->items()->count())->toBe(1);
    expect($order->payment_status)->toBe('unpaid');

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('signature=', $order->order_number);

    // Verify stock decrement
    $product->refresh();
    expect($product->stock_quantity)->toBe($initialStock - 2);

    // Verify Order Success Page
    $successPage = $this->get("/es/es/order/{$order->order_number}");
    $successPage->assertOk();
    $successPage->assertSee($order->order_number);
    $successPage->assertSee('¡Pedido Confirmado con Éxito!');
});

test('catalog-only mode disables cart and allows requesting custom quotes', function (): void {
    // Switch to Catalog Only Mode
    ShopSetting::set('shop.mode', 'catalog_only');
    $shopService = app(ShopDomainService::class);
    expect($shopService->isCatalogOnly())->toBeTrue();

    $product = Product::where('sku', 'PRD-EDGE-1000')->first();

    // Product detail in catalog mode shows Request Quote button
    $response = $this->get('/es/es/tienda/servidor-edge-cyber-kv-10g');
    $response->assertOk();
    $response->assertSee('Solicitar Presupuesto');
    $response->assertDontSee('Añadir al Carrito');

    // Submit inquiry
    $inquiryRes = $this->post('/es/es/tienda/inquiry', [
        'product_id' => $product->id,
        'name' => 'Ingeniería Cloud SL',
        'email' => 'infra@cloud.es',
        'phone' => '+34 910 000 000',
        'message' => 'Requerimos presupuesto para 50 unidades de servidores edge.',
    ]);

    $inquiryRes->assertSessionHas('success');
});

test('admin can create and manage products with modular sections in backoffice', function (): void {
    $admin = User::where('email', 'admin@db-cms.io')->first();
    $market = Market::where('code', 'es')->first();
    $category = ProductCategory::first();

    $sections = [
        [
            'type' => 'features',
            'title' => 'Capacidades de Aceleración',
            'items' => [
                ['icon' => '⚡', 'title' => 'PCIe Gen 5', 'desc' => '128 GB/s bandwidth'],
            ],
            'animation' => 'fadeInUp',
        ],
        [
            'type' => 'faq',
            'title' => 'Dudas de Integración',
            'items' => [
                ['question' => '¿Es compatible con Kubernetes?', 'answer' => 'Sí, incluye driver CSI.'],
            ],
            'animation' => 'fadeInUp',
        ],
    ];

    $response = $this->actingAs($admin)
        ->post(route('admin.shop.products.store'), [
            'sku' => 'PRD-CLUSTER-AI',
            'translations' => ['es' => [
                'name' => 'Superclúster IA Perimetral',
                'slug' => 'supercluster-ia-perimetral',
                'summary' => 'Clúster de alta densidad.',
                'description' => 'Descripción detallada en markdown.',
            ]],
            'price' => 4999.00,
            'compare_price' => 5499.00,
            'stock_quantity' => 5,
            'track_stock' => true,
            'category_id' => $category->id,
            'market_ids' => [$market->id],
            'is_featured' => true,
            'is_active' => true,
            'sections_json' => json_encode($sections),
        ]);

    $response->assertRedirect(route('admin.shop.products.index'));

    $newProd = Product::where('sku', 'PRD-CLUSTER-AI')->first();
    expect($newProd)->not->toBeNull();
    expect($newProd->sections()->count())->toBe(2);

    // Check frontoffice rendering with modular sections
    $frontRes = $this->get('/es/es/tienda/supercluster-ia-perimetral');
    $frontRes->assertOk();
    $frontRes->assertSee('Capacidades de Aceleración');
    $frontRes->assertSee('¿Es compatible con Kubernetes?');
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
    expect(ShopSetting::get('shop.mode'))->toBe('catalog_only');
    expect((float) ShopSetting::get('shop.tax_rate'))->toBe(18.0);

    // 2. Orders list
    $ordersRes = $this->actingAs($admin)->get(route('admin.shop.orders.index'));
    $ordersRes->assertOk();
});
