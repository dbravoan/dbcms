<?php

declare(strict_types=1);

namespace Modules\Shop\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Shop\Models\Inquiry;

interface ShopAdministrationServiceInterface
{
    /** @return LengthAwarePaginator<int, Inquiry> */
    public function inquiries(): LengthAwarePaginator;

    public function review(Inquiry $inquiry): void;
}
