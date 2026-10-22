<?php

declare(strict_types=1);

namespace Modules\Shop\DTOs;

use App\DTOs\Front\FrontPageData;
use Modules\Shop\Models\Order;

final readonly class PurchaseOutcome
{
    public function __construct(public ?Order $order, public ?FrontPageData $redirect) {}
}
