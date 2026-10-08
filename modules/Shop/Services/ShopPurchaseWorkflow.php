<?php

declare(strict_types=1);

namespace Modules\Shop\Services;

use App\Contracts\Services\LocalizationServiceInterface;
use App\DTOs\Front\FrontPageData;
use App\Models\Market;
use App\Services\Cms\FrontViewResolver;
use Modules\Shop\Contracts\InquiryServiceInterface;
use Modules\Shop\Contracts\ShopPurchaseWorkflowInterface;
use Modules\Shop\DTOs\CartSummary;
use Modules\Shop\DTOs\CheckoutCommandData;
use Modules\Shop\DTOs\InquirySubmission;
use Modules\Shop\DTOs\OrderAccessContext;
use Modules\Shop\DTOs\PurchaseOutcome;
use Modules\Shop\Models\Order;
use Modules\Shop\Models\Product;

final class ShopPurchaseWorkflow implements ShopPurchaseWorkflowInterface
{
    public function __construct(private readonly ShopDomainService $shop, private readonly CartService $cart, private readonly InquiryServiceInterface $inquiries, private readonly LocalizationServiceInterface $localization, private readonly FrontViewResolver $views) {}

    public function cart(Market $market, string $locale): FrontPageData
    {
        if (! $this->shop->isShopEnabled()) {
            return FrontPageData::redirect($this->localization->url('/shop', $market, $locale), 302, ['info' => 'La tienda online no está activa en este momento.']);
        }

        return $this->cartPage('shop.cart', 'shop::cart', $market, $locale);
    }

    public function checkout(Market $market, string $locale): FrontPageData
    {
        if (! $this->shop->isShopEnabled()) {
            return FrontPageData::redirect($this->localization->url('/shop', $market, $locale), 302);
        }
        $summary = $this->cart->getSummary($market);
        if ($summary->items === []) {
            return FrontPageData::redirect($this->localization->url('/cart', $market, $locale), 302, ['error' => 'Tu carrito de compras está vacío.']);
        }

        return $this->cartPage('shop.checkout', 'shop::checkout', $market, $locale, $summary);
    }

    public function purchase(CheckoutCommandData $command, Market $market): PurchaseOutcome
    {
        if (! $this->shop->isShopEnabled()) {
            return new PurchaseOutcome(null, FrontPageData::redirect($this->localization->url('/shop', $market), 302));
        }
        $items = $this->cart->getItems($market);
        if ($items === []) {
            return new PurchaseOutcome(null, FrontPageData::redirect($this->localization->url('/cart', $market), 302, ['error' => 'El carrito está vacío.']));
        }
        $order = $this->shop->createOrder($command->customerData, $items, $market, $command->paymentMethod);
        $this->cart->clear();

        return new PurchaseOutcome($order, null);
    }

    public function order(string $number, OrderAccessContext $actor, Market $market): FrontPageData
    {
        $order = Order::where('order_number', $number)->with(['items.product.translations', 'market'])->firstOrFail();
        $owner = $actor->userId !== null && $order->user_id !== null && $actor->userId === $order->user_id;
        abort_unless($owner || in_array($number, $actor->sessionOrderNumbers, true) || $actor->validSignature, 404);
        abort_if($order->market_id !== null && $order->market_id !== $market->id, 404);

        return FrontPageData::view($this->views->resolve('shop.order_success', 'shop::order_success'), ['order' => $order]);
    }

    public function inquiry(InquirySubmission $input, Market $market, string $locale): string
    {
        $this->inquiries->submit($input, $market, $locale);
        $product = Product::with('translations')->findOrFail($input->productId);

        $translation = $product->translations->firstWhere('locale', $locale) ?? $product->translations->first();

        return (string) ($translation->name ?? '');
    }

    /** @param view-string $fallback */
    private function cartPage(string $name, string $fallback, Market $market, string $locale, ?CartSummary $summary = null): FrontPageData
    {
        $summary ??= $this->cart->getSummary($market);

        return FrontPageData::view($this->views->resolve($name, $fallback), [
            'items' => $summary->items, 'subtotal' => $summary->subtotal,
            'taxTotal' => $summary->taxTotal, 'grandTotal' => $summary->grandTotal,
            'currentMarket' => $market, 'currentLocale' => $locale,
        ]);
    }
}
