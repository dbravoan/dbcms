<?php

declare(strict_types=1);

namespace Modules\Shop\Services;

use App\Models\Market;
use Illuminate\Validation\ValidationException;
use Modules\Shop\Contracts\CartApplicationInterface;
use Modules\Shop\DTOs\CartMutationResult;
use Modules\Shop\Models\Product;

final class CartApplicationService implements CartApplicationInterface
{
    public function __construct(private readonly CartService $cart, private readonly ShopDomainService $shop) {}

    public function read(?Market $market, string $locale): array
    {
        $summary = $this->cart->getSummary($market);
        $items = array_map(static function (array $item) use ($locale): array {
            $product = $item['product'];
            $item['product'] = ['id' => $product->getKey(), 'name' => $product->translations->firstWhere('locale', $locale)?->name,
                'sku' => $product->sku, 'featured_image' => $product->featured_image];

            return $item;
        }, $summary->items);

        return ['enabled' => $this->shop->isShopEnabled(), 'count' => $this->cart->count(), 'items' => $items,
            'subtotal' => $summary->subtotal, 'formatted_subtotal' => $this->cart->formatPrice($summary->subtotal, $market),
            'tax' => $summary->taxTotal, 'total' => $summary->grandTotal,
            'formatted_total' => $this->cart->formatPrice($summary->grandTotal, $market)];
    }

    public function add(int $productId, int $quantity, Market $market): CartMutationResult
    {
        if ($quantity < 1) {
            throw ValidationException::withMessages(['quantity' => 'La cantidad debe ser positiva.']);
        }
        $product = Product::active()->forMarket($market)->findOrFail($productId);
        $existing = (int) ($this->cart->getRawCart()[$productId]['quantity'] ?? 0);
        if ($this->exceedsStock($product, $existing + $quantity)) {
            return new CartMutationResult(['success' => false, 'message' => 'No hay suficiente stock disponible para este producto.'], false);
        }
        $this->cart->add($productId, $quantity);

        return new CartMutationResult(['success' => true, 'message' => 'Producto añadido al carrito.', 'count' => $this->cart->count()]);
    }

    public function update(int $productId, int $quantity, Market $market): array
    {
        if ($quantity < 0) {
            throw ValidationException::withMessages(['quantity' => 'La cantidad no puede ser negativa.']);
        }
        $product = Product::active()->forMarket($market)->findOrFail($productId);
        if ($this->exceedsStock($product, $quantity)) {
            throw ValidationException::withMessages(['quantity' => 'No hay suficiente stock disponible.']);
        }
        $this->cart->update($productId, $quantity);

        return ['success' => true, 'count' => $this->cart->count()];
    }

    public function remove(int $productId): array
    {
        $this->cart->remove($productId);

        return ['success' => true, 'count' => $this->cart->count()];
    }

    public function clear(): array
    {
        $this->cart->clear();

        return ['success' => true, 'count' => 0];
    }

    private function exceedsStock(Product $product, int $quantity): bool
    {
        return (bool) module_setting('shop', 'enable_stock', true) && $product->track_stock && $product->stock_quantity < $quantity;
    }
}
