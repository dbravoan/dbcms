<?php

declare(strict_types=1);

namespace Modules\Blog\Services;

use App\Contracts\Services\LocalizationServiceInterface;
use App\Core\Filters\FilterAlternateUrls;
use App\Core\Filters\FilterIntroRenderer;
use App\Core\Filters\FilterQueryRedirector;
use App\Core\Filters\FilterSeoResolver;
use App\Core\Filters\FilterSet;
use App\Core\Filters\FilterUrlBuilder;
use App\DTOs\Front\FrontPageData;
use App\DTOs\Front\PublicContentContext;
use App\Services\Cms\FrontViewResolver;
use App\Services\Seo\JsonLdSchemaService;
use App\Services\Seo\SeoMetaService;
use Modules\Blog\Contracts\BlogCatalogueReaderInterface;
use Modules\Blog\Contracts\PostDomainServiceInterface;
use Modules\Blog\Filters\BlogFilters;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\Post;

final class BlogCatalogueReader implements BlogCatalogueReaderInterface
{
    public function __construct(private readonly FrontViewResolver $views, private readonly LocalizationServiceInterface $localization, private readonly PostDomainServiceInterface $posts) {}

    public function index(PublicContentContext $context): FrontPageData
    {
        $localization = $this->localization;
        $service = $this->posts;
        $market = $context->market;
        $locale = $context->locale;

        // Filter form submissions (ranges, multi-selects, picks) consolidate into
        // the canonical friendly URL with a 301.
        $formPath = app(FilterQueryRedirector::class)
            ->canonicalPath(app(BlogFilters::class), $market, $locale, $context->query);

        if ($formPath !== null) {
            $blogSlug = $localization->getModulePrefix('blog', $locale);

            return FrontPageData::redirect($localization->url("/{$blogSlug}/{$formPath}", $market), 301);
        }

        $posts = $service->getFeed($market, $locale, 12, $context->page);
        $viewName = $this->views->resolve('blog.index', 'blog::index');

        $categories = Category::with('translations')->where('is_active', true)->forMarket($market)->whereNull('parent_id')->get();
        $blogSlug = $localization->getModulePrefix('blog', $locale);

        $breadcrumbs = [
            ['title' => $locale === 'es' ? 'Inicio' : ($locale === 'ca' ? 'Inici' : 'Home'), 'url' => $localization->url('/', $market)],
            ['title' => 'Blog', 'url' => $localization->url("/{$blogSlug}", $market)],
        ];

        $alternateUrls = $localization->buildAlternateUrlsMapForPath($context->segments);

        return FrontPageData::view($viewName, [
            'posts' => $posts,
            'categories' => $categories,
            'breadcrumbs' => $breadcrumbs,
            'alternateUrls' => $alternateUrls,
            'currentMarket' => $market,
            // An empty filter set lets themes render the facet links (crawlable
            // entry points into the filter space) from the plain index too.
            'filterSet' => new FilterSet('blog', (string) $market->code, $locale, []),
            'filterModule' => app(BlogFilters::class),
        ]);
    }

