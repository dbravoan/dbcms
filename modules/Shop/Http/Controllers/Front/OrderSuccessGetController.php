<?php

declare(strict_types=1);

namespace Modules\Shop\Http\Controllers\Front;

use App\Http\Adapters\FrontPageResponseMapper;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Shop\Contracts\ShopPurchaseWorkflowInterface;
use Modules\Shop\DTOs\OrderAccessContext;

final class OrderSuccessGetController extends Controller
{
    public function __construct(private readonly ShopPurchaseWorkflowInterface $shop, private readonly FrontPageResponseMapper $responses) {}

    public function __invoke(Request $request, ?string $orderNumber = null): View
    {
        $routeNumber = $request->route('orderNumber');
        $number = is_string($routeNumber) ? $routeNumber : (string) $orderNumber;
        $actor = new OrderAccessContext($request->user() === null ? null : (int) $request->user()->getAuthIdentifier(), array_values(array_filter((array) $request->session()->get('shop_owned_orders', []), is_string(...))), $request->hasValidSignature());

        return $this->responses->view($this->shop->order($number, $actor, app('current.market')));
    }
}
