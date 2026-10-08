<?php

declare(strict_types=1);

namespace Modules\Pages\Contracts;

use App\DTOs\Front\FrontPageData;
use App\DTOs\Front\PublicContentContext;

interface PagePublicReaderInterface
{
    public function show(PublicContentContext $context): FrontPageData;
}
