<?php

declare(strict_types=1);

namespace Modules\Shop\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Modules\Shop\Contracts\ShopEditorialServiceInterface;
use Modules\Shop\Models\Product;

final class ProductDeleteController extends Controller
{
    public function __construct(private readonly ShopEditorialServiceInterface $service) {}

    public function __invoke(Product $product): RedirectResponse
    {
        $this->service->delete('product', $product);

        return redirect()->route('admin.shop.products.index')->with('success', 'Producto guardado correctamente.');
    }
}
