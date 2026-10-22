<?php

declare(strict_types=1);

namespace Modules\Shop\Services;

use App\Contracts\Services\LocalizationServiceInterface;
use App\Core\Filters\FilterAlternateUrls;
use App\Core\Filters\FilterIntroRenderer;
use App\Core\Filters\FilterPathParser;
use App\Core\Filters\FilterQueryRedirector;
use App\Core\Filters\FilterSeoResolver;
use App\Core\Filters\FilterSet;
use App\Core\Filters\FilterUrlBuilder;
use App\Core\Sections\SectionRendererService;
use App\DTOs\Front\FrontPageData;
use App\DTOs\Front\PublicContentContext;
use App\Models\Market;
use App\Services\Cms\FrontViewResolver;
use App\Services\Seo\JsonLdSchemaService;
use App\Services\Seo\PublicContentModifiedService;
use App\Services\Seo\SeoMetaService;
use Modules\Shop\Contracts\ShopCatalogueServiceInterface;
use Modules\Shop\Filters\ShopFilters;
use Modules\Shop\Models\Product;
use Modules\Shop\Models\ProductCategory;
use Modules\Shop\Models\ProductTranslation;

final class ShopCatalogueService implements ShopCatalogueServiceInterface
{
    public function __construct(
        protected ShopDomainService $shopService,
        private readonly FrontViewResolver $views,
        private readonly LocalizationServiceInterface $localization,
        private readonly SectionRendererService $sectionRenderer,
    ) {}

    public function index(PublicContentContext $context): FrontPageData
    {
        /** @var Market $market */
        $market = $context->market;
        $locale = $context->locale;

        // Filter form submissions (ranges, picks…) consolidate into the canonical
        // friendly URL with a 301.
        $formPath = app(FilterQueryRedirector::class)
            ->canonicalPath(app(ShopFilters::class), $market, $locale, $context->query);

        if ($formPath !== null) {
            $shopSlug = $this->localization->getModulePrefix('shop', $locale);

            return FrontPageData::redirect($this->localization->url("/{$shopSlug}/{$formPath}", $market), 301);
        }

        return $this->renderCatalogue($market, $locale);
    }

    /**
     * Catalogue renderer shared by the index and category routes.
     */
    private function renderCatalogue(Market $market, string $locale, ?ProductCategory $selectedCategory = null): FrontPageData
    {
        $products = $this->shopService->getPublishedProducts($market, $selectedCategory?->getKey(), null, 12, $locale);

        return FrontPageData::view($this->views->resolve('shop.index', 'shop::index'), [
            'products' => $products,
            'categories' => ProductCategory::forMarket($market)->whereHas('translations', fn ($q) => $q->where('locale', $locale))->with('translations')->where('is_active', true)->orderBy('order', 'asc')->get(),
            'currentMarket' => $market,
            'currentLocale' => $locale,
            'isShop' => $this->shopService->isShopEnabled(),
            'showPrices' => $this->shopService->shouldShowPrices(),
            'selectedCategory' => $selectedCategory?->getKey(),
            'selectedCategoryModel' => $selectedCategory,
            'searchQuery' => null,
            // Empty filter set → themes can still render the facet links.
            'filterSet' => new FilterSet('shop', (string) $market->code, $locale, []),
            'filterModule' => app(ShopFilters::class),
        ]);
    }

    public function resolveSlug(PublicContentContext $context): FrontPageData
    {
        /** @var Market $market */
        $market = $context->market;
        $locale = $context->locale;
        $slug = (string) $context->slug;

        // 1. Check if slug matches a product category
        $category = ProductCategory::forMarket($market)->whereHas('translations', function ($q) use ($slug, $locale): void {
            $q->where('slug', $slug)->where('locale', $locale);
        })->where('is_active', true)->first();

        if ($category !== null) {
            return $this->category($context, $category);
        }

        // 2. Check if slug matches an active product for this market
        $product = Product::active()
            ->forMarket($market)
            ->whereHas('translations', function ($q) use ($slug, $locale): void {
                $q->where('slug', $slug)->where('locale', $locale);
            })
            ->with([
                'translations', 'markets', 'publishedSections.translations',
                'category' => fn ($category) => $category->where('is_active', true)->forMarket($market)
                    ->whereHas('translations', fn ($translations) => $translations->where('locale', $locale))->with('translations'),
            ])
            ->first();

        if ($product !== null) {
            return $this->show($context, $product);
        }

        // 3. Friendly filter/search path (e.g. `camisetas/precio/100-200`).
        $filters = app(ShopFilters::class);
        $set = (app(FilterPathParser::class))->parse(
            'shop',
            (string) $market->code,
            $locale,
            trim($slug, '/'),
            $filters->filterDimensions(),
        );

        if ($set !== null) {
            return $this->filtered($context, $filters, $set);
        }

        abort(404);
    }

