<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\PlatformShowcaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Modules\Shop\Models\Order;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(PlatformShowcaseSeeder::class);
    $this->admin = User::where('email', 'admin@db-cms.io')->firstOrFail();

    // Ensure at least 1 order exists for shop orders datatable
    Order::create([
        'order_number' => 'ORD-RESP-001',
        'customer_name' => 'Demo Customer',
        'customer_email' => 'customer@example.com',
        'currency' => 'EUR',
        'status' => 'completed',
        'payment_status' => 'paid',
        'subtotal' => 100.0,
        'tax_total' => 21.0,
        'shipping_total' => 0.0,
        'discount_total' => 0.0,
        'grand_total' => 121.0,
    ]);

    // Ensure at least 1 token exists for API tokens datatable
    app(ClientRepository::class)->createPersonalAccessGrantClient('Testing Client');
    $this->admin->createToken('Demo Responsive Test Token');
});

test('all 12 backoffice datatable views load successfully and contain responsive accordion and action button markers', function (string $url): void {
    $response = $this->actingAs($this->admin, 'web')->get($url);
    $response->assertOk();

    // Verify presence of responsive drawer toggle and accordion (stable marker,
    // decoupled from the Alpine expression).
    $response->assertSee('data-row-detail', false);
    $response->assertSee('fa-chevron-down');
    $response->assertSee('md:hidden');
})->with([
    'Menus' => '/admin/menus',
    'Pages' => '/admin/pages',
    'Posts' => '/admin/blog/posts',
    'Post Categories' => '/admin/blog/categories',
    'Products' => '/admin/shop/products',
    'Shop Categories' => '/admin/shop/categories',
    'Orders' => '/admin/shop/orders',
    'Users' => '/admin/users',
    'Markets' => '/admin/markets',
    'Forms' => '/admin/forms',
    'Galleries' => '/admin/galleries',
    'Tokens' => '/admin/tokens',
]);
