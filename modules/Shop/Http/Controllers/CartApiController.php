<?php

declare(strict_types=1);

namespace Modules\Shop\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Market;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Shop\Contracts\CartApplicationInterface;

class CartApiController extends Controller
{
    public function __construct(private readonly CartApplicationInterface $cart) {}

    public function getCart(): JsonResponse
    {
        return response()->json($this->cart->read($this->market(), app()->getLocale()));
    }

    public function add(Request $request): JsonResponse
    {
        $data = $request->validate(['product_id' => 'required|exists:products,id', 'quantity' => 'nullable|integer|min:1']);
        $result = $this->cart->add((int) $data['product_id'], (int) ($data['quantity'] ?? 1), app('current.market'));

        return response()->json($result->data, $result->accepted ? 200 : 422);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate(['product_id' => 'required|exists:products,id', 'quantity' => 'required|integer|min:0']);

        return response()->json($this->cart->update((int) $data['product_id'], (int) $data['quantity'], app('current.market')));
    }

    public function remove(Request $request): JsonResponse
    {
        $data = $request->validate(['product_id' => 'required|exists:products,id']);

        return response()->json($this->cart->remove((int) $data['product_id']));
    }

    public function clear(): JsonResponse
    {
        return response()->json($this->cart->clear());
    }

    private function market(): ?Market
    {
        return app()->bound('current.market') ? app('current.market') : null;
    }
}
