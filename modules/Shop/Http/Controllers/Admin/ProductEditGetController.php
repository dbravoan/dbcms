<?php

declare(strict_types=1);

namespace Modules\Shop\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Modules\Shop\Contracts\ShopEditorialServiceInterface;
use Modules\Shop\Models\Product;

final class ProductEditGetController extends Controller
{
    public function __construct(private readonly ShopEditorialServiceInterface $service) {}

    public function __invoke(Product $product): View
    {
        return view('admin.shop.products.edit', $this->service->editProduct($product));
    }
}
