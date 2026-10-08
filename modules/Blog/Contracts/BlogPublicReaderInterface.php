<?php

declare(strict_types=1);

namespace Modules\Blog\Contracts;

use App\DTOs\Front\FrontPageData;
use App\DTOs\Front\PublicContentContext;
use Modules\Blog\Models\Category;

interface BlogPublicReaderInterface
{
    public function index(PublicContentContext $context): FrontPageData;

    public function show(PublicContentContext $context): FrontPageData;

    public function category(PublicContentContext $context, ?Category $category = null): FrontPageData;

    public function resolveSlug(PublicContentContext $context): FrontPageData;
}
