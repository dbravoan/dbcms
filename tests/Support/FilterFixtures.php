<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Market;
use App\Models\User;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\Post;
use Modules\Shop\Models\Product;

final class FilterFixtures
{
    public static function blog(): void
    {
        $market = Market::query()->where('code', 'es')->sole();
        $category = Category::create(['is_active' => true]);
        $category->translations()->create(['locale' => 'es', 'name' => 'Arquitectura', 'slug' => 'arquitectura']);
        $category->markets()->attach($market);
        // Three is the real minimum-results boundary for indexable filters.
        foreach (range(1, 3) as $number) {
            $post = Post::create(['user_id' => User::query()->where('email', 'admin@db-cms.io')->sole()->id, 'category_id' => $category->id, 'is_published' => true]);
            $post->translations()->create(['locale' => 'es', 'title' => 'Artículo '.$number, 'slug' => 'articulo-'.$number, 'content' => '<p>Contenido del filtro.</p>']);
            $post->markets()->attach($market);
        }
    }

    public static function shop(): void
    {
        $product = Product::create(['sku' => 'FILTER-1', 'price' => 50, 'stock_quantity' => 5, 'is_active' => true]);
        $product->translations()->create(['locale' => 'es', 'name' => 'Producto de filtro', 'slug' => 'producto-filtro']);
        $product->markets()->attach(Market::query()->where('code', 'es')->sole());
    }
}
