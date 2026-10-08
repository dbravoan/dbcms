<?php

declare(strict_types=1);

namespace Modules\Shop\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/** Market assignment with observable price and availability changes. */
final class ProductMarket extends Pivot
{
    public $incrementing = true;

    protected $table = 'market_product';

    protected function casts(): array
    {
        return ['custom_price' => 'float', 'is_active' => 'boolean'];
    }
}
