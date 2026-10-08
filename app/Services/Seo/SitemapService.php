<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Contracts\Services\LocalizationServiceInterface;
use App\Contracts\Services\PublicContentDiscoveryInterface;
use App\Contracts\Services\SeoServiceInterface;
use App\Core\Filters\FilterAlternateUrls;
use App\Core\Filters\FilterPathResolver;
use App\Core\Modules\ModuleManager;
use App\DTOs\Seo\SeoMetadataDTO;
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

class SitemapService implements SeoServiceInterface
{
    public function __construct(
        protected JsonLdSchemaService $jsonLd,
        protected OpenGraphService $openGraph
    ) {}

    /**
     * {@inheritdoc}
     */
    public function generateSiteSchema(): array
    {
        return $this->jsonLd->generateSiteGraph();
    }

    /**
     * {@inheritdoc}
     */
    /** @return array<string, mixed> */
    public function resolveMetadata(SeoMetadataDTO $dto): array
    {
        return $this->openGraph->resolveMetaTags($dto);
    }

    /**
     * Helper to safely escape XML URLs.
     */
    protected function xmlUrl(string $url): string
    {
        return htmlspecialchars($url, ENT_XML1, 'UTF-8');
    }

    /**
     * {@inheritdoc}
     */
    public function generateSitemapXml(?Market $market = null, ?string $locale = null, ?int $part = null): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">'."\n";

        $xml .= ($market !== null && ! empty($locale))
            ? $this->buildScopedSitemap($market, $locale)
            : $this->buildGlobalSitemap();

        $xml .= '</urlset>';

        preg_match_all('/<url>.*?<\/url>\s*/s', $xml, $matches);
        $documents = app(SitemapDocumentPartitioner::class)->partition(
            $matches[0],
            (int) config('seo.sitemap.max_urls', SitemapDocumentPartitioner::MAX_URLS),
            (int) config('seo.sitemap.max_bytes', SitemapDocumentPartitioner::MAX_BYTES)
        );
        if ($part !== null) {
            return $documents[$part - 1] ?? '';
        }
        if (count($documents) === 1) {
            return $documents[0];
        }
        $base = $market !== null && $locale !== null
            ? app(LocalizationServiceInterface::class)->url('/', $market, $locale)
            : rtrim((string) config('app.url'), '/');
        $index = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach (array_keys($documents) as $number) {
            $index .= '<sitemap><loc>'.$this->xmlUrl($base.'/sitemap-part-'.($number + 1).'.xml').'</loc></sitemap>';
        }

