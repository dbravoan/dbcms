<?php

declare(strict_types=1);

namespace Modules\Shop\Http\Controllers;

use App\Http\Adapters\FrontPageResponseMapper;
use App\Http\Adapters\PublicContentContextFactory;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Shop\Contracts\ShopCatalogueServiceInterface;
use Modules\Shop\Models\Product;
use Modules\Shop\Models\ProductCategory;

class ShopFrontController extends Controller
{
    public function __construct(
        private readonly ShopCatalogueServiceInterface $catalogue,
        private readonly PublicContentContextFactory $contexts,
        private readonly FrontPageResponseMapper $responses,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        return $this->responses->respond($this->catalogue->index($this->contexts->fromRequest($request)));
    }

    public function resolveSlug(Request $request): View|RedirectResponse
    {
        return $this->responses->respond($this->catalogue->resolveSlug($this->contexts->fromRequest($request)));
    }

    public function category(Request $request, ?ProductCategory $category = null): View|RedirectResponse
    {
        return $this->responses->respond($this->catalogue->category($this->contexts->fromRequest($request), $category));
    }

    public function show(Request $request, ?Product $product = null): View
    {
        return $this->responses->view($this->catalogue->show($this->contexts->fromRequest($request), $product));
    }
}
