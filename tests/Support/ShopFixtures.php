<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Market;
use Modules\Shop\Models\Product;

final class ShopFixtures
{
    /** @param list<string> $locales */
    public static function product(string $sku = 'PRD-EDGE-1000', array $locales = ['es', 'en']): Product
    {
        $product = Product::create(['sku' => $sku, 'price' => 159, 'stock_quantity' => 20, 'is_active' => true, 'track_stock' => true]);
        foreach ($locales as $locale) {
            $product->translations()->create(['locale' => $locale, 'name' => 'Producto '.$sku, 'slug' => strtolower($sku), 'description' => 'Producto público de prueba', 'meta_description' => 'Producto público de prueba']);
        }
        $product->markets()->sync(Market::pluck('id')->all());

        return $product;
    }
}
