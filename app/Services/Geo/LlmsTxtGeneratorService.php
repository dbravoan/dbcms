<?php

declare(strict_types=1);

namespace App\Services\Geo;

use App\Contracts\Services\GeoServiceInterface;
use App\Contracts\Services\LocalizationServiceInterface;
use App\Contracts\Services\PublicContentDiscoveryInterface;
use App\Contracts\Services\PublishedSectionTextInterface;
use App\Core\Content\PublicSemanticRegistry;
use App\Core\Filters\FilterPathResolver;
use App\Core\Modules\ModuleManager;
use App\Models\FilterPage;
use App\Models\Market;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Modules\Blog\Models\Category as BlogCategory;
use Modules\Blog\Models\Post;
use Modules\Gallery\Models\Gallery;
use Modules\Pages\Models\Page;
use Modules\Shop\Models\Product;
use Modules\Shop\Models\ProductCategory as ShopCategory;
use Modules\Shop\Services\ShopDomainService;

class LlmsTxtGeneratorService implements GeoServiceInterface
{
    public function __construct(
        protected ContextChunkingService $chunker
    ) {}

    /**
     * {@inheritdoc}
     */
    public function generateLlmsTxt(?Market $market = null, ?string $locale = null): string
    {
        $siteName = (string) (setting('general.site_name', '') ?: Config::get('geo.site_name', Config::get('cms.name', '')));
        $siteDesc = (string) (setting('general.site_description', '') ?: Config::get('geo.site_description', ''));

        return ($market !== null && ! empty($locale))
            ? $this->scopedIndex($market, $locale, $siteName, $siteDesc)
            : $this->globalIndex($siteName, $siteDesc);
    }

    /**
     * Market + locale scoped index (/es/es/llms.txt).
     */
    private function scopedIndex(Market $market, string $locale, string $siteName, string $siteDesc): string
    {
        $base = app(LocalizationServiceInterface::class)->url('/', $market, $locale);
        $localization = app(LocalizationServiceInterface::class);
        $blogSlug = $localization->getModulePrefix('blog', $locale);
        $shopSlug = $localization->getModulePrefix('shop', $locale);
        $galPrefix = $localization->getModulePrefix('gallery', $locale);

        $output = "# {$siteName} - {$market->name} ({$locale})\n\n";
        $output .= "> {$siteDesc}\n\n";
        $output .= "## Overview & Main Hubs\n";
        $output .= "- [Homepage]({$base}): Portal principal para la región [{$market->code}].\n";
        if ($this->isModuleActive('blog')) {
            $output .= "- [Blog & Technical Articles]({$base}/{$blogSlug}): Artículos técnicos, novedades y arquitectura.\n";
        }
        if ($this->isModuleActive('shop')) {
            $output .= "- [Products & Shop Catalog]({$base}/{$shopSlug}): Catálogo público de productos.\n";
        }
        if ($this->isModuleActive('gallery')) {
            $output .= "- [Media Galleries]({$base}/{$galPrefix}): Archivo visual y galerías de proyectos.\n";
        }
        if ($this->isModuleActive('search') && (bool) module_setting('search', 'is_indexable', false)) {
            $searchSlug = $localization->getModulePrefix('search', $locale);
            $output .= "- [General Search]({$base}/{$searchSlug}): Buscador de todo el contenido del sitio.\n";
        }
        $output .= "- [Full Semantic Corpus]({$base}/llms-full.txt): Base de conocimiento completa para ingesta de LLMs.\n";
        $output .= "- [Sitemap XML]({$base}/sitemap.xml): Mapa del sitio indexable para rastreadores.\n\n";

        $output .= $this->scopedIndexPages($market, $locale, $base);
        $output .= $this->scopedIndexCategories($market, $locale, $base, $blogSlug, $shopSlug);
        $output .= $this->scopedIndexFilterLandings($market, $locale, $base);
        $output .= $this->scopedIndexPosts($market, $locale, $base, $blogSlug);
        $output .= $this->scopedIndexProducts($market, $locale, $base, $shopSlug);
        $output .= $this->scopedIndexGalleries($market, $locale, $base, $galPrefix);
        $output .= app(PublicSemanticRegistry::class)->index($market, $locale);

        return $output;
    }

