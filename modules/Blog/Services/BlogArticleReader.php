<?php

declare(strict_types=1);

namespace Modules\Blog\Services;

use App\Contracts\Services\LocalizationServiceInterface;
use App\DTOs\Front\FrontPageData;
use App\DTOs\Front\PublicContentContext;
use App\Services\Cms\FrontViewResolver;
use App\Services\Seo\SeoMetaService;
use Modules\Blog\Contracts\BlogArticleReaderInterface;
use Modules\Blog\Models\Post;
use Modules\Pages\Services\SectionRendererService;

final class BlogArticleReader implements BlogArticleReaderInterface
{
    public function __construct(private readonly FrontViewResolver $views, private readonly LocalizationServiceInterface $localization) {}

    public function show(PublicContentContext $context): FrontPageData
    {
        $localization = $this->localization;
        $market = $context->market;
        $locale = $context->locale;
        $slug = $context->slug;

        /** @var Post $post */
        $post = Post::published()
            ->forMarket($market)
            ->whereHas('translations', function ($q) use ($slug, $locale): void {
                $q->where('slug', $slug)->where('locale', $locale);
            })
            ->with(['translations', 'publishedSections.translations', 'author', 'category' => fn ($query) => $query->where('is_active', true)->forMarket($market)->with('translations'), 'comments.replies', 'tags'])
            ->first();
        if ($post === null) {
            return FrontPageData::missing();
        }

        $activeTranslation = $post->translations->where('locale', $locale)->first()
            ?? $post->translations->first();
        if ($activeTranslation === null) {
            return FrontPageData::missing();
        }

        // Render modular sections if present
        $renderedSections = [];
        if ($post->publishedSections->isNotEmpty()) {
            $renderer = app(SectionRendererService::class);
            foreach ($post->publishedSections as $section) {
                $renderedSections[] = $renderer->renderSection($section, $market, $locale);
            }
        }

        // Build localized alternate hreflang tags & switcher map
        $slugsMap = [];
        foreach ($post->translations as $t) {
            $slugsMap[$t->locale] = $t->slug;
        }
        $blogSlug = $localization->getModulePrefix('blog', $locale);
        $hreflangs = $localization->buildHreflangCluster('{blogPrefix}/{slug}', $slugsMap, $post);
        $alternateUrls = $localization->buildAlternateUrlsMapForPath($context->segments, $slugsMap);

        // Build Breadcrumb Trail
        $breadcrumbs = [];
        if ($post->category) {
            $breadcrumbs = $post->category->getBreadcrumbTrail($locale, $market);
        } else {
            $breadcrumbs = [
                ['title' => $locale === 'es' ? 'Inicio' : ($locale === 'ca' ? 'Inici' : 'Home'), 'url' => $localization->url('/', $market)],
                ['title' => 'Blog', 'url' => $localization->url("/{$blogSlug}", $market)],
            ];
        }

        $breadcrumbs[] = [
            'title' => $activeTranslation->title ?? 'Artículo',
            'url' => $localization->url("/{$blogSlug}/{$activeTranslation?->slug}", $market),
        ];

        $viewName = $this->views->resolve('blog.show', 'blog::show');
        $seoMeta = app(SeoMetaService::class)->forWebPage(
            title: (string) ($activeTranslation->meta_title ?: $activeTranslation->title),
            description: (string) ($activeTranslation->meta_description ?: $activeTranslation->summary),
            canonical: $context->currentUrl,
            entitySchema: app(PostSeoSchemaService::class)->entity($post, $activeTranslation, $context->currentUrl),
            type: 'article',
            breadcrumbs: array_map(static fn (array $crumb): array => ['name' => $crumb['title'], 'url' => $crumb['url']], $breadcrumbs),
            alternates: array_map(static fn (string $tag, string $href): array => ['hreflang' => $tag, 'href' => $href], array_keys($hreflangs), array_values($hreflangs)),
        );

        return FrontPageData::view($viewName, [
            'seoMeta' => $seoMeta,
            'post' => $post,
            'translation' => $activeTranslation,
            'renderedSections' => $renderedSections,
            'hreflangs' => $hreflangs,
            'alternateUrls' => $alternateUrls,
            'breadcrumbs' => $breadcrumbs,
            'currentMarket' => $market,
        ]);
    }
}
