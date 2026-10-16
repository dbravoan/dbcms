<?php

declare(strict_types=1);

namespace Modules\Gallery\Services;

use App\Contracts\Services\LocalizationServiceInterface;
use App\Contracts\Services\PublicContentDiscoveryInterface;
use App\DTOs\Front\FrontPageData;
use App\Services\Cms\FrontViewResolver;
use App\Services\Seo\PublicContentModifiedService;
use App\Services\Seo\SeoMetaService;
use Modules\Gallery\Contracts\GalleryFrontPageServiceInterface;
use Modules\Gallery\DTOs\GalleryFrontInput;
use Modules\Gallery\Models\Gallery;

final class GalleryFrontPageService implements GalleryFrontPageServiceInterface
{
    public function __construct(
        private readonly FrontViewResolver $views,
        private readonly LocalizationServiceInterface $localization,
        private readonly PublicContentDiscoveryInterface $discovery,
    ) {}

    public function index(GalleryFrontInput $input): FrontPageData
    {
        $market = $input->market;
        $locale = $input->locale;
        $localization = $this->localization;

        $galleries = $this->discovery->query('gallery', $market, $locale, ['items'])
            ->latest()
            ->paginate(12, ['*'], 'page', max(1, $input->page))
            ->withPath($input->absoluteUrl);

        $galPrefix = $localization->getModulePrefix('gallery', $locale);
        $alternateUrls = $localization->buildAlternateUrlsMapForPath(explode('/', trim((string) parse_url($input->absoluteUrl, PHP_URL_PATH), '/')));

        $breadcrumbs = [
            ['title' => $locale === 'es' ? 'Inicio' : ($locale === 'ca' ? 'Inici' : 'Home'), 'url' => $localization->url('/', $market)],
            ['title' => $locale === 'es' ? 'Galería' : ($locale === 'ca' ? 'Galeria' : 'Gallery'), 'url' => $localization->url("/{$galPrefix}", $market)],
        ];

        $viewName = $this->views->resolve('gallery.index', 'gallery::index');

        return FrontPageData::view($viewName, [
            'galleries' => $galleries,
            'galPrefix' => $galPrefix,
            'breadcrumbs' => $breadcrumbs,
            'alternateUrls' => $alternateUrls,
            'currentMarket' => $market,
        ]);
    }

    public function show(GalleryFrontInput $input): FrontPageData
    {
        $market = $input->market;
        $locale = $input->locale;
        $localization = $this->localization;
        $slug = $input->slug;

        /** @var Gallery $gallery */
        $gallery = $this->discovery->query('gallery', $market, $locale, ['items'])
            ->whereHas('translations', fn ($translation) => $translation->where('slug', $slug)->where('locale', $locale))
            ->first();
        if (! $gallery instanceof Gallery) {
            return FrontPageData::missing();
        }

        $translation = $gallery->translations->where('locale', $locale)->first();
        if ($translation === null) {
            return FrontPageData::missing();
        }

        // Build localized alternate hreflang tags & switcher map
        $slugsMap = [];
        foreach ($gallery->translations as $t) {
            $slugsMap[$t->locale] = $t->slug;
        }

        $galPrefix = $localization->getModulePrefix('gallery', $locale);
        $hreflangs = $localization->buildHreflangCluster('{galleryPrefix}/{slug}', $slugsMap, $gallery);
        $alternateUrls = $localization->buildAlternateUrlsMapForPath(explode('/', trim((string) parse_url($input->absoluteUrl, PHP_URL_PATH), '/')), $slugsMap);

        $breadcrumbs = [
            ['title' => $locale === 'es' ? 'Inicio' : ($locale === 'ca' ? 'Inici' : 'Home'), 'url' => $localization->url('/', $market)],
            ['title' => $locale === 'es' ? 'Galería' : ($locale === 'ca' ? 'Galeria' : 'Gallery'), 'url' => $localization->url("/{$galPrefix}", $market)],
            ['title' => $translation->title ?? $gallery->title ?? 'Álbum', 'url' => $localization->url("/{$galPrefix}/{$translation?->slug}", $market)],
        ];

        $viewName = $this->views->resolve('gallery.show', 'gallery::show');
        $seoMeta = app(SeoMetaService::class)->forWebPage(
            title: (string) ($translation->meta_title ?: $translation->title),
            description: (string) ($translation->meta_description ?: $translation->description),
            canonical: $input->absoluteUrl,
            locale: $locale,
            entitySchema: app(GallerySeoSchemaService::class)->entity($gallery, $translation),
            modifiedAt: app(PublicContentModifiedService::class)->forEntity($gallery),
            breadcrumbs: array_map(static fn (array $crumb): array => ['name' => $crumb['title'], 'url' => $crumb['url']], $breadcrumbs),
            alternates: array_map(static fn (string $tag, string $href): array => ['hreflang' => $tag, 'href' => $href], array_keys($hreflangs), array_values($hreflangs)),
        );

        return FrontPageData::view($viewName, [
            'seoMeta' => $seoMeta,
            'gallery' => $gallery,
            'translation' => $translation,
            'items' => $gallery->items,
            'breadcrumbs' => $breadcrumbs,
            'hreflangs' => $hreflangs,
            'alternateUrls' => $alternateUrls,
            'currentMarket' => $market,
        ]);
    }
}
