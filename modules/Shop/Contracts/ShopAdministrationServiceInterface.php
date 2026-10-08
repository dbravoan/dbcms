<?php

declare(strict_types=1);

namespace Modules\Shop\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Shop\Models\Inquiry;
use Modules\Shop\Models\Order;

interface ShopAdministrationServiceInterface
{
    /** @return LengthAwarePaginator<int, Inquiry> */
    public function inquiries(): LengthAwarePaginator;

    public function review(Inquiry $inquiry): void;

    /** @return LengthAwarePaginator<int, Order> */
    public function legacyPayments(): LengthAwarePaginator;
}
