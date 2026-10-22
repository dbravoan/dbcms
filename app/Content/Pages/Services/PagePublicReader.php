<?php

declare(strict_types=1);

namespace App\Content\Pages\Services;

use App\Content\Pages\Contracts\PageDomainServiceInterface;
use App\Content\Pages\Contracts\PagePublicReaderInterface;
use App\Contracts\Services\LocalizationServiceInterface;
use App\Core\Modules\ModuleManager;
use App\Core\Sections\SectionRendererService;
use App\DTOs\Front\FrontPageData;
use App\DTOs\Front\PublicContentContext;
use App\Models\Market;
use App\Services\Cms\FrontViewResolver;
use App\Services\Seo\PublicContentModifiedService;
use App\Services\Seo\SeoMetaService;

final class PagePublicReader implements PagePublicReaderInterface
{
    public function __construct(private readonly FrontViewResolver $views, private readonly PageDomainServiceInterface $pages, private readonly SectionRendererService $renderer, private readonly LocalizationServiceInterface $localization) {}

    public function show(PublicContentContext $context): FrontPageData
    {
        $market = $context->market;
        $locale = $context->locale;
        $slug = $context->slug;
        $pageService = $this->pages;
        $renderer = $this->renderer;
        $localization = $this->localization;
        $targetSlug = ! empty($slug) ? $slug : 'home';

        $moduleManager = app(ModuleManager::class);
        $page = $pageService->resolvePageBySlug($targetSlug, $market, $locale);

        if (! $page && empty($slug)) {
            // Fallback try with localized aliases like 'inicio'
            $page = $pageService->resolvePageBySlug('inicio', $market, $locale);
        }

        if (! $page) {
            if (empty($slug)) {
                return FrontPageData::view('welcome-cms', [
                    'activeMarkets' => Market::where('is_active', true)->get(),
                    'installedModules' => $moduleManager->getActiveModules(),
                ]);
            }

            return FrontPageData::missing();
        }

        $translation = $page->translations->where('locale', $locale)->first();
        if ($translation === null) {
            return FrontPageData::missing();
        }

        // Render each published section sequentially
        $page->load('publishedSections.translations');
        $renderedSections = [];
        foreach ($page->publishedSections as $section) {
            $renderedSections[] = $renderer->renderSection($section, $market, $locale);
        }

        // Build localized alternate hreflang tags & switcher map
        $slugsMap = [];
        foreach ($page->translations as $t) {
            $slugsMap[$t->locale] = $t->slug;
        }

        $hreflangs = $localization->buildHreflangCluster($targetSlug === 'home' ? '' : '{slug}', $slugsMap, $page);
        $alternateUrls = $localization->buildAlternateUrlsMapForPath($context->segments, $slugsMap);

        $breadcrumbs = [
            ['title' => $locale === 'es' ? 'Inicio' : ($locale === 'ca' ? 'Inici' : 'Home'), 'url' => $localization->url('/', $market)],
        ];

        if ($targetSlug !== 'home' && $targetSlug !== 'inicio') {
            $breadcrumbs[] = [
                'title' => $translation->title ?? 'Página',
                'url' => $localization->url("/{$translation?->slug}", $market),
            ];
        }

        $viewName = $this->views->resolve('pages.show', 'pages::show');

        // Unified SEO/GEO metadata (canonical, OG, Twitter, hreflang, JSON-LD).
        /** @var SeoMetaService $seoMetaService */
        $seoMetaService = app(SeoMetaService::class);
        $seoMeta = $seoMetaService->forWebPage(
            title: (string) ($translation?->meta_title ?: $translation?->title ?: ''),
            description: (string) ($translation?->meta_description ?: ''),
            canonical: $context->currentUrl,
            modifiedAt: app(PublicContentModifiedService::class)->forEntity($page),
            alternates: array_map(static fn (string $tag, string $href): array => ['hreflang' => $tag, 'href' => $href], array_keys($hreflangs), array_values($hreflangs)),
            breadcrumbs: array_map(
                static fn (array $crumb): array => ['name' => $crumb['title'], 'url' => $crumb['url']],
                $breadcrumbs
            ),
        );

        return FrontPageData::view($viewName, [
            'page' => $page,
            'translation' => $translation,
            'renderedSections' => $renderedSections,
            'hreflangs' => $hreflangs,
            'seoMeta' => $seoMeta,
            'alternateUrls' => $alternateUrls,
            'breadcrumbs' => $breadcrumbs,
            'currentMarket' => $market,
        ]);
    }
}
