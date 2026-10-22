<?php

declare(strict_types=1);

namespace Modules\Shop\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Modules\Shop\Contracts\ShopEditorialServiceInterface;
use Modules\Shop\Models\ProductCategory;

final class ShopCategoryDeleteController extends Controller
{
    public function __construct(private readonly ShopEditorialServiceInterface $service) {}

    public function __invoke(ProductCategory $category): RedirectResponse
    {
        $this->service->delete('shop_category', $category);

        return redirect()->route('admin.shop.categories.index')->with('success', 'Categoría guardado correctamente.');
    }
}