    public function filtered(PublicContentContext $context, FilterSet $set): FrontPageData
    {
        $filters = app(BlogFilters::class);
        $localization = $this->localization;
        $market = $context->market;
        $locale = $context->locale;
        $blogSlug = $localization->getModulePrefix('blog', $locale);
        $dimensions = $filters->filterDimensions();

        $canonical = (app(FilterUrlBuilder::class))->canonicalPath($set, $dimensions, $locale) ?? '';
        $requested = trim($context->slug, '/');

        // Any non-canonical permutation 301s to the canonical URL.
        if ($canonical !== '' && $canonical !== $requested) {
            return FrontPageData::redirect($localization->url("/{$blogSlug}/{$canonical}", $market), 301);
        }

        // The query lives with the module (single source of truth, reused by the
        // MCP browsing tool); an unresolvable value is a 404 here.
        $posts = $filters->listQuery($set, $market, $locale, 12, $context->page);

        if ($posts === null) {
            return FrontPageData::missing();
        }

        // No results (and not curated) is a 404, never an empty indexable page.
        if ($posts->total() === 0) {
            return FrontPageData::missing();
        }

        $filterSeo = app(FilterSeoResolver::class)->resolve($filters, $set, $dimensions, $posts->total(), (string) $market->code);

        // Curated intro, composed with the CMS block manager.
        $curated = app(FilterSeoResolver::class)->curated($filters, $set, (string) $market->code);
        $filterIntro = app(FilterIntroRenderer::class)->render($curated, $market, $locale);

        $homeLabel = $locale === 'es' ? 'Inicio' : ($locale === 'ca' ? 'Inici' : 'Home');
        $canonicalUrl = $localization->url("/{$blogSlug}/{$canonical}", $market);

        $breadcrumbs = [
            ['title' => $homeLabel, 'url' => $localization->url('/', $market)],
            ['title' => 'Blog', 'url' => $localization->url("/{$blogSlug}", $market)],
            ['title' => $filterSeo->heading, 'url' => $canonicalUrl],
        ];

        // hreflang cluster: the same filters expressed in every active locale.
        $alternates = app(FilterAlternateUrls::class)
            ->build($set, $dimensions, $filters, $market, $localization);

        // CollectionPage + ItemList so search/AI engines read this as a listing.
        $items = [];
        foreach ($posts->items() as $post) {
            $translation = $post->translations->firstWhere('locale', $locale) ?? $post->translations->first();
            $slug = (string) ($translation->slug ?? '');

            if ($slug === '') {
                continue;
            }

            $items[] = [
                'name' => (string) ($translation->title ?? $slug),
                'url' => $localization->url("/{$blogSlug}/{$slug}", $market, $locale),
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
            alternates: array_values($alternates),
            schemaOverride: $schema,
        );

        return FrontPageData::view($this->views->resolve('blog.index', 'blog::index'), [
            'posts' => $posts,
            'categories' => Category::with('translations')->where('is_active', true)->forMarket($market)->whereNull('parent_id')->get(),
            'breadcrumbs' => $breadcrumbs,
            'alternateUrls' => $localization->buildAlternateUrlsMapForPath($context->segments),
            'currentMarket' => $market,
            'seoMeta' => $seoMeta,
            'filterSet' => $set,
            'filterModule' => $filters,
            'filterHeading' => $filterSeo->heading,
            // Front-office bar: one-click "index this filter in Google".
            'filterCurationUrl' => route('admin.seo.filters.index', [
                'module' => 'blog',
                'market_code' => (string) $market->code,
                'locale' => $locale,
                'path' => $canonical,
            ]),
            'filterIsIndexable' => $filterSeo->indexable,
            'filterIntro' => $filterIntro,
        ]);
    }

    public function category(PublicContentContext $context, ?Category $category = null): FrontPageData
    {
        $localization = $this->localization;
        $market = $context->market;
        $locale = $context->locale;
        $slug = $context->slug;
        $blogSlug = $localization->getModulePrefix('blog', $locale);

        /** @var Category $category */
        $category = $category
            ?? Category::resolveBySlugPath($slug, $locale, $market)
            ?? Category::where('is_active', true)->forMarket($market)->whereHas('translations', function ($q) use ($slug, $locale): void {
                $q->where('slug', $slug)->where('locale', $locale);
            })->with(['translations', 'parent.translations'])->first();
        if ($category === null || ! Category::where('is_active', true)->forMarket($market)->whereKey($category->getKey())->exists()) {
            return FrontPageData::missing();
        }

        // One taxonomy page, one URL: the canonical form is the bare entity path
        // (the one the sitemap publishes), so an explicit dimension marker such
        // as `/blog/categoria/x` consolidates into it with a 301.
        $canonicalSlugPath = $category->getSlugPath($locale);
        $requested = trim($slug, '/');

        if ($canonicalSlugPath !== '' && $requested !== $canonicalSlugPath) {
            return FrontPageData::redirect($localization->url("/{$blogSlug}/{$canonicalSlugPath}", $market), 301);
        }

        $posts = Post::published()
            ->forMarket($market)
            ->where('category_id', $category->id)
            ->whereHas('translations', function ($q) use ($locale): void {
                $q->where('locale', $locale);
            })
            ->with(['translations', 'author', 'category' => fn ($query) => $query->where('is_active', true)->forMarket($market)->with('translations')])
            ->paginate(12, ['*'], 'page', $context->page);

        // Build localized alternate hreflang tags for category
        $slugsMap = [];
        foreach ($category->translations as $t) {
            $slugsMap[$t->locale] = $category->getSlugPath($t->locale);
        }
        $hreflangs = $localization->buildHreflangCluster('{blogPrefix}/{slug}', $slugsMap, $category);
        $alternateUrls = $localization->buildAlternateUrlsMapForPath($context->segments, $slugsMap);

        // Build Breadcrumb Trail
        $breadcrumbs = $category->getBreadcrumbTrail($locale, $market);

        $viewName = $this->views->resolve('blog.category', $this->views->resolve('blog.index', 'blog::index'));

        $categories = Category::with('translations')->where('is_active', true)->forMarket($market)->whereNull('parent_id')->get();

        // Taxonomy archives are the indexable core of the blog: they deserve a
        // complete head (canonical + hreflang + CollectionPage JSON-LD).
        $translation = $category->translations->firstWhere('locale', $locale) ?? $category->translations->first();
        $canonicalUrl = $localization->url("/{$blogSlug}/{$canonicalSlugPath}", $market);

        $alternates = [];

        foreach ((array) $hreflangs as $tag => $href) {
            if (is_string($tag) && is_string($href)) {
                $alternates[] = ['hreflang' => $tag, 'href' => $href];
            }
        }

        $items = [];

        foreach ($posts->items() as $post) {
            $postTranslation = $post->translations->firstWhere('locale', $locale) ?? $post->translations->first();
            $postSlug = (string) ($postTranslation->slug ?? '');

            if ($postSlug === '') {
                continue;
            }

            $items[] = [
                'name' => (string) ($postTranslation->title ?? $postSlug),
                'url' => $localization->url("/{$blogSlug}/{$postSlug}", $market, $locale),
            ];
        }

        $seoMeta = app(SeoMetaService::class)->forWebPage(
            title: (string) ($translation->name ?? $category->getKey()),
            description: (string) ($translation->description ?? ''),
            canonical: $canonicalUrl,
            alternates: $alternates,
            schemaOverride: app(JsonLdSchemaService::class)->generateCollectionGraph(
                name: (string) ($translation->name ?? ''),
                description: (string) ($translation->description ?? ''),
                url: $canonicalUrl,
                locale: $locale,
                items: $items,
                breadcrumbs: array_map(
                    static fn (array $crumb): array => ['name' => (string) ($crumb['title'] ?? ''), 'url' => (string) ($crumb['url'] ?? '')],
                    $breadcrumbs
                ),
            ),
        );

        return FrontPageData::view($viewName, [
            'category' => $category,
            'posts' => $posts,
            'categories' => $categories,
            'breadcrumbs' => $breadcrumbs,
            'hreflangs' => $hreflangs,
            'alternateUrls' => $alternateUrls,
            'currentMarket' => $market,
            'seoMeta' => $seoMeta,
            'filterSet' => new FilterSet('blog', (string) $market->code, $locale, ['category' => [$canonicalSlugPath]]),
            'filterModule' => app(BlogFilters::class),
        ]);
    }
}
