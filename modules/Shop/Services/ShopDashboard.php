<?php

declare(strict_types=1);

namespace Modules\Shop\Services;

use App\Models\User;
use Modules\Shop\Models\Product;

final class ShopDashboard
{
    /** @return array{views: array<string, string>, data: array<string, mixed>}|null */
    public function data(User $user): ?array
    {
        if (! $user->isAdmin() && ! $user->hasPermission('manage:shop')) {
            return null;
        }

        return ['views' => ['metrics' => 'shop::admin.dashboard.metrics'], 'data' => ['stats' => ['total_products' => Product::count()]]];
    }
}
