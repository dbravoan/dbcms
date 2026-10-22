<?php

declare(strict_types=1);

namespace Modules\Shop\Http\Controllers\Admin;

use App\DTOs\Admin\DatatableQueryData;
use App\Http\Controllers\Controller;
use App\Services\Admin\DatatableQueryService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Shop\Models\Order;

final class OrdersGetController extends Controller
{
    public function __invoke(Request $request, DatatableQueryService $tables): View
    {
        $query = Order::with(['items', 'market']);

        $orders = $tables->paginate(
            $query,
            DatatableQueryData::fromInput($request->query()),
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
