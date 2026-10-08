<?php

declare(strict_types=1);

namespace Modules\Shop\Http\Controllers\Front;

use App\Http\Adapters\FrontPageResponseMapper;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\URL;
use Modules\Shop\Contracts\ShopPurchaseWorkflowInterface;
use Modules\Shop\DTOs\CheckoutCommandData;
use Modules\Shop\Http\Requests\CheckoutRequest;

final class CheckoutPostController extends Controller
{
    public function __construct(private readonly ShopPurchaseWorkflowInterface $shop, private readonly FrontPageResponseMapper $responses) {}

    public function __invoke(CheckoutRequest $request): RedirectResponse
    {
        $market = app('current.market');
        $result = $this->shop->purchase(CheckoutCommandData::fromInput($request->validated()), $market);
        if ($result->redirect !== null) {
            $response = $this->responses->respond($result->redirect);
            assert($response instanceof RedirectResponse);

            return $response;
        }
        $order = $result->order;
        assert($order !== null);
        $request->session()->push('shop_owned_orders', $order->order_number);

        return redirect(URL::temporarySignedRoute('shop.order.success', now()->addDay(), ['market' => $market->code, 'locale' => app()->getLocale(), 'orderNumber' => $order->order_number]))->with('success', '¡Pedido realizado con éxito!');
    }
}
