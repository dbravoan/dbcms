<?php

declare(strict_types=1);

use Database\Seeders\PlatformShowcaseSeeder;
use Illuminate\Support\Facades\Cache;
use Modules\Shop\Models\Order;
use Modules\Shop\Models\Product;

beforeEach(function (): void {
    $this->seed(PlatformShowcaseSeeder::class);
    config(['cms.page_cache.enabled' => true]);
    Cache::flush();
});

test('checkout transport preserves manual payment session ownership and a private signed success page', function (): void {
    $product = Product::where('sku', 'PRD-EDGE-1000')->firstOrFail();
    $this->postJson('/api/cart/add', ['product_id' => $product->id, 'quantity' => 1])->assertOk();
    $before = Order::count();
    $response = $this->post('/es/es/checkout/process', [
        'customer_name' => 'Purchase QA', 'customer_email' => 'purchase@example.invalid',
        'address' => 'Calle de pruebas 1', 'city' => 'Madrid', 'postal_code' => '28001',
        'country' => 'España', 'payment_method' => 'bank_transfer',
    ])->assertRedirect();
    expect(Order::count())->toBe($before + 1);
    $order = Order::latest('id')->firstOrFail();
    expect($order->payment_status)->not->toBe('paid');
    expect(session('shop_owned_orders'))->toContain($order->order_number);
    $location = $response->headers->get('Location');
    expect($location)->toContain('signature=')->toContain('expires=');
    $success = $this->get($location)->assertOk()->assertSee('purchase@example.invalid');
    expect($success->headers->get('X-Page-Cache'))->toBeNull();
    $cart = $this->getJson('/api/cart')->assertOk();
    expect($cart->json('items'))->toBe([]);
});

test('checkout rejects the removed card simulator before creating an order', function (): void {
    $before = Order::count();
    $this->postJson('/es/es/checkout/process', [
        'customer_name' => 'Purchase QA', 'customer_email' => 'purchase@example.invalid',
        'address' => 'Calle de pruebas 1', 'city' => 'Madrid', 'postal_code' => '28001',
        'country' => 'España', 'payment_method' => 'card',
    ])->assertUnprocessable()->assertJsonValidationErrors('payment_method');
    expect(Order::count())->toBe($before);
});