    /**
     * Curated (indexable) filter landings: the navigable entry points into the
     * dynamic filter space, so an AI engine can traverse the catalogue instead
     * of guessing query strings.
     */
    private function scopedIndexFilterLandings(Market $market, string $locale, string $base): string
    {
        try {
            $rows = FilterPage::query()
                ->where('is_indexable', true)
                ->where('locale', $locale)
                ->where(function ($query) use ($market): void {
                    $query->where('market_code', $market->code)->orWhereNull('market_code');
                })
                ->orderBy('module')
                ->get();

            if ($rows->isEmpty()) {
                return '';
            }

            $resolver = app(FilterPathResolver::class);
            $localization = app(LocalizationServiceInterface::class);

            $output = "## Curated Filters & Collections\n";

            foreach ($rows as $row) {
                $resolved = $resolver->resolve((string) $row->module, (string) $market->code, $locale, (string) ($row->path ?? ''));

                // Same quality gate as the runtime (below it the landing is noindex).
                if ($resolved === null || ! $resolver->meetsQualityGate($resolved, $market, $locale)) {
                    continue;
                }

                $prefix = $localization->getModulePrefix((string) $row->module, $locale);
                $label = (string) ($row->heading ?? $row->title ?? $resolved['canonical']);
                $description = (string) ($row->meta_description ?? '');

                $output .= "- [{$label}]({$base}/{$prefix}/{$resolved['canonical']})"
                    .($description !== '' ? ": {$description}" : '')
                    ."\n";
            }

            return $output."\n";
        } catch (\Throwable) {
            return '';
        }
    }

    private function scopedIndexPages(Market $market, string $locale, string $base): string
    {
        if (! $this->isModuleActive('pages')) {
            return '';
        }

        $pages = app(PublicContentDiscoveryInterface::class)->query('page', $market, $locale, ['translations'])
            ->get();

        if ($pages->isEmpty()) {
            return '';
        }

        $output = "## Published Landing Pages\n";
        foreach ($pages as $page) {
            /** @var Page $page */
            $trans = $page->translations->where('locale', $locale)->first();
            if ($trans && $trans->slug !== 'home') {
                $desc = ! empty($trans->meta_description) ? $trans->meta_description : '';
                $output .= "- [{$trans->title}]({$base}/{$trans->slug}): {$desc}\n";
            }
        }

        return $output."\n";
    }

    private function scopedIndexCategories(Market $market, string $locale, string $base, string $blogSlug, string $shopSlug): string
    {
        try {
            $bCategories = $this->isModuleActive('blog') ? BlogCategory::with('translations')->where('is_active', true)->forMarket($market)->get() : collect();
            $sCategories = $this->isModuleActive('shop') ? ShopCategory::with('translations')->where('is_active', true)->forMarket($market)->get() : collect();

            if ($bCategories->isEmpty() && $sCategories->isEmpty()) {
                return '';
            }

            $output = "## Topic Taxonomies & Categories\n";
            foreach ($bCategories as $bCat) {
                /** @var BlogCategory $bCat */
                $trans = $bCat->translations->where('locale', $locale)->first();
                if ($trans) {
                    $desc = ! empty($trans->description) ? $trans->description : 'Categoría editorial del blog.';
                    $slugPath = method_exists($bCat, 'getSlugPath') ? ($bCat->getSlugPath($locale) ?: (string) $trans->slug) : (string) $trans->slug;
                    $output .= "- [Blog: {$trans->name}]({$base}/{$blogSlug}/{$slugPath}): {$desc}\n";
                }
            }
            foreach ($sCategories as $sCat) {
                /** @var ShopCategory $sCat */
                $trans = $sCat->translations->where('locale', $locale)->first();
                if ($trans) {
                    $desc = ! empty($trans->description) ? $trans->description : 'Categoría de productos del catálogo.';
                    $output .= "- [Catálogo: {$trans->name}]({$base}/{$shopSlug}/{$trans->slug}): {$desc}\n";
                }
            }

            return $output."\n";
        } catch (\Throwable) {
            return '';
        }
    }

    private function scopedIndexPosts(Market $market, string $locale, string $base, string $blogSlug): string
    {
        $posts = app(PublicContentDiscoveryInterface::class)->query('post', $market, $locale, ['translations'])
            ->latest()
            ->take(20)
            ->get();

        if ($posts->isEmpty()) {
            return '';
        }

        $output = "## Technical Articles & Blog Posts\n";
        foreach ($posts as $post) {
            /** @var Post $post */
            $trans = $post->translations->where('locale', $locale)->first();
            if ($trans) {
                $summary = ! empty($trans->summary) ? $trans->summary : 'Artículo técnico publicado en el blog.';
                $output .= "- [{$trans->title}]({$base}/{$blogSlug}/{$trans->slug}): {$summary}\n";
            }
        }

        return $output."\n";
    }

