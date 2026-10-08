<?php

declare(strict_types=1);

use App\Models\Market;
use Database\Seeders\PlatformShowcaseSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Modules\Shop\Models\Inquiry;
use Modules\Shop\Models\Order;
use Modules\Shop\Models\Product;
use Modules\Shop\Services\CartService;
use Modules\Shop\Services\ShopDomainService;

beforeEach(function (): void {
    $this->seed(PlatformShowcaseSeeder::class);
    config(['cms.page_cache.enabled' => true]);
    Cache::flush();
});

it('rejects accumulated and updated quantities above stock', function (): void {
    $product = Product::where('sku', 'PRD-EDGE-1000')->firstOrFail();
    $product->update(['stock_quantity' => 1, 'track_stock' => true]);
    $this->postJson('/api/cart/add', ['product_id' => $product->id, 'quantity' => 1])->assertOk();
    $this->postJson('/api/cart/add', ['product_id' => $product->id, 'quantity' => 1])->assertStatus(422);
    $this->postJson('/api/cart/update', ['product_id' => $product->id, 'quantity' => 2])->assertStatus(422);
});

it('rechecks stock at checkout and creates no partial order', function (): void {
    $product = Product::where('sku', 'PRD-EDGE-1000')->firstOrFail();
    $market = Market::where('code', 'es')->firstOrFail();
    app(CartService::class)->add($product->id);
    $items = app(CartService::class)->getItems($market);
    $product->update(['stock_quantity' => 0, 'track_stock' => true]);
    $before = Order::count();
    expect(fn () => app(ShopDomainService::class)->createOrder(['customer_name' => 'Buyer', 'customer_email' => 'buyer@example.test'], $items, $market, 'bank_transfer'))
        ->toThrow(ValidationException::class);
    expect(Order::count())->toBe($before)->and($product->fresh()->stock_quantity)->toBe(0);
});

it('never caches cart html even for anonymous sessions', function (): void {
    $response = $this->get('/es/es/cart');
    $response->assertOk();
    expect($response->headers->get('X-Page-Cache'))->toBeNull();
});

it('does not authorize an order by its commercial reference', function (): void {
    $order = Order::create(['order_number' => 'ORD-SAFETY', 'customer_name' => 'Private', 'customer_email' => 'private@example.test', 'market_id' => Market::where('code', 'es')->value('id'), 'payment_method' => 'bank_transfer']);
    $this->get('/es/es/order/'.$order->order_number)->assertNotFound();
    $url = URL::temporarySignedRoute('shop.order.success', now()->addDay(), ['market' => 'es', 'locale' => 'es', 'orderNumber' => $order->order_number]);
    $this->get($url)->assertOk()->assertSee('private@example.test');
});

it('keeps submitted inquiries even when delivery is unavailable', function (): void {
    $product = Product::where('sku', 'PRD-EDGE-1000')->firstOrFail();
    $this->post('/es/es/inquiry', ['product_id' => $product->id, 'name' => 'Buyer', 'email' => 'buyer@example.test', 'message' => 'Please send a quote.'])->assertRedirect();
    expect(Inquiry::where('email', 'buyer@example.test')->value('message'))->toBe('Please send a quote.');
});

it('does not allow a product outside the selected market into the cart', function (): void {
    $product = Product::where('sku', 'PRD-EDGE-1000')->firstOrFail();
    $product->markets()->sync([Market::where('code', 'es')->value('id') => ['is_active' => true]]);
    $this->withHeader('X-Market', 'us')->postJson('/api/cart/add', ['product_id' => $product->id])->assertNotFound();
});

it('does not expose cost prices or other market pivots in the public cart', function (): void {
    $product = Product::where('sku', 'PRD-EDGE-1000')->firstOrFail();
    $this->postJson('/api/cart/add', ['product_id' => $product->id])->assertOk();
    $response = $this->getJson('/api/cart');
    $response->assertOk()->assertJsonPath('items.0.product.id', $product->id);
    expect($response->json('items.0.product'))->not->toHaveKey('cost_price')->not->toHaveKey('markets');
});

it('aggregates repeated order lines before checking available stock', function (): void {
    $product = Product::where('sku', 'PRD-EDGE-1000')->firstOrFail();
    $product->update(['stock_quantity' => 1, 'track_stock' => true]);
    $market = Market::where('code', 'es')->firstOrFail();
    $line = ['product' => $product, 'quantity' => 1, 'unit_price' => 1.0, 'subtotal' => 1.0];
    expect(fn () => app(ShopDomainService::class)->createOrder(['customer_name' => 'Buyer', 'customer_email' => 'buyer@example.test'], [$line, $line], $market, 'cod'))
        ->toThrow(ValidationException::class);
    expect($product->fresh()->stock_quantity)->toBe(1);
});
