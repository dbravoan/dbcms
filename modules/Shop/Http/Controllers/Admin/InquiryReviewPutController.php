<?php

declare(strict_types=1);

namespace Modules\Shop\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Modules\Shop\Contracts\ShopAdministrationServiceInterface;
use Modules\Shop\Models\Inquiry;

final class InquiryReviewPutController
{
    public function __construct(private readonly ShopAdministrationServiceInterface $shop) {}

    public function __invoke(Inquiry $inquiry): RedirectResponse
    {
        $this->shop->review($inquiry);

        return back()->with('success', __('Solicitud revisada.'));
    }
}
