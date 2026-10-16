<?php

declare(strict_types=1);

namespace Modules\Shop\Widgets;

use App\Core\Widgets\BaseWidgetComponent;
use App\Models\Market;
use Modules\Shop\Models\Product;
use Modules\Shop\Services\ShopDomainService;

class FeaturedProductsWidget extends BaseWidgetComponent
{
    public function getRequiredModule(): ?string
    {
        return 'shop';
    }

    public function getTranslationPaths(): array
    {
        return ['title', 'subtitle'];
    }

    public function getType(): string
    {
        return 'shop.featured_products';
    }

    public function getName(): string
    {
        return 'Productos Destacados de Tienda';
    }

    public function getEditorSchema(): array
    {
        return [
            'limit' => ['type' => 'number', 'default' => 4, 'label' => 'Cantidad de productos'],
        ];
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function render(array $parameters = [], ?Market $market = null, ?string $locale = null): string
    {
        /** @var Market $market */
        $market ??= app('current.market');
        $locale ??= app()->getLocale();
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

        $viewName = $this->widgetView('shop', 'featured_products');

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
        ])->render();
    }
}
