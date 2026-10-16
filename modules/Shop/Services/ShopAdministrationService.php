<?php

declare(strict_types=1);

namespace Modules\Shop\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Modules\Shop\Contracts\ShopAdministrationServiceInterface;
use Modules\Shop\Models\Inquiry;

final class ShopAdministrationService implements ShopAdministrationServiceInterface
{
    public function inquiries(): LengthAwarePaginator
    {
        return Inquiry::query()->latest()->paginate(25);
    }

    public function review(Inquiry $inquiry): void
    {
        DB::transaction(static fn (): bool => $inquiry->update(['is_reviewed' => true]));
    }
}
