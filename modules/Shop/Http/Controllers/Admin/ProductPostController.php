<?php

declare(strict_types=1);

namespace Modules\Shop\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Modules\Shop\Contracts\ShopEditorialServiceInterface;
use Modules\Shop\Http\Requests\ShopEntityRequest;

final class ProductPostController extends Controller
{
    public function __construct(private readonly ShopEditorialServiceInterface $service) {}

    public function __invoke(ShopEntityRequest $request): RedirectResponse
    {
        $this->service->create('product', $request->validated(), $request->user());

        return redirect()->route('admin.shop.products.index')->with('success', 'Producto guardado correctamente.');
    }
}
