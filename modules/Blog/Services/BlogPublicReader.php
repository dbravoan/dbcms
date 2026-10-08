<?php

declare(strict_types=1);

namespace Modules\Blog\Services;

use App\Contracts\Services\LocalizationServiceInterface;
use App\Core\Filters\FilterPathParser;
use App\Core\Modules\ModuleManager;
use App\DTOs\Front\FrontPageData;
use App\DTOs\Front\PublicContentContext;
use App\Models\Market;
use Modules\Blog\Contracts\BlogArticleReaderInterface;
use Modules\Blog\Contracts\BlogCatalogueReaderInterface;
use Modules\Blog\Contracts\BlogPublicReaderInterface;
use Modules\Blog\Filters\BlogFilters;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\Post;

final class BlogPublicReader implements BlogPublicReaderInterface
{
    public function __construct(private readonly BlogCatalogueReaderInterface $catalogue, private readonly BlogArticleReaderInterface $articles, private readonly LocalizationServiceInterface $localization, private readonly ModuleManager $modules) {}

    public function index(PublicContentContext $context): FrontPageData
    {
        return $this->modules->isModuleEnabled('blog') ? $this->catalogue->index($context) : FrontPageData::missing();
    }

    public function show(PublicContentContext $context): FrontPageData
    {
        return $this->modules->isModuleEnabled('blog') ? $this->articles->show($context) : FrontPageData::missing();
    }

    public function category(PublicContentContext $context, ?Category $category = null): FrontPageData
    {
        return $this->modules->isModuleEnabled('blog') ? $this->catalogue->category($context, $category) : FrontPageData::missing();
    }

    public function resolveSlug(PublicContentContext $context): FrontPageData
    {
        if (! $this->modules->isModuleEnabled('blog')) {
            return FrontPageData::missing();
        }
        $localization = $this->localization;
        $market = $context->market;
        $locale = $context->locale;
        $slug = $context->slug;

        // 1. Check if slug corresponds to a category
        $category = Category::resolveBySlugPath($slug, $locale, $market)
            ?? Category::where('is_active', true)->forMarket($market)->whereHas('translations', function ($q) use ($slug, $locale): void {
                $q->where('slug', $slug)->where('locale', $locale);
            })->with(['translations', 'parent.translations'])->first();

        if ($category !== null) {
            return $this->catalogue->category($context, $category);
        }

        // 2. Check if slug corresponds to a published Post
        $postExists = Post::published()
            ->forMarket($market)
            ->whereHas('translations', function ($q) use ($slug, $locale): void {
                $q->where('slug', $slug)->where('locale', $locale);
            })
            ->exists();

        if ($postExists) {
            return $this->articles->show($context);
        }

        // 3. Friendly filter/search path (e.g. `que-cocino-hoy/etiqueta-tomate`).
        //    A single bare segment that collides with another module's prefix
        //    (`tienda`, `shop`, `galeria`…) is never reinterpreted as a search
        //    value: it must 404 like any other unknown slug instead of serving a
        //    duplicated search URL.
        $bareSegment = trim($slug, '/');

        $isModulePrefix = ! str_contains($bareSegment, '/')
            && array_key_exists(strtolower($bareSegment), $localization->moduleAliases());

        if (! $isModulePrefix) {
            $filters = app(BlogFilters::class);
            $set = (app(FilterPathParser::class))->parse(
                'blog',
                (string) $market->code,
                $locale,
                $bareSegment,
                $filters->filterDimensions(),
            );

            if ($set !== null) {
                return $this->catalogue->filtered($context, $set);
            }
        }

        return FrontPageData::missing();
    }

    /**
     * Render a dynamic, market/locale-scoped filter page from a friendly URL.
     */
}
