<?php

declare(strict_types=1);

namespace Modules\Blog\Contracts;

use App\DTOs\Front\FrontPageData;
use App\DTOs\Front\PublicContentContext;

interface BlogArticleReaderInterface
{
    public function show(PublicContentContext $context): FrontPageData;
}
