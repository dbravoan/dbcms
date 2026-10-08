<?php

declare(strict_types=1);

namespace Modules\Shop\Services;

use App\Contracts\Services\ModuleNotificationServiceInterface;
use App\Core\Sections\SectionSyncService;
use App\Core\Translations\EntityTranslationSyncService;
use App\Models\Market;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Shop\Models\Order;
use Modules\Shop\Models\OrderItem;
use Modules\Shop\Models\Product;
use Modules\Shop\Models\ProductTranslation;
use Modules\Shop\Models\ShopSetting;

class ShopDomainService
{
    /**
     * Checks if full e-commerce shop mode (cart & checkout) is active.
     */
    public function isShopEnabled(): bool
    {
        return ShopSetting::get('shop.mode', 'full_shop') === 'full_shop';
    }

    /**
     * Checks if catalog-only showcase mode is active.
     */
    public function isCatalogOnly(): bool
    {
        return ShopSetting::get('shop.mode', 'full_shop') === 'catalog_only';
    }

    public function getShopMode(): string
    {
        return (string) ShopSetting::get('shop.mode', 'full_shop');
    }

    /**
     * Determines whether product prices should be displayed.
     * In full shop mode, prices are always shown.
     * In catalog mode, prices are shown only if 'shop.catalog_show_prices' is true.
     */
    public function shouldShowPrices(): bool
    {
        if ($this->isShopEnabled()) {
            return true;
        }

        return (bool) ShopSetting::get('shop.catalog_show_prices', true);
    }

    /**
     * Creates a product with multi-language translations and market assignments.
     *
     * @param array{
     *     sku: string,
     *     category_id?: int|null,
     *     price: float,
     *     compare_price?: float|null,
     *     cost_price?: float|null,
     *     stock_quantity?: int,
     *     track_stock?: bool,
     *     is_digital?: bool,
     *     is_featured?: bool,
     *     is_active?: bool,
     *     featured_image?: string|null,
     *     gallery_images?: array<int, string>|null,
     *     specs_json?: array<string, mixed>|null,
     *     market_ids?: array<int, int>,
     *     market_prices?: array<int, float>,
     *     sections_json?: string|null,
     *     translations: array<string, array{name: string, slug?: string, summary?: string, description?: string, meta_title?: string, meta_description?: string, schema_type?: string}>
     * } $data
     */
    public function createProduct(array $data): Product
    {
        return DB::transaction(function () use ($data): Product {
            /** @var Product $product */
            $product = Product::create([
                'category_id' => $data['category_id'] ?? null,
                'sku' => $data['sku'],
                'price' => $data['price'],
                'compare_price' => $data['compare_price'] ?? null,
                'cost_price' => $data['cost_price'] ?? null,
                'stock_quantity' => $data['stock_quantity'] ?? 0,
                'track_stock' => $data['track_stock'] ?? true,
                'is_digital' => $data['is_digital'] ?? false,
                'is_featured' => $data['is_featured'] ?? false,
                'is_active' => $data['is_active'] ?? true,
                'featured_image' => $data['featured_image'] ?? null,
                'gallery_images' => $data['gallery_images'] ?? [],
                'specs_json' => $data['specs_json'] ?? [],
            ]);

            // Sync Markets and custom prices
            if (! empty($data['market_ids'])) {
                $syncData = [];
                foreach ($data['market_ids'] as $mId) {
                    $customPrice = $data['market_prices'][$mId] ?? null;
                    $syncData[$mId] = [
                        'custom_price' => $customPrice,
                        'is_active' => true,
                    ];
                }
                $product->markets()->sync($syncData);
            }

            // Create Translations
            $payload = [];
            foreach ($data['translations'] as $locale => $tData) {
                $payload[$locale] = [
                    'name' => $tData['name'] ?? '',
                    'slug' => $tData['slug'] ?? '',
                    'summary' => $tData['summary'] ?? null,
                    'description' => $tData['description'] ?? null,
                    'meta_title' => $tData['meta_title'] ?? ($tData['name'] ?? ''),
                    'meta_description' => $tData['meta_description'] ?? ($tData['summary'] ?? null),
                    'schema_type' => $tData['schema_type'] ?? 'Product',
                ];
            }

            app(EntityTranslationSyncService::class)->sync($product, ProductTranslation::class, 'product_id', $payload, 'name');

            // Sync Sections if present
            if (! empty($data['sections_json'])) {
                $this->syncSections($product, $data['sections_json']);
            }

            return $product->load(['translations', 'category.translations', 'markets', 'sections.translations']);
        });
    }

    public function syncSections(Product $product, string $sectionsJson): void
    {
        app(SectionSyncService::class)->sync($product, $sectionsJson);
    }

