<?php

declare(strict_types=1);

namespace Modules\Shop\Contracts;

use App\DTOs\Front\FrontPageData;
use App\DTOs\Front\PublicContentContext;
use Modules\Shop\Models\Product;
use Modules\Shop\Models\ProductCategory;

interface ShopCatalogueServiceInterface
{
    public function index(PublicContentContext $context): FrontPageData;

    public function resolveSlug(PublicContentContext $context): FrontPageData;

    public function category(PublicContentContext $context, ?ProductCategory $category = null): FrontPageData;

    public function show(PublicContentContext $context, ?Product $product = null): FrontPageData;
}
