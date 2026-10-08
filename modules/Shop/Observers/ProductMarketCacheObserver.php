<?php

declare(strict_types=1);

namespace Modules\Shop\Observers;

use App\Services\Cms\PageCacheService;
use Illuminate\Support\Facades\DB;
use Modules\Shop\Models\ProductMarket;

final class ProductMarketCacheObserver
{
    public function __construct(private readonly PageCacheService $cache) {}

    public function saved(ProductMarket $assignment): void
    {
        $this->cache->invalidateType('products');
        if (DB::transactionLevel() > 0) {
            DB::afterCommit(fn () => $this->cache->invalidateType('products'));
        }
    }

    public function deleted(ProductMarket $assignment): void
    {
        $this->saved($assignment);
    }
}
