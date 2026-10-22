<?php

declare(strict_types=1);

namespace Modules\Shop\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Shop\Contracts\CartApplicationInterface;
use Modules\Shop\Services\ShopDomainService;

final class CartAddPostController extends Controller
{
    public function __construct(private readonly CartApplicationInterface $cart, private readonly ShopDomainService $shop) {}

    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless($this->shop->isShopEnabled(), 404);
        $data = $request->validate(['product_id' => 'required|integer|exists:products,id', 'quantity' => 'required|integer|min:1']);
        $market = app('current.market');
        $result = $this->cart->add((int) $data['product_id'], (int) $data['quantity'], $market);
        if (! $result->accepted) {
            return back()->withErrors(['cart' => $result->data['message']])->withInput();
        }

        return redirect()->route('shop.cart', ['market' => $market->code, 'locale' => app()->getLocale()]);
    }
}