    /**
     * Render a dynamic, market/locale-scoped catalogue filter page.
     */
    private function filtered(
        PublicContentContext $context,
        ShopFilters $filters,
        FilterSet $set,
    ): FrontPageData {
        /** @var Market $market */
        $market = $context->market;
        $locale = $context->locale;
        $shopSlug = $this->localization->getModulePrefix('shop', $locale);
        $dimensions = $filters->filterDimensions();

        $canonical = (app(FilterUrlBuilder::class))->canonicalPath($set, $dimensions, $locale) ?? '';
        $requested = trim((string) $context->slug, '/');

        if ($canonical !== '' && $canonical !== $requested) {
            return FrontPageData::redirect($this->localization->url("/{$shopSlug}/{$canonical}", $market), 301);
        }

        // The query lives with the module (single source of truth, reused by the
        // MCP browsing tool); an unresolvable value is a 404 here.
        $result = $filters->listQuery($set, $market, $locale, 12);

        if ($result === null) {
            abort(404);
        }

        $products = $result['paginator'];
        $selectedCategory = $result['category'];

        if ($products->total() === 0) {
            abort(404);
        }

        $filterSeo = app(FilterSeoResolver::class)->resolve($filters, $set, $dimensions, $products->total(), (string) $market->code);

        // Curated intro, composed with the CMS block manager.
        $curated = app(FilterSeoResolver::class)->curated($filters, $set, (string) $market->code);
        $filterIntro = app(FilterIntroRenderer::class)->render($curated, $market, $locale);

        $catalogLabel = match ($locale) {
            'en' => 'Shop',
            'ca' => 'Botiga',
            default => 'Tienda',
        };

        $canonicalUrl = $this->localization->url("/{$shopSlug}/{$canonical}", $market);

        $breadcrumbs = [
            ['title' => match ($locale) {
                'en' => 'Home',
                'ca' => 'Inici',
                default => 'Inicio',
            }, 'url' => $this->localization->url('/', $market)],
            ['title' => $catalogLabel, 'url' => $this->localization->url("/{$shopSlug}", $market)],
            ['title' => $filterSeo->heading, 'url' => $canonicalUrl],
        ];

        $alternates = app(FilterAlternateUrls::class)
            ->build($set, $dimensions, $filters, $market, $this->localization);

        $items = [];
        foreach ($products->items() as $product) {
            $translation = $product->translations->firstWhere('locale', $locale) ?? $product->translations->first();
            $productSlug = (string) ($translation->slug ?? '');

            if ($productSlug === '') {
                continue;
            }

            $items[] = [
                'name' => (string) ($translation->name ?? $productSlug),
                'url' => $this->localization->url("/{$shopSlug}/{$productSlug}", $market, $locale),
            ];
        }

        $schema = app(JsonLdSchemaService::class)->generateCollectionGraph(
            name: $filterSeo->heading,
            description: $filterSeo->description,
            url: $canonicalUrl,
            locale: $locale,
            items: $items,
            breadcrumbs: array_map(static fn (array $crumb): array => ['name' => $crumb['title'], 'url' => $crumb['url']], $breadcrumbs),
        );

        $seoMeta = app(SeoMetaService::class)->forWebPage(
            title: $filterSeo->title,
            description: $filterSeo->description,
            canonical: $canonicalUrl,
            robots: $filterSeo->robots,
            locale: $locale,
            alternates: array_values($alternates),
            schemaOverride: $schema,
        );

        return FrontPageData::view($this->views->resolve('shop.index', 'shop::index'), [
            'products' => $products,
            'categories' => ProductCategory::forMarket($market)->whereHas('translations', fn ($q) => $q->where('locale', $locale))->with('translations')->where('is_active', true)->orderBy('order')->get(),
            'currentMarket' => $market,
            'currentLocale' => $locale,
            'isShop' => $this->shopService->isShopEnabled(),
            'showPrices' => $this->shopService->shouldShowPrices(),
            'selectedCategory' => $selectedCategory?->getKey(),
            'selectedCategoryModel' => $selectedCategory,
            'searchQuery' => $set->string('search'),
            'seoMeta' => $seoMeta,
            'filterSet' => $set,
            'filterModule' => $filters,
            'filterHeading' => $filterSeo->heading,
            // Front-office bar: one-click "index this filter in Google".
            'filterCurationUrl' => route('admin.seo.filters.index', [
                'module' => 'shop',
                'market_code' => (string) $market->code,
                'locale' => $locale,
                'path' => $canonical,
            ]),
            'filterIsIndexable' => $filterSeo->indexable,
            'filterIntro' => $filterIntro,
        ]);
    }

    public function category(PublicContentContext $context, ?ProductCategory $category = null): FrontPageData
    {
        /** @var Market $market */
        $market = $context->market;
        $locale = $context->locale;
        $slug = (string) $context->slug;

        /** @var ProductCategory $cat */
        $cat = $category ?? ProductCategory::forMarket($market)->where('is_active', true)->whereHas('translations', function ($q) use ($slug, $locale): void {
            $q->where('slug', $slug)->where('locale', $locale);
        })->firstOrFail();
        abort_unless(ProductCategory::forMarket($market)->where('is_active', true)->whereHas('translations', fn ($q) => $q->where('locale', $locale))->whereKey($cat->getKey())->exists(), 404);

        return $this->renderCatalogue($market, $locale, $cat);
    }

