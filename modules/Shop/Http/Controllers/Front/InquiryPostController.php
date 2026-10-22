<?php

declare(strict_types=1);

namespace Modules\Shop\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Modules\Shop\Contracts\ShopPurchaseWorkflowInterface;
use Modules\Shop\DTOs\InquirySubmission;
use Modules\Shop\Http\Requests\InquiryRequest;

final class InquiryPostController extends Controller
{
    public function __construct(private readonly ShopPurchaseWorkflowInterface $shop) {}

    public function __invoke(InquiryRequest $request): RedirectResponse
    {
        $name = $this->shop->inquiry(InquirySubmission::fromArray($request->validated()), app('current.market'), app()->getLocale());

        return back()->with('success', 'Gracias por contactarnos. Hemos recibido tu solicitud de presupuesto para "'.$name.'".');
    }
}
