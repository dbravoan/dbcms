<?php

declare(strict_types=1);

use App\Models\Market;
use Illuminate\Support\Facades\DB;
use Modules\Shop\Models\Product;
use Modules\Shop\Services\CartService;
use Tests\Support\CmsFixtures;
use Tests\Support\ShopComposition;
use Tests\Support\ShopFixtures;

uses(ShopComposition::class);

test('cart totals use one product snapshot rather than rereading the catalogue for each total', function (): void {
    CmsFixtures::localization();
    ShopFixtures::product();
    $market = Market::where('code', 'es')->firstOrFail();
    $product = Product::where('sku', 'PRD-EDGE-1000')->firstOrFail();
    $cart = app(CartService::class);
    $cart->clear();
    $cart->add($product->id, 2);
    $taxRate = (float) module_setting('shop', 'tax_rate', 21.0);
    DB::enableQueryLog();
    try {
        $summary = $cart->getSummary($market, 3.5);
        $productReads = collect(DB::getQueryLog())->filter(static fn (array $query): bool => str_contains($query['query'], 'from `products`'));
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }
    expect($productReads)->toHaveCount(1);
    expect($summary->items)->toHaveCount(1);
    expect($summary->subtotal)->toBe($summary->items[0]['unit_price'] * 2);
    expect($summary->taxTotal)->toBe(round($summary->subtotal * $taxRate / 100, 2));
    expect($summary->grandTotal)->toBe($summary->subtotal + $summary->taxTotal + 3.5);
    $cart->clear();
    expect($cart->getSummary($market)->grandTotal)->toBe(0.0);
});
