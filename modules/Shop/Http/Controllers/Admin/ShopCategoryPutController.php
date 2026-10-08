<?php

declare(strict_types=1);

namespace Modules\Shop\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Modules\Shop\Contracts\ShopEditorialServiceInterface;
use Modules\Shop\Http\Requests\ShopEntityRequest;
use Modules\Shop\Models\ProductCategory;

final class ShopCategoryPutController extends Controller
{
    public function __construct(private readonly ShopEditorialServiceInterface $service) {}

    public function __invoke(ShopEntityRequest $request, ProductCategory $category): RedirectResponse
    {
        $this->service->update('shop_category', $category, $request->validated());

        return redirect()->route('admin.shop.categories.index')->with('success', 'Categoría guardado correctamente.');
    }
}
