<?php

declare(strict_types=1);

namespace Modules\Shop\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Modules\Shop\Models\Order;

final class OrderGetController extends Controller
{
    public function __invoke(Order $order): View
    {
        $order->load(['items.product.translations', 'market', 'user']);

        return view('admin.shop.orders.show', [
            'order' => $order,
        ]);
    }
}
