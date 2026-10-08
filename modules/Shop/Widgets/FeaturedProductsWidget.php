<?php

declare(strict_types=1);

namespace Modules\Shop\Widgets;

use App\Core\Widgets\WidgetComponentInterface;
use App\Models\Market;
use App\Services\Cms\FrontViewResolver;
use Illuminate\Contracts\View\View;
use Modules\Shop\Models\Product;
use Modules\Shop\Services\ShopDomainService;

class FeaturedProductsWidget implements WidgetComponentInterface
{
    public static function getKey(): string
    {
        return 'shop.featured_products';
    }

    public static function getTitle(): string
    {
        return 'Productos Destacados de Tienda';
    }

    public static function getOptionsSchema(): array
    {
        return [
            'limit' => ['type' => 'integer', 'default' => 4, 'label' => 'Cantidad de productos'],
        ];
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function render(array $parameters = []): View|string
    {
        /** @var Market $market */
        $market = app('current.market');
        $locale = app()->getLocale();
        $limit = (int) ($parameters['limit'] ?? 4);

        $products = Product::active()
            ->featured()
            ->forMarket($market)
            ->with(['translations', 'category.translations', 'markets'])
            ->limit($limit)
            ->get();

        if ($products->isEmpty()) {
            $products = Product::active()
                ->forMarket($market)
                ->with(['translations', 'category.translations', 'markets'])
                ->limit($limit)
                ->get();
        }

        $shopService = app(ShopDomainService::class);
        $isShop = $shopService->isShopEnabled();

        $viewName = app(FrontViewResolver::class)->widget('shop', 'featured_products', 'shop::widgets.featured_products');

        $title = isset($parameters['title']) && ! empty($parameters['title']) ? (string) $parameters['title'] : null;
        $subtitle = isset($parameters['subtitle']) && ! empty($parameters['subtitle']) ? (string) $parameters['subtitle'] : null;

        return view($viewName, [
            'products' => $products,
            'currentMarket' => $market,
            'currentLocale' => $locale,
            'isShop' => $isShop,
            'showPrices' => $shopService->shouldShowPrices(),
            'title' => $title,
            'subtitle' => $subtitle,
        ]);
    }
}
