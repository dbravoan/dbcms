<?php

declare(strict_types=1);

namespace Modules\Shop\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\Market;
use Illuminate\View\View;
use Modules\Shop\Models\ProductCategory;
use Modules\Shop\Services\ShopDomainService;

final class ProductCreateGetController extends Controller
{
    public function __construct(
        private readonly ShopDomainService $shopService
    ) {}

    public function __invoke(): View
    {
        $categories = ProductCategory::with('translations')->get();
        $markets = Market::with('languages')->where('is_active', true)->get();
        $languages = Language::where('is_active', true)->get();

        return view('admin.shop.products.create', [
            'categories' => $categories,
            'markets' => $markets,
            'languages' => $languages,
            'isShop' => $this->shopService->isShopEnabled(),
        ]);
    }
}
