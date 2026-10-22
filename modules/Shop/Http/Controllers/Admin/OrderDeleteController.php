<?php

declare(strict_types=1);

namespace Modules\Shop\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Modules\Shop\Models\Order;

final class OrderDeleteController extends Controller
{
    public function __invoke(Order $order): RedirectResponse
    {
        $order->items()->delete();
        $order->delete();

        return redirect()->route('admin.shop.orders.index')->with('success', 'Pedido eliminado.');
    }
}
