<?php

declare(strict_types=1);

namespace Modules\Blog\Contracts;

use App\Core\Filters\FilterSet;
use App\DTOs\Front\FrontPageData;
use App\DTOs\Front\PublicContentContext;
use Modules\Blog\Models\Category;

interface BlogCatalogueReaderInterface
{
    public function index(PublicContentContext $context): FrontPageData;

    public function filtered(PublicContentContext $context, FilterSet $set): FrontPageData;

    public function category(PublicContentContext $context, ?Category $category = null): FrontPageData;
}
