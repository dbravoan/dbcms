<?php

declare(strict_types=1);

namespace Modules\Shop\Http\Controllers\Admin;

use Illuminate\View\View;
use Modules\Shop\Contracts\ShopAdministrationServiceInterface;

final class LegacyPaymentReportGetController
{
    public function __construct(private readonly ShopAdministrationServiceInterface $shop) {}

    public function __invoke(): View
    {
        return view('admin.shop.orders.legacy-payments', ['orders' => $this->shop->legacyPayments()]);
    }
}
