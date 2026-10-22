<?php

declare(strict_types=1);

namespace Modules\Shop\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Shop\Models\Order;

final class OrderStatusPutController extends Controller
{
    public function __invoke(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,processing,completed,cancelled',
            'payment_status' => 'required|in:unpaid,paid,refunded',
            'notes' => 'nullable|string',
        ]);

        $order->update($validated);

        return redirect()->route('admin.shop.orders.show', $order)->with('success', 'Estado del pedido actualizado.');
    }
}
