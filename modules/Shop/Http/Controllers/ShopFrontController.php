<?php

declare(strict_types=1);

namespace Modules\Shop\Http\Controllers;

use App\Contracts\Services\LocalizationServiceInterface;
use App\Http\Adapters\FrontPageResponseMapper;
use App\Http\Adapters\PublicContentContextFactory;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Pages\Services\SectionRendererService;
use Modules\Shop\Contracts\ShopCatalogueServiceInterface;
use Modules\Shop\Http\Controllers\Front\CartGetController;
use Modules\Shop\Http\Controllers\Front\CheckoutGetController;
use Modules\Shop\Http\Controllers\Front\CheckoutPostController;
use Modules\Shop\Http\Controllers\Front\InquiryPostController;
use Modules\Shop\Http\Controllers\Front\OrderSuccessGetController;
use Modules\Shop\Http\Requests\CheckoutRequest;
use Modules\Shop\Http\Requests\InquiryRequest;
use Modules\Shop\Models\Product;
use Modules\Shop\Models\ProductCategory;

class ShopFrontController extends Controller
{
    public function __construct(
        private readonly ShopCatalogueServiceInterface $catalogue,
        private readonly PublicContentContextFactory $contexts,
        private readonly FrontPageResponseMapper $responses,
    ) {}

    public function index(Request $request, LocalizationServiceInterface $localization): View|RedirectResponse
    {
        return $this->responses->respond($this->catalogue->index($this->contexts->fromRequest($request)));
    }

    public function resolveSlug(Request $request, LocalizationServiceInterface $localization, SectionRendererService $sectionRenderer): View|RedirectResponse
    {
        return $this->responses->respond($this->catalogue->resolveSlug($this->contexts->fromRequest($request)));
    }

    public function category(Request $request, LocalizationServiceInterface $localization, ?ProductCategory $category = null): View|RedirectResponse
    {
        return $this->responses->respond($this->catalogue->category($this->contexts->fromRequest($request), $category));
    }

    public function show(Request $request, LocalizationServiceInterface $localization, SectionRendererService $sectionRenderer, ?Product $product = null): View
    {
        return $this->responses->view($this->catalogue->show($this->contexts->fromRequest($request), $product));
    }

    public function cart(LocalizationServiceInterface $localization): View|RedirectResponse
    {
        return app(CartGetController::class)();
    }

    public function checkout(LocalizationServiceInterface $localization): View|RedirectResponse
    {
        return app(CheckoutGetController::class)();
    }

    public function processCheckout(CheckoutRequest $request, LocalizationServiceInterface $localization): RedirectResponse
    {
        return app(CheckoutPostController::class)($request);
    }

    public function orderSuccess(Request $request, ?string $orderNumber = null): View
    {
        return app(OrderSuccessGetController::class)($request, $orderNumber);
    }

    public function submitInquiry(InquiryRequest $request): RedirectResponse
    {
        return app(InquiryPostController::class)($request);
    }
}
