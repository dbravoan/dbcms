<?php

declare(strict_types=1);

namespace Modules\Shop\Http\Controllers\Admin;

use App\Core\Traits\HandlesDatatableQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Shop\Models\Order;

final class OrdersGetController extends Controller
{
    use HandlesDatatableQuery;

    public function __invoke(Request $request): View
    {
        $query = Order::with(['items', 'market']);

        $orders = $this->applyDatatable(
            $query,
            $request,
            ['order_number', 'customer_name', 'customer_email'],
            [
                'status' => 'status',
                'payment_status' => 'payment_status',
            ],
            'id',
            'desc'
        );

        return view('admin.shop.orders.index', [
            'orders' => $orders,
        ]);
    }
}
