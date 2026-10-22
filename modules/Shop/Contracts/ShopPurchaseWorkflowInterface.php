<?php

declare(strict_types=1);

namespace Modules\Shop\Contracts;

use App\DTOs\Front\FrontPageData;
use App\Models\Market;
use Modules\Shop\DTOs\CheckoutCommandData;
use Modules\Shop\DTOs\InquirySubmission;
use Modules\Shop\DTOs\OrderAccessContext;
use Modules\Shop\DTOs\PurchaseOutcome;

interface ShopPurchaseWorkflowInterface
{
    public function cart(Market $market, string $locale): FrontPageData;

    public function checkout(Market $market, string $locale): FrontPageData;

    public function purchase(CheckoutCommandData $command, Market $market): PurchaseOutcome;

    public function order(string $number, OrderAccessContext $actor, Market $market): FrontPageData;

    public function inquiry(InquirySubmission $input, Market $market, string $locale): string;
}
