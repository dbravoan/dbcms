<?php

declare(strict_types=1);

namespace Modules\Shop\Http\Controllers\Admin;

use App\Core\Traits\HandlesDatatableQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Shop\Models\Product;
use Modules\Shop\Models\ProductCategory;
use Modules\Shop\Services\ShopDomainService;

final class ProductsGetController extends Controller
{
    use HandlesDatatableQuery;

    public function __construct(
        private readonly ShopDomainService $shopService
    ) {}

    public function __invoke(Request $request): View
    {
        $query = Product::with(['translations', 'category.translations', 'markets']);

        $products = $this->applyDatatable(
            $query,
            $request,
            ['sku', 'translations.name', 'translations.slug'],
            [
                'category_id' => 'category_id',
                'status' => 'is_active',
                'is_active' => 'is_active',
                'market_id' => 'markets.id',
            ],
            'id',
            'desc'
        );

        $categories = ProductCategory::with('translations')->orderBy('order', 'asc')->get();

        return view('admin.shop.products.index', [
            'products' => $products,
            'categories' => $categories,
            'isShop' => $this->shopService->isShopEnabled(),
        ]);
    }
}
