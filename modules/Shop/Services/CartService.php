<?php

declare(strict_types=1);

namespace Modules\Shop\Services;

use App\Models\Market;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Modules\Shop\DTOs\CartSummary;
use Modules\Shop\Models\Product;

class CartService
{
    protected string $sessionKey = 'shop_cart_items';

    /**
     * @return array<int, array{product_id: int, quantity: int}>
     */
    public function getRawCart(): array
    {
        return Session::get($this->sessionKey, []);
    }

    public function add(int $productId, int $quantity = 1): void
    {
        $cart = $this->getRawCart();

        if (isset($cart[$productId])) {
            $cart[$productId]['quantity'] += $quantity;
        } else {
            $cart[$productId] = [
                'product_id' => $productId,
                'quantity' => $quantity,
            ];
        }

        Session::put($this->sessionKey, $cart);
    }

    public function update(int $productId, int $quantity): void
    {
        $cart = $this->getRawCart();

        if ($quantity <= 0) {
            unset($cart[$productId]);
        } else {
            $cart[$productId] = [
                'product_id' => $productId,
                'quantity' => $quantity,
            ];
        }

        Session::put($this->sessionKey, $cart);
    }

    public function remove(int $productId): void
    {
        $cart = $this->getRawCart();
        unset($cart[$productId]);
        Session::put($this->sessionKey, $cart);
    }

    public function clear(): void
    {
        Session::forget($this->sessionKey);
    }

    public function count(): int
    {
        $cart = $this->getRawCart();
        $total = 0;
        foreach ($cart as $item) {
            $total += (int) $item['quantity'];
        }

        return $total;
    }

    /**
     * @return array<int, array{product: Product, quantity: int, unit_price: float, subtotal: float, formatted_unit_price: string, formatted_subtotal: string}>
     */
    public function getItems(?Market $market = null): array
    {
        $raw = $this->getRawCart();
        if (empty($raw)) {
            return [];
        }

        $productIds = array_keys($raw);
        $query = Product::whereIn('id', $productIds)->active();
        if ($market !== null) {
            $query->forMarket($market);
        }
        $products = $query->with(['translations', 'markets', 'category.translations'])
            ->get()
            ->keyBy('id');

        $items = [];
        foreach ($raw as $productId => $item) {
            /** @var Product|null $product */
            $product = $products->get($productId);
            if (! $product) {
                throw ValidationException::withMessages(['cart' => 'Un producto del carrito ya no está disponible en este mercado.']);
            }

            $price = $product->getPriceForMarket($market);
            $qty = (int) $item['quantity'];
            $subtotal = $price * $qty;

            $items[] = [
                'product' => $product,
                'quantity' => $qty,
                'unit_price' => $price,
                'subtotal' => $subtotal,
                'formatted_unit_price' => $this->formatPrice($price, $market),
                'formatted_subtotal' => $this->formatPrice($subtotal, $market),
            ];
        }

        return $items;
    }

    public function getSummary(?Market $market = null, float $shipping = 0.0): CartSummary
    {
        $items = $this->getItems($market);
        $subtotal = array_reduce($items, static fn (float $sum, array $item): float => $sum + $item['subtotal'], 0.0);
        $taxRate = (float) module_setting('shop', 'tax_rate', 21.0);
        $tax = round($subtotal * $taxRate / 100, 2);

        return new CartSummary($items, $subtotal, $tax, $subtotal + $tax + $shipping);
    }

    public function formatPrice(float $price, ?Market $market = null): string
    {
        $currency = $market->currency ?? (string) module_setting('shop', 'currency', 'EUR');

        return match ($currency) {
            'USD' => '$'.number_format($price, 2),
            'GBP' => '£'.number_format($price, 2),
            'MXN' => '$'.number_format($price, 2).' MXN',
            default => number_format($price, 2, ',', '.').' €',
        };
    }
}