    private function scopedIndexProducts(Market $market, string $locale, string $base, string $shopSlug): string
    {
        try {
            $products = app(PublicContentDiscoveryInterface::class)->query('product', $market, $locale, ['translations'])
                ->take(20)
                ->get();

            if ($products->isEmpty()) {
                return '';
            }

            $output = "## Products Catalog\n";
            foreach ($products as $product) {
                /** @var Product $product */
                $trans = $product->translations->where('locale', $locale)->first();
                if ($trans) {
                    $desc = ! empty($trans->description) ? strip_tags($trans->description) : 'Producto de catálogo.';
                    $price = app(ShopDomainService::class)->shouldShowPrices() ? " | Precio: {$product->getPriceForMarket($market)} {$market->currency}" : '';
                    $output .= "- [{$trans->name}]({$base}/{$shopSlug}/{$trans->slug}): SKU: `{$product->sku}`{$price} | {$desc}\n";
                }
            }

            return $output."\n";
        } catch (\Throwable) {
            return '';
        }
    }

    private function scopedIndexGalleries(Market $market, string $locale, string $base, string $galPrefix): string
    {
        try {
            $galleries = app(PublicContentDiscoveryInterface::class)->query('gallery', $market, $locale, ['translations', 'items'])
                ->get();

            if ($galleries->isEmpty()) {
                return '';
            }

            $output = "## Media Albums & Galleries\n";
            foreach ($galleries as $gallery) {
                /** @var Gallery $gallery */
                $trans = $gallery->translations->where('locale', $locale)->first();
                if ($trans) {
                    $desc = ! empty($trans->description) ? $trans->description : 'Galería multimedia interactiva.';
                    $output .= "- [{$trans->title}]({$base}/{$galPrefix}/{$trans->slug}): {$desc} ({$gallery->items->count()} elementos)\n";
                }
            }

            return $output."\n";
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * Global root index (/llms.txt).
     */
    private function globalIndex(string $siteName, string $siteDesc): string
    {
        $appBaseUrl = rtrim((string) Config::get('app.url', 'http://localhost'), '/');

        $output = "# {$siteName}\n\n";
        $output .= "> {$siteDesc}\n\n";
        $output .= "## Regional Knowledge Bases (Multi-Market)\n";

        $activeMarkets = app(PublicContentDiscoveryInterface::class)->markets();
        foreach ($activeMarkets as $market) {
            foreach ($market->languages as $language) {
                $regionalUrl = app(LocalizationServiceInterface::class)->url('/llms.txt', $market, $language);
                $output .= "- [{$market->name} - {$language->name} ({$market->code}/{$language->code})]({$regionalUrl}): Índice GEO especializado para {$market->code}/{$language->code}.\n";
            }
        }
        $output .= "\n";

        $output .= "## Overview & API Surface\n";
        $output .= "- [Full Knowledge Base]({$appBaseUrl}/llms-full.txt): Comprehensive semantic index for LLM ingestion.\n";
        $output .= "- [Sitemap Index]({$appBaseUrl}/sitemap.xml): Complete search engine sitemap.\n";
        $output .= "- [API Schema]({$appBaseUrl}/api/v1/geo/metadata): Structured JSON-LD metadata graph.\n\n";

        return $output;
    }

    /**
     * {@inheritdoc}
     */
    public function generateLlmsFullTxt(?Market $market = null, ?string $locale = null): string
    {
        $output = $this->generateLlmsTxt($market, $locale)."\n\n---\n\n# Full Semantic Corpus\n\n";

        $contexts = $market !== null && ! empty($locale)
            ? [[$market, $locale]]
            : app(PublicContentDiscoveryInterface::class)->markets()->flatMap(fn (Market $active): array => $active->languages->map(fn ($language): array => [$active, $language->code])->all())->all();
        foreach ($contexts as [$contextMarket, $contextLocale]) {
            $output .= "## Market {$contextMarket->code} — {$contextLocale}\n\n";
            $output .= $this->scopedCorpusPages($contextMarket, $contextLocale);
            $output .= $this->scopedCorpusPosts($contextMarket, $contextLocale);
            $output .= $this->scopedCorpusProducts($contextMarket, $contextLocale);
            $output .= $this->scopedCorpusGalleries($contextMarket, $contextLocale);
            $output .= app(PublicSemanticRegistry::class)->corpus($contextMarket, $contextLocale);
        }

        return $output;
    }

    private function scopedCorpusPages(Market $market, string $locale): string
    {
        $output = '';
        $pages = app(PublicContentDiscoveryInterface::class)->query('page', $market, $locale, ['publishedSections.translations'])->get();

        foreach ($pages as $page) {
            /** @var Page $page */
            $trans = $page->translations->where('locale', $locale)->first();
            if (! $trans) {
                continue;
            }

            $output .= "## Page: {$trans->title}\n";
            $output .= '**Source**: '.app(PublicContentDiscoveryInterface::class)->url('page', $market, $locale, (string) $trans->slug)."\n\n";
            if (! empty($trans->meta_description)) {
                $output .= "### Meta Description\n{$trans->meta_description}\n\n";
            }

            $output .= $this->publishedSectionText($page, $market, $locale);
            $output .= "---\n\n";
        }

        return $output;
    }

    private function scopedCorpusPosts(Market $market, string $locale): string
    {
        $output = '';
        $posts = app(PublicContentDiscoveryInterface::class)->query('post', $market, $locale, ['translations', 'publishedSections.translations'])
            ->get();

        foreach ($posts as $post) {
            /** @var Post $post */
            $trans = $post->translations->where('locale', $locale)->first();
            if (! $trans) {
                continue;
            }

            $output .= "## Article: {$trans->title}\n";
            $output .= '**Source**: '.app(PublicContentDiscoveryInterface::class)->url('post', $market, $locale, (string) $trans->slug)."\n";
            $output .= "**Published**: {$post->published_at?->toIso8601String()}\n\n";
            $output .= "### Summary\n{$trans->summary}\n\n";
            $output .= "### Body\n{$trans->content}\n\n";
            $output .= $this->publishedSectionText($post, $market, $locale);
            $output .= "---\n\n";
        }

        return $output;
    }

    private function scopedCorpusProducts(Market $market, string $locale): string
    {
        try {
            $output = '';
            $products = app(PublicContentDiscoveryInterface::class)->query('product', $market, $locale, ['translations', 'publishedSections.translations'])
                ->get();

            foreach ($products as $product) {
                /** @var Product $product */
                $trans = $product->translations->where('locale', $locale)->first();
                if (! $trans) {
                    continue;
                }

                $output .= "## Product: {$trans->name}\n";
                $output .= '**Source**: '.app(PublicContentDiscoveryInterface::class)->url('product', $market, $locale, (string) $trans->slug)."\n";
                $price = app(ShopDomainService::class)->shouldShowPrices() ? " | **Price**: {$product->getPriceForMarket($market)} {$market->currency}" : '';
                $output .= "**SKU**: `{$product->sku}`{$price}\n\n";
                if (! empty($trans->description)) {
                    $output .= "### Description\n".strip_tags($trans->description)."\n\n";
                }
                if (! empty($trans->features) && is_array($trans->features)) {
                    $output .= "### Features\n";
                    foreach ($trans->features as $feature) {
                        $output .= '- '.strip_tags((string) $feature)."\n";
                    }
                    $output .= "\n";
                }
                $output .= $this->publishedSectionText($product, $market, $locale);
                $output .= "---\n\n";
            }

            return $output;
        } catch (\Throwable) {
            return '';
        }
    }

    private function scopedCorpusGalleries(Market $market, string $locale): string
    {
        $output = '';
        foreach (app(PublicContentDiscoveryInterface::class)->query('gallery', $market, $locale, ['items'])->get() as $gallery) {
            /** @var Gallery $gallery */
            $translation = $gallery->translations->firstWhere('locale', $locale);
            if ($translation === null) {
                continue;
            }
            $output .= "## Gallery: {$translation->title}\n";
            $output .= '**Source**: '.app(PublicContentDiscoveryInterface::class)->url('gallery', $market, $locale, (string) $translation->slug)."\n\n";
            $output .= strip_tags((string) $translation->description)."\n\n";
            foreach ($gallery->items as $item) {
                $output .= '- '.strip_tags((string) ($item->title ?: $item->alt_text)).': '.strip_tags((string) $item->caption)."\n";
            }
            $output .= "\n---\n\n";
        }

        return $output;
    }

    private function publishedSectionText(Model $entity, Market $market, string $locale): string
    {
        return app(PublishedSectionTextInterface::class)->text($entity, $market, $locale);
    }

    /**
     * {@inheritdoc}
     */
    public function chunkContentForEmbeddings(string $content, int $chunkSize = 512): array
    {
        return $this->chunker->chunk($content, $chunkSize);
    }

    /**
     * Safely checks if a module is currently active.
     */
    protected function isModuleActive(string $identifier): bool
    {
        try {
            return app(ModuleManager::class)->isModuleEnabled($identifier);
        } catch (\Throwable) {
            return false;
        }
    }
}