    public function show(PublicContentContext $context, ?Product $product = null): FrontPageData
    {
        /** @var Market $market */
        $market = $context->market;
        $locale = $context->locale;
        $slug = (string) $context->slug;

        /** @var Product $product */
        $product = $product ?? Product::active()
            ->forMarket($market)
            ->whereHas('translations', function ($q) use ($slug, $locale): void {
                $q->where('slug', $slug)->where('locale', $locale);
            })
            ->with([
                'translations', 'markets', 'publishedSections.translations',
                'category' => fn ($category) => $category->where('is_active', true)->forMarket($market)
                    ->whereHas('translations', fn ($translations) => $translations->where('locale', $locale))->with('translations'),
            ])
            ->firstOrFail();

        abort_unless($product->is_active && Product::forMarket($market)->whereKey($product->getKey())->exists(), 404);
        $activeTranslation = $product->translations->firstWhere('locale', $locale);
        abort_if($activeTranslation === null, 404);

        // Render modular sections
        $renderedSections = [];
        if ($product->publishedSections->isNotEmpty()) {
            foreach ($product->publishedSections as $section) {
                $renderedSections[] = $this->sectionRenderer->renderSection($section, $market, $locale);
            }
        }

        // Build localized alternate hreflang tags & switcher map
        $slugsMap = [];
        foreach ($product->translations as $t) {
            $slugsMap[$t->locale] = $t->slug;
        }
        $shopSlug = $this->localization->getModulePrefix('shop', $locale);
        $hreflangs = $this->localization->buildHreflangCluster('{shopPrefix}/{slug}', $slugsMap, $product);
        $alternateUrls = $this->localization->buildAlternateUrlsMapForPath($context->segments, $slugsMap);

        // Related Products
        $relatedProducts = Product::active()
            ->forMarket($market)
            ->whereHas('translations', fn ($q) => $q->where('locale', $locale))
            ->where('id', '!=', $product->id)
            ->where('category_id', $product->category_id)
            ->with(['translations', 'markets'])
            ->limit(3)
            ->get();

        $isShop = $this->shopService->isShopEnabled();
        $showPrices = $this->shopService->shouldShowPrices();

        $viewName = $this->views->resolve('shop.show', 'shop::show');

        // Rich, entity-aware SEO/GEO metadata (Product + Offer JSON-LD graph).
        /** @var SeoMetaService $seo */
        $seo = app(SeoMetaService::class);
        $seoMeta = $seo->forWebPage(
            title: (string) ($activeTranslation->meta_title ?: $activeTranslation->name ?: ''),
            description: (string) ($activeTranslation->meta_description ?: $activeTranslation->summary ?: ''),
            canonical: $context->currentUrl,
            image: $product->featured_image ?: null,
            entitySchema: $this->productSchema($product, $activeTranslation, $market, $context->currentUrl),
            modifiedAt: app(PublicContentModifiedService::class)->forEntity($product),
            alternates: array_map(static fn (string $tag, string $href): array => ['hreflang' => $tag, 'href' => $href], array_keys($hreflangs), array_values($hreflangs)),
            type: 'product',
            locale: $locale,
            breadcrumbs: [
                ['name' => 'Inicio', 'url' => url("/{$market->code}/{$locale}")],
                ['name' => 'Tienda', 'url' => url("/{$market->code}/{$locale}/{$shopSlug}")],
                ['name' => (string) ($activeTranslation->name ?? ''), 'url' => $context->currentUrl],
            ],
        );

        return FrontPageData::view($viewName, [
            'product' => $product,
            'translation' => $activeTranslation,
            'renderedSections' => $renderedSections,
            'relatedProducts' => $relatedProducts,
            'hreflangs' => $hreflangs,
            'seoMeta' => $seoMeta,
            'alternateUrls' => $alternateUrls,
            'currentMarket' => $market,
            'currentLocale' => $locale,
            'isShop' => $isShop,
            'showPrices' => $showPrices,
        ]);
    }

    /**
     * Builds the Schema.org Product node (with Offer when prices are visible).
     *
     * @param  ProductTranslation|null  $translation
     * @return array<string, mixed>
     */
    private function productSchema(Product $product, mixed $translation, Market $market, string $currentUrl): array
    {
        $schema = [
            '@type' => 'Product',
            'name' => (string) ($translation->name ?? ''),
            'description' => (string) ($translation->summary ?? ''),
            'sku' => (string) $product->sku,
        ];

        if (! empty($product->featured_image)) {
            $schema['image'] = $product->featured_image;
        }

        $price = $product->getPriceForMarket($market);
        if ($this->shopService->shouldShowPrices() && $price >= 0) {
            $schema['offers'] = [
                '@type' => 'Offer',
                'price' => (string) $price,
                'priceCurrency' => (string) $market->currency,
                'availability' => $product->stock_quantity > 0
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
                'url' => $currentUrl,
            ];
        }

        return $schema;
    }
}