    /**
     * Creates an order and decrements inventory if tracked.
     *
     * @param  array<string, mixed>  $customerData
     * @param  array<int, array{product: Product, quantity: int, unit_price: float, subtotal: float}>  $cartItems
     */
    public function createOrder(array $customerData, array $cartItems, ?Market $market = null, string $paymentMethod = 'bank_transfer'): Order
    {
        CheckoutPolicy::paymentMethod($paymentMethod);

        return DB::transaction(function () use ($customerData, $cartItems, $market, $paymentMethod): Order {
            $quantities = [];
            foreach ($cartItems as $item) {
                $productId = (int) $item['product']->getKey();
                $quantities[$productId] = ($quantities[$productId] ?? 0) + $item['quantity'];
            }
            ksort($quantities);
            $query = Product::query()->whereIn('id', array_keys($quantities))->active();
            if ($market !== null) {
                $query->forMarket($market);
            }
            $products = $query->with(['translations', 'markets'])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $lines = [];
            foreach ($quantities as $productId => $quantity) {
                $product = $products->get($productId);
                if (! $product || $quantity < 1 || ((bool) ShopSetting::get('shop.enable_stock', true) && $product->track_stock && $product->stock_quantity < $quantity)) {
                    throw ValidationException::withMessages(['cart' => 'Uno de los productos ya no está disponible en la cantidad solicitada.']);
                }
                $lines[] = ['product' => $product, 'quantity' => $quantity, 'unit_price' => $product->getPriceForMarket($market)];
            }
            if ($lines === []) {
                throw ValidationException::withMessages(['cart' => 'El carrito está vacío.']);
            }
            $subtotal = 0.0;
            foreach ($lines as $item) {
                $subtotal += ($item['unit_price'] * $item['quantity']);
            }

            $taxRate = (float) ShopSetting::get('shop.tax_rate', 21.0);
            $taxTotal = round($subtotal * ($taxRate / 100), 2);
            $shippingTotal = (float) ($customerData['shipping_total'] ?? 0.00);
            $grandTotal = $subtotal + $taxTotal + $shippingTotal;

            $currency = $market->currency ?? (string) ShopSetting::get('shop.currency', 'EUR');
            $orderNumber = 'ORD-'.strtoupper(Str::random(16));

            /** @var Order $order */
            $order = Order::create([
                'order_number' => $orderNumber,
                'user_id' => auth()->check() ? (int) auth()->id() : null,
                'market_id' => $market?->id,
                'currency' => $currency,
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'payment_method' => $paymentMethod,
                'subtotal' => $subtotal,
                'tax_total' => $taxTotal,
                'shipping_total' => $shippingTotal,
                'discount_total' => 0.00,
                'grand_total' => $grandTotal,
                'customer_name' => (string) $customerData['customer_name'],
                'customer_email' => (string) $customerData['customer_email'],
                'customer_phone' => (string) ($customerData['customer_phone'] ?? ''),
                'billing_address' => (array) ($customerData['billing_address'] ?? []),
                'shipping_address' => (array) ($customerData['shipping_address'] ?? $customerData['billing_address'] ?? []),
                'notes' => (string) ($customerData['notes'] ?? ''),
            ]);

            foreach ($lines as $item) {
                /** @var Product $prod */
                $prod = $item['product'];
                $itemSubtotal = $item['unit_price'] * $item['quantity'];

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $prod->id,
                    'product_name' => $prod->translations->first()->name ?? 'Producto #'.$prod->id,
                    'sku' => $prod->sku,
                    'unit_price' => $item['unit_price'],
                    'quantity' => $item['quantity'],
                    'subtotal' => $itemSubtotal,
                    'total' => $itemSubtotal,
                ]);

                // Stock decrement
                if ((bool) ShopSetting::get('shop.enable_stock', true) && $prod->track_stock) {
                    // The row remains locked; save also emits cache/index observers.
                    $prod->stock_quantity -= $item['quantity'];
                    $prod->save();
                }
            }

            DB::afterCommit(function () use ($order): void {
                if (app()->bound(ModuleNotificationServiceInterface::class)) {
                    try {
                        /** @var ModuleNotificationServiceInterface $notifier */
                        $notifier = app(ModuleNotificationServiceInterface::class);
                        $notifier->notify(
                            module: 'shop',
                            type: 'order',
                            title: "Nuevo Pedido #{$order->order_number}",
                            message: "Pedido de {$order->customer_name} ({$order->customer_email}) por importe de {$order->grand_total} {$order->currency}.",
                            actionUrl: route('admin.shop.orders.show', $order),
                            data: ['order_id' => $order->id, 'order_number' => $order->order_number, 'total' => $order->grand_total],
                            sendEmail: true
                        );
                    } catch (\Throwable) {
                        // Fail silently to prevent interrupting transaction
                    }
                }
            });

            return $order->load(['items', 'market']);
        });
    }

    /**
     * Queries active products scoped by market and category.
     *
     * @return LengthAwarePaginator<int, Product>
     */
    public function getPublishedProducts(?Market $market = null, ?int $categoryId = null, ?string $search = null, int $perPage = 12): LengthAwarePaginator
    {
        $query = Product::active()
            ->with(['translations', 'category.translations', 'markets']);

        if ($market) {
            $query->forMarket($market);
        }

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        if (! empty($search)) {
            $query->where(function ($q) use ($search): void {
                $q->where('sku', 'like', "%{$search}%")
                    ->orWhereHas('translations', function ($tQ) use ($search): void {
                        $tQ->where('name', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%");
                    });
            });
        }

        return $query->orderBy('is_featured', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }
}
