<?php

declare(strict_types=1);

namespace Modules\Shop\Contracts;

use App\Models\Market;
use Modules\Shop\DTOs\InquirySubmission;
use Modules\Shop\Models\Inquiry;

interface InquiryServiceInterface
{
    public function submit(InquirySubmission $data, Market $market, string $locale): Inquiry;
}
