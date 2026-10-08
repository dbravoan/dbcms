<?php

declare(strict_types=1);

namespace Modules\Shop\Http\Controllers\Front;

use App\Http\Adapters\FrontPageResponseMapper;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Modules\Shop\Contracts\ShopPurchaseWorkflowInterface;

final class CheckoutGetController extends Controller
{
    public function __construct(private readonly ShopPurchaseWorkflowInterface $shop, private readonly FrontPageResponseMapper $responses) {}

    public function __invoke(): View|RedirectResponse
    {
        return $this->responses->respond($this->shop->checkout(app('current.market'), app()->getLocale()));
    }
}
