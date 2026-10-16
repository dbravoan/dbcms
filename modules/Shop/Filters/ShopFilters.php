<?php

declare(strict_types=1);

namespace Modules\Shop\Filters;

use App\Contracts\Services\LocalizationServiceInterface;
use App\Core\Filters\Contracts\FilterableModule;
use App\Core\Filters\Contracts\FilterableResults;
use App\Core\Filters\FilterDimension;
use App\Core\Filters\FilterSet;
use App\Core\Filters\FilterUrlBuilder;
use App\Models\Market;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Shop\Models\Product;
use Modules\Shop\Models\ProductCategory;

/**
 * Shop filter dimensions (friendly, canonical, SEO-aware URLs).
 *
 * `category` is implicit: a bare leading slug scopes the catalogue, so
 * `/tienda/camisetas/precio/100-200/orden/precio-asc` is one canonical URL.
 */
final class ShopFilters implements FilterableModule, FilterableResults
{
    private const MARKET_PRICE_SQL = 'COALESCE((SELECT market_product.custom_price FROM market_product WHERE market_product.product_id = products.id AND market_product.market_id = ? AND market_product.is_active = ?), products.price)';

    /**
     * Runs a filter request against the catalogue (single source of truth
     * shared by the front controller and the MCP browsing tool).
     *
     * @return array{paginator: LengthAwarePaginator<int, Product>, category: ProductCategory|null}|null
     */
    public function listQuery(FilterSet $set, Market $market, string $locale, int $perPage = 12): ?array
    {
        $query = Product::active()
            ->forMarket($market)
            ->with(['translations', 'category.translations', 'markets'])
            ->whereHas('translations', fn ($q) => $q->where('locale', $locale));

        $selectedCategory = null;

        foreach ($set->list('category') as $categorySlug) {
            $selectedCategory = ProductCategory::forMarket($market)->where('is_active', true)->whereHas('translations', fn ($q) => $q->where('slug', $categorySlug)->where('locale', $locale))->first();

            if ($selectedCategory === null) {
                return null;
            }

            $query->where('category_id', $selectedCategory->getKey());
        }

        if (($term = $set->string('search')) !== null) {
            $term = str_replace('-', ' ', $term);
            $query->whereHas('translations', fn ($q) => $q->where('locale', $locale)->where('name', 'like', "%{$term}%"));
        }

        if (($price = $set->string('price')) !== null) {
            [$min, $max] = array_pad(explode('-', $price, 2), 2, '');

            if ($min !== '') {
                $query->whereRaw(self::MARKET_PRICE_SQL.' >= ?', [$market->getKey(), true, (float) $min]);
            }

            if ($max !== '') {
                $query->whereRaw(self::MARKET_PRICE_SQL.' <= ?', [$market->getKey(), true, (float) $max]);
            }
        }

        if ($set->bool('featured')) {
            $query->where('is_featured', true);
        }

        match ($set->string('sort', 'novedades')) {
            'precio-asc' => $query->orderByRaw(self::MARKET_PRICE_SQL.' ASC', [$market->getKey(), true])->orderBy('products.id'),
            'precio-desc' => $query->orderByRaw(self::MARKET_PRICE_SQL.' DESC', [$market->getKey(), true])->orderBy('products.id'),
            default => $query->orderByDesc('created_at'),
        };

        return ['paginator' => $query->paginate($perPage), 'category' => $selectedCategory];
    }

    /**
     * Agent-facing projection of a filter request (MCP / WebMCP navigation).
     *
     * @return array{items: array<int, array<string, mixed>>, total: int, canonical_path: string}
     */
    public function browse(FilterSet $set, Market $market, string $locale, int $perPage = 10): array
    {
        $result = $this->listQuery($set, $market, $locale, $perPage);

        if ($result === null) {
            return ['items' => [], 'total' => 0, 'canonical_path' => ''];
        }

        $localization = app(LocalizationServiceInterface::class);
        $prefix = $localization->getModulePrefix($this->filterModuleKey(), $locale);

        $items = [];

        foreach ($result['paginator']->items() ?? [] as $product) {
            $translation = $product->translations->firstWhere('locale', $locale) ?? $product->translations->first();
            $slug = (string) ($translation->slug ?? '');

            if ($slug === '') {
                continue;
            }

            $items[] = [
                'id' => (int) $product->getKey(),
                'type' => 'product',
                'title' => (string) ($translation->name ?? $slug),
                'price' => $product->getPriceForMarket($market),
                'featured' => (bool) $product->is_featured,
                'url' => $localization->url("/{$prefix}/{$slug}", $market, $locale),
            ];
        }

        return [
            'items' => $items,
            'total' => (int) ($result['paginator']->total() ?? 0),
            'canonical_path' => (string) ((app(FilterUrlBuilder::class))->canonicalPath($set, $this->filterDimensions(), $locale) ?? ''),
        ];
    }

    public function filterModuleKey(): string
    {
        return 'shop';
    }

    public function filterModuleLabel(string $locale): string
    {
        return match ($locale) {
            'en' => 'Shop',
            'ca' => 'Botiga',
            default => 'Tienda',
        };
    }

    /**
     * @return array<int, FilterDimension>
     */
    public function filterDimensions(): array
    {
        return [
            FilterDimension::terms(
                'category',
                order: 10,
                vocabulary: fn (string $locale, string $marketCode): array => $this->categories($locale, $marketCode),
                implicit: true,
            ),
            FilterDimension::text('search', order: 20),
            // The unit of a price range is the market currency (eur, usd, mxn…).
            FilterDimension::range('price', order: 30, marketCurrency: true),
            FilterDimension::enum('sort', [
                'precio-asc' => ['es' => 'Precio ↑', 'en' => 'Price ↑', 'ca' => 'Preu ↑'],
                'precio-desc' => ['es' => 'Precio ↓', 'en' => 'Price ↓', 'ca' => 'Preu ↓'],
                'novedades' => ['es' => 'Novedades', 'en' => 'Newest', 'ca' => 'Novetats'],
            ], order: 40),
            FilterDimension::bool('featured', order: 50),
        ];
    }

    /**
     * @return array<string, array{label: string, key: int}>
     */
    private function categories(string $locale, string $marketCode): array
    {
        $market = Market::query()->where('code', $marketCode)->where('is_active', true)
            ->whereHas('languages', fn ($languages) => $languages->where('code', $locale)->where('is_active', true))->first();
        if ($market === null) {
            return [];
        }
        $map = [];

        $categories = ProductCategory::query()
            ->forMarket($market)
            ->where('is_active', true)
            ->whereHas('translations', fn ($translations) => $translations->where('locale', $locale))
            ->with('translations')
            ->get();

        foreach ($categories as $category) {
            $translation = $category->translations->firstWhere('locale', $locale);

            $slug = (string) ($translation->slug ?? '');
            $label = (string) ($translation->name ?? $category->getKey());

            if ($slug !== '') {
                $map[$slug] = ['label' => $label, 'key' => (int) $category->getKey()];
            }
        }

        return $map;
    }
}