        return $index.'</sitemapindex>';
    }

    /**
     * Regional sitemap scoped to a single market + locale (e.g. /es/es/sitemap.xml).
     */
    private function buildScopedSitemap(Market $market, string $locale): string
    {
        $base = app(LocalizationServiceInterface::class)->url('/', $market, $locale);
        $localization = app(LocalizationServiceInterface::class);
        $blogSlug = $localization->getModulePrefix('blog', $locale);
        $shopSlug = $localization->getModulePrefix('shop', $locale);
        $galPrefix = $this->galleryPrefix($locale);

        $xml = $this->urlEntry($base, null, 'daily', '1.0');

        if ($this->isModuleActive('blog')) {
            $xml .= $this->urlEntry("{$base}/{$blogSlug}", null, 'daily', '0.9');
        }
        if ($this->isModuleActive('shop')) {
            $xml .= $this->urlEntry("{$base}/{$shopSlug}", null, 'daily', '0.9');
        }
        if ($this->isModuleActive('gallery')) {
            $xml .= $this->urlEntry("{$base}/{$galPrefix}", null, 'weekly', '0.8');
        }
        // The general search is opt-in: it only enters the sitemap when the
        // editor activated it in Filtros y busquedas.
        if ($this->isModuleActive('search') && (bool) module_setting('search', 'is_indexable', false)) {
            $searchSlug = $localization->getModulePrefix('search', $locale);
            $xml .= $this->urlEntry("{$base}/{$searchSlug}", null, 'weekly', '0.5');
        }

        $xml .= $this->scopedPages($market, $locale, $base);
        $xml .= $this->scopedBlog($market, $locale, $base, $blogSlug);
        $xml .= $this->scopedShop($market, $locale, $base, $shopSlug);
        $xml .= $this->scopedGalleries($market, $locale, $base, $galPrefix);
        $xml .= $this->scopedFilterPages($market, $locale, $base);

        return $xml;
    }

    private function scopedPages(Market $market, string $locale, string $base): string
    {
        if (! $this->isModuleActive('pages')) {
            return '';
        }

        $xml = '';
        $pages = app(PublicContentDiscoveryInterface::class)->query('page', $market, $locale, ['translations', 'markets'])
            ->get();

        foreach ($pages as $page) {
            /** @var Page $page */
            $trans = $page->translations->where('locale', $locale)->first();
            if ($trans === null || $trans->slug === 'home') {
                continue;
            }

            $xml .= $this->urlEntry(
                "{$base}/{$trans->slug}",
                app(PublicContentModifiedService::class)->forEntity($page)?->toIso8601String(),
                'weekly',
                '0.8',
                $this->alternateLinks($page->translations, $market->code, fn (string $loc, string $slug): string => url("/{$market->code}/{$loc}/{$slug}"))
            );
        }

        return $xml;
    }

    private function scopedBlog(Market $market, string $locale, string $base, string $blogSlug): string
    {
        if (! $this->isModuleActive('blog')) {
            return '';
        }

        $xml = '';
        $posts = app(PublicContentDiscoveryInterface::class)->query('post', $market, $locale, ['translations', 'markets'])
            ->latest()
            ->get();

        foreach ($posts as $post) {
            /** @var Post $post */
            $trans = $post->translations->where('locale', $locale)->first();
            if ($trans === null) {
                continue;
            }

            $xml .= $this->urlEntry(
                "{$base}/{$blogSlug}/{$trans->slug}",
                app(PublicContentModifiedService::class)->forEntity($post)?->toIso8601String(),
                'weekly',
                '0.8',
                $this->alternateLinks($post->translations, $market->code, function (string $loc, string $slug) use ($market): string {
                    $altBlogSlug = app(LocalizationServiceInterface::class)->getModulePrefix('blog', $loc);

                    return url("/{$market->code}/{$loc}/{$altBlogSlug}/{$slug}");
                })
            );
        }

        $xml .= $this->scopedBlogCategories($market, $locale, $base, $blogSlug);

        return $xml;
    }

    private function scopedBlogCategories(Market $market, string $locale, string $base, string $blogSlug): string
    {
        try {
            $xml = '';
            $categories = BlogCategory::with('translations')->where('is_active', true)->forMarket($market)->get();

            foreach ($categories as $category) {
                $trans = $category->translations->where('locale', $locale)->first();
                if ($trans === null) {
                    continue;
                }

                $slugPath = $category->getSlugPath($locale) ?: (string) $trans->slug;

                $xml .= $this->urlEntry(
                    "{$base}/{$blogSlug}/{$slugPath}",
                    app(PublicContentModifiedService::class)->forEntity($category)?->toIso8601String(),
                    'weekly',
                    '0.7',
                    $this->alternateLinks($category->translations, $market->code, function (string $loc, string $slug) use ($market, $category): string {
                        $altBlogSlug = app(LocalizationServiceInterface::class)->getModulePrefix('blog', $loc);
                        $altPath = $category->getSlugPath($loc) ?: $slug;

                        return url("/{$market->code}/{$loc}/{$altBlogSlug}/{$altPath}");
                    })
                );
            }

            return $xml;
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * Curated filter/search landings that passed the quality gate: they are the
     * only dynamic filter URLs published to crawlers (everything else stays
     * accessible but `noindex`).
     */
    private function scopedFilterPages(Market $market, string $locale, string $base): string
    {
        try {
            $rows = FilterPage::query()
                ->where('is_indexable', true)
                ->where('locale', $locale)
                ->where(function ($query) use ($market): void {
                    $query->where('market_code', $market->code)->orWhereNull('market_code');
                })
                ->get();

            if ($rows->isEmpty()) {
                return '';
            }

            $resolver = app(FilterPathResolver::class);
            $localization = app(LocalizationServiceInterface::class);
            $alternates = app(FilterAlternateUrls::class);

            $xml = '';

            foreach ($rows as $row) {
                $resolved = $resolver->resolve((string) $row->module, (string) $market->code, $locale, (string) ($row->path ?? ''));

                // A curated landing that does not pass the quality gate stays
                // noindex at runtime, so it must not be published here either.
                if ($resolved === null || ! $resolver->meetsQualityGate($resolved, $market, $locale)) {
                    continue;
                }

                $prefix = $localization->getModulePrefix((string) $row->module, $locale);
                $lastMod = $row->updated_at?->toIso8601String();

                $hreflangs = [];

                foreach ($alternates->build(
                    $resolved['set'],
                    $resolved['module']->filterDimensions(),
                    $resolved['module'],
                    $market,
                    $localization
                ) as $alternate) {
                    $hreflangs[$alternate['hreflang']] = $alternate['href'];
                }

                $xml .= $this->urlEntry(
                    "{$base}/{$prefix}/{$resolved['canonical']}",
                    $lastMod,
                    'weekly',
                    '0.6',
                    $hreflangs
                );
            }

            return $xml;
        } catch (\Throwable) {
            return '';
        }
    }

    private function scopedShop(Market $market, string $locale, string $base, string $shopSlug): string
    {
        if (! $this->isModuleActive('shop')) {
            return '';
        }

        $xml = '';

        try {
            $products = app(PublicContentDiscoveryInterface::class)->query('product', $market, $locale, ['translations', 'markets'])
                ->get();

            foreach ($products as $product) {
                /** @var Product $product */
                $trans = $product->translations->where('locale', $locale)->first();
                if ($trans === null) {
                    continue;
                }

                $xml .= $this->urlEntry(
                    "{$base}/{$shopSlug}/{$trans->slug}",
                    app(PublicContentModifiedService::class)->forEntity($product)?->toIso8601String(),
                    'weekly',
                    '0.8',
                    $this->alternateLinks($product->translations, $market->code, function (string $loc, string $slug) use ($market): string {
                        $altShopSlug = app(LocalizationServiceInterface::class)->getModulePrefix('shop', $loc);

                        return url("/{$market->code}/{$loc}/{$altShopSlug}/{$slug}");
                    })
                );
            }
        } catch (\Throwable) {
            // Ignore if Shop module table is missing
        }

        try {
            $categories = ShopCategory::with('translations')
                ->where('is_active', true)
                ->forMarket($market)
                ->orderBy('order', 'asc')
                ->get();

            foreach ($categories as $category) {
                $trans = $category->translations->where('locale', $locale)->first();
                if ($trans === null) {
                    continue;
                }

                $xml .= $this->urlEntry(
                    "{$base}/{$shopSlug}/{$trans->slug}",
                    app(PublicContentModifiedService::class)->forEntity($category)?->toIso8601String(),
                    'weekly',
                    '0.7',
                    $this->alternateLinks($category->translations, $market->code, function (string $loc, string $slug) use ($market): string {
                        $altShopSlug = app(LocalizationServiceInterface::class)->getModulePrefix('shop', $loc);

                        return url("/{$market->code}/{$loc}/{$altShopSlug}/{$slug}");
                    })
                );
            }
        } catch (\Throwable) {
            // Ignore if Shop module table is missing
        }

        return $xml;
    }

    private function scopedGalleries(Market $market, string $locale, string $base, string $galPrefix): string
    {
        if (! $this->isModuleActive('gallery')) {
            return '';
        }

        try {
            $xml = '';
            $galleries = app(PublicContentDiscoveryInterface::class)->query('gallery', $market, $locale, ['translations', 'markets'])
                ->get();

            foreach ($galleries as $gallery) {
                /** @var Gallery $gallery */
                $trans = $gallery->translations->where('locale', $locale)->first();
                if ($trans === null) {
                    continue;
                }

                $xml .= $this->urlEntry(
                    "{$base}/{$galPrefix}/{$trans->slug}",
                    app(PublicContentModifiedService::class)->forEntity($gallery)?->toIso8601String(),
                    'monthly',
                    '0.7',
                    $this->alternateLinks($gallery->translations, $market->code, fn (string $loc, string $slug): string => url("/{$market->code}/{$loc}/{$this->galleryPrefix($loc)}/{$slug}"))
                );
            }

            return $xml;
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * Global root sitemap across every active market + language (/sitemap.xml).
     */
    private function buildGlobalSitemap(): string
    {
        $appBaseUrl = rtrim((string) Config::get('app.url', 'http://localhost'), '/');
        $localization = app(LocalizationServiceInterface::class);

        $xml = $this->urlEntry($appBaseUrl, null, 'daily', '1.0');

        $markets = app(PublicContentDiscoveryInterface::class)->markets();

        foreach ($markets as $market) {
            foreach ($market->languages as $language) {
                $marketUrl = $localization->url('/', $market, $language->code);
                $blogSlug = $localization->getModulePrefix('blog', $language->code);
                $shopSlug = $localization->getModulePrefix('shop', $language->code);
                $galPrefix = $this->galleryPrefix($language->code);

                $xml .= $this->urlEntry($marketUrl, null, 'daily', '0.9');

                if ($this->isModuleActive('blog')) {
                    $xml .= $this->urlEntry("{$marketUrl}/{$blogSlug}", null, 'daily', '0.8');
                }
                if ($this->isModuleActive('shop')) {
                    $xml .= $this->urlEntry("{$marketUrl}/{$shopSlug}", null, 'daily', '0.8');
                }
                if ($this->isModuleActive('gallery')) {
                    $xml .= $this->urlEntry("{$marketUrl}/{$galPrefix}", null, 'weekly', '0.7');
                }
                if ($this->isModuleActive('search') && (bool) module_setting('search', 'is_indexable', false)) {
                    $searchSlug = $localization->getModulePrefix('search', $language->code);
                    $xml .= $this->urlEntry("{$marketUrl}/{$searchSlug}", null, 'weekly', '0.5');
                }

                $xml .= $this->globalPages($market, $language->code, $marketUrl);
                $xml .= $this->globalBlog($market, $language->code, $marketUrl, $blogSlug);
                $xml .= $this->globalShop($market, $language->code, $marketUrl, $shopSlug);
                $xml .= $this->globalGalleries($market, $language->code, $marketUrl, $galPrefix);
                $xml .= $this->globalFilterPages($market, $language->code, $marketUrl);
            }
        }

        return $xml;
    }

    /**
     * Curated filter landings for the aggregate sitemap (same quality gate as
     * the per-market sitemap).
     */
    private function globalFilterPages(Market $market, string $locale, string $marketUrl): string
    {
        try {
            $rows = FilterPage::query()
                ->where('is_indexable', true)
                ->where('locale', $locale)
                ->where(function ($query) use ($market): void {
                    $query->where('market_code', $market->code)->orWhereNull('market_code');
                })
                ->get();

            if ($rows->isEmpty()) {
                return '';
            }

            $resolver = app(FilterPathResolver::class);
            $localization = app(LocalizationServiceInterface::class);

            $xml = '';

            foreach ($rows as $row) {
                $resolved = $resolver->resolve((string) $row->module, (string) $market->code, $locale, (string) ($row->path ?? ''));

                if ($resolved === null || ! $resolver->meetsQualityGate($resolved, $market, $locale)) {
                    continue;
                }

                $prefix = $localization->getModulePrefix((string) $row->module, $locale);

                $xml .= $this->urlEntry(
                    "{$marketUrl}/{$prefix}/{$resolved['canonical']}",
                    $row->updated_at?->toIso8601String(),
                    'weekly',
                    '0.6'
                );
            }

            return $xml;
        } catch (\Throwable) {
            return '';
        }
    }

    private function globalPages(Market $market, string $locale, string $marketUrl): string
    {
        if (! $this->isModuleActive('pages')) {
            return '';
        }

        $xml = '';
        $pages = app(PublicContentDiscoveryInterface::class)->query('page', $market, $locale, ['translations'])
            ->get();

        foreach ($pages as $page) {
            /** @var Page $page */
            $trans = $page->translations->where('locale', $locale)->first();
            if ($trans === null || $trans->slug === 'home') {
                continue;
            }

            $xml .= $this->urlEntry(
                "{$marketUrl}/{$trans->slug}",
                app(PublicContentModifiedService::class)->forEntity($page)?->toIso8601String(),
                'weekly',
                '0.8',
                $this->alternateLinks($page->translations, $market->code, fn (string $loc, string $slug): string => url("/{$market->code}/{$loc}/{$slug}"))
            );
        }

        return $xml;
    }

    private function globalBlog(Market $market, string $locale, string $marketUrl, string $blogSlug): string
    {
        if (! $this->isModuleActive('blog')) {
            return '';
        }

        $xml = '';
        $posts = app(PublicContentDiscoveryInterface::class)->query('post', $market, $locale, ['translations'])
            ->get();

        foreach ($posts as $post) {
            /** @var Post $post */
            $trans = $post->translations->where('locale', $locale)->first();
            if ($trans === null) {
                continue;
            }

            $xml .= $this->urlEntry(
                "{$marketUrl}/{$blogSlug}/{$trans->slug}",
                app(PublicContentModifiedService::class)->forEntity($post)?->toIso8601String(),
                'weekly',
                '0.8',
                $this->alternateLinks($post->translations, $market->code, function (string $loc, string $slug) use ($market): string {
                    $altBlogSlug = app(LocalizationServiceInterface::class)->getModulePrefix('blog', $loc);

                    return url("/{$market->code}/{$loc}/{$altBlogSlug}/{$slug}");
                })
            );
        }

        try {
            $categories = BlogCategory::with('translations')->where('is_active', true)->forMarket($market)->get();
            foreach ($categories as $category) {
                $trans = $category->translations->where('locale', $locale)->first();
                if ($trans === null) {
                    continue;
                }

                $xml .= $this->urlEntry(
                    "{$marketUrl}/{$blogSlug}/{$category->getSlugPath($locale)}",
                    app(PublicContentModifiedService::class)->forEntity($category)?->toIso8601String(),
                    'weekly',
                    '0.7',
                    $this->alternateLinks($category->translations, $market->code, function (string $loc, string $slug) use ($market): string {
                        $altBlogSlug = app(LocalizationServiceInterface::class)->getModulePrefix('blog', $loc);

                        return url("/{$market->code}/{$loc}/{$altBlogSlug}/{$slug}");
                    })
                );
            }
        } catch (\Throwable) {
            // Ignore if missing
        }

        return $xml;
    }

    private function globalShop(Market $market, string $locale, string $marketUrl, string $shopSlug): string
    {
        if (! $this->isModuleActive('shop')) {
            return '';
        }

        $xml = '';

        try {
            $products = app(PublicContentDiscoveryInterface::class)->query('product', $market, $locale, ['translations'])
                ->get();

            foreach ($products as $product) {
                /** @var Product $product */
                $trans = $product->translations->where('locale', $locale)->first();
                if ($trans === null) {
                    continue;
                }

                $xml .= $this->urlEntry(
                    "{$marketUrl}/{$shopSlug}/{$trans->slug}",
                    app(PublicContentModifiedService::class)->forEntity($product)?->toIso8601String(),
                    'weekly',
                    '0.8',
                    $this->alternateLinks($product->translations, $market->code, function (string $loc, string $slug) use ($market): string {
                        $altShopSlug = app(LocalizationServiceInterface::class)->getModulePrefix('shop', $loc);

                        return url("/{$market->code}/{$loc}/{$altShopSlug}/{$slug}");
                    })
                );
            }
        } catch (\Throwable) {
            // Ignore if missing
        }

        try {
            $categories = ShopCategory::with('translations')->where('is_active', true)->forMarket($market)->get();
            foreach ($categories as $category) {
                $trans = $category->translations->where('locale', $locale)->first();
                if ($trans === null) {
                    continue;
                }

                $xml .= $this->urlEntry(
                    "{$marketUrl}/{$shopSlug}/{$trans->slug}",
                    app(PublicContentModifiedService::class)->forEntity($category)?->toIso8601String(),
                    'weekly',
                    '0.7',
                    $this->alternateLinks($category->translations, $market->code, function (string $loc, string $slug) use ($market): string {
                        $altShopSlug = app(LocalizationServiceInterface::class)->getModulePrefix('shop', $loc);

                        return url("/{$market->code}/{$loc}/{$altShopSlug}/{$slug}");
                    })
                );
            }
        } catch (\Throwable) {
            // Ignore if missing
        }

        return $xml;
    }

    private function globalGalleries(Market $market, string $locale, string $marketUrl, string $galPrefix): string
    {
        if (! $this->isModuleActive('gallery')) {
            return '';
        }

        try {
            $xml = '';
            $galleries = app(PublicContentDiscoveryInterface::class)->query('gallery', $market, $locale, ['translations'])
                ->get();

            foreach ($galleries as $gallery) {
                /** @var Gallery $gallery */
                $trans = $gallery->translations->where('locale', $locale)->first();
                if ($trans === null) {
                    continue;
                }

                $xml .= $this->urlEntry(
                    "{$marketUrl}/{$galPrefix}/{$trans->slug}",
                    app(PublicContentModifiedService::class)->forEntity($gallery)?->toIso8601String(),
                    'monthly',
                    '0.7',
                    $this->alternateLinks($gallery->translations, $market->code, fn (string $loc, string $slug): string => url("/{$market->code}/{$loc}/{$this->galleryPrefix($loc)}/{$slug}"))
                );
            }

            return $xml;
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * Build a single <url> entry.
     *
     * @param  array<string, string>  $alternates  hreflang => href
     */
    private function urlEntry(string $loc, ?string $lastMod = null, string $changefreq = 'weekly', string $priority = '0.8', array $alternates = []): string
    {
        $entry = "  <url>\n";
        $entry .= '    <loc>'.$this->xmlUrl($loc)."</loc>\n";
        if ($lastMod !== null) {
            $entry .= "    <lastmod>{$lastMod}</lastmod>\n";
        }
        $entry .= "    <changefreq>{$changefreq}</changefreq>\n";
        $entry .= "    <priority>{$priority}</priority>\n";

        foreach ($alternates as $hreflang => $href) {
            $entry .= '    <xhtml:link rel="alternate" hreflang="'.$hreflang.'" href="'.$this->xmlUrl($href)."\" />\n";
        }

        $entry .= "  </url>\n";

        return $entry;
    }

    /**
     * Build the hreflang alternate map for a translation collection.
     *
     * @param  iterable<mixed>  $translations
     * @param  callable(string, string): string  $urlBuilder  (locale, slug) => absolute URL
     * @return array<string, string>
     */
    private function alternateLinks(iterable $translations, string $marketCode, callable $urlBuilder): array
    {
        $links = [];

        foreach ($translations as $translation) {
            if (! $translation instanceof Model) {
                continue;
            }

            $locale = (string) $translation->getAttribute('locale');
            if ($locale === '' || ! Market::where('code', $marketCode)->where('is_active', true)->whereHas('languages', fn ($languages) => $languages->where('code', $locale)->where('is_active', true))->exists() || (string) $translation->getAttribute('slug') === '') {
                continue;
            }

            $market = Market::where('code', $marketCode)->firstOrFail();
            $target = $urlBuilder($locale, (string) $translation->getAttribute('slug'));
            $path = (string) parse_url($target, PHP_URL_PATH);
            $path = preg_replace('#^/'.preg_quote($marketCode, '#').'/'.preg_quote($locale, '#').'(?:/|$)#', '/', $path) ?? $path;
            $links[strtolower($locale).'-'.strtoupper($marketCode)] = app(LocalizationServiceInterface::class)->url($path, $market, $locale);
        }

        return $links;
    }

    /**
     * Localized gallery route prefix (gallery|galeria).
     */
    private function galleryPrefix(string $locale): string
    {
        return app(LocalizationServiceInterface::class)->getModulePrefix('gallery', $locale);
    }

    /**
     * Safely checks if a module is currently active.
     */
    protected function isModuleActive(string $identifier): bool
    {
        try {
            return app(ModuleManager::class)->isActive($identifier);
        } catch (\Throwable) {
            return false;
        }
    }
}
