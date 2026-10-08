<?php

declare(strict_types=1);

namespace Modules\Blog\Filters;

use App\Contracts\Services\LocalizationServiceInterface;
use App\Core\Filters\Contracts\FilterableModule;
use App\Core\Filters\Contracts\FilterableResults;
use App\Core\Filters\FilterDimension;
use App\Core\Filters\FilterSet;
use App\Core\Filters\FilterUrlBuilder;
use App\Models\Market;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\Post;
use Modules\Blog\Models\Tag;

/**
 * Blog filter dimensions (friendly, canonical, SEO-aware URLs).
 *
 * `category` is implicit: a bare leading slug is a category value, so
 * `/blog/que-cocino-hoy/etiqueta-tomate` scopes the category and tags.
 */
final class BlogFilters implements FilterableModule, FilterableResults
{
    /**
     * Runs a filter request against the blog (single source of truth shared by
     * the front controller and the MCP browsing tool).
     *
     * Returns null when a filter value cannot be resolved (the caller decides
     * between a 404 and an agent-facing error).
     */
    /** @return LengthAwarePaginator<int, Post> */
    public function listQuery(FilterSet $set, Market $market, string $locale, int $perPage = 12, ?int $page = null): ?LengthAwarePaginator
    {
        $query = Post::published()
            ->forMarket($market)
            ->whereHas('translations', fn ($q) => $q->where('locale', $locale))
            ->with(['translations', 'author']);

        foreach ($set->list('category') as $categorySlug) {
            $category = Category::resolveBySlugPath($categorySlug, $locale, $market)
                ?? Category::whereHas('translations', fn ($q) => $q->where('slug', $categorySlug)->where('locale', $locale))->first();

            if ($category === null) {
                return null;
            }

            $query->where('category_id', $category->getKey());
        }

        if (($tags = $set->list('tag')) !== []) {
            $query->whereHas('tags', fn ($q) => $q->whereIn('slug', $tags));
        }

        if (($term = $set->string('search')) !== null) {
            $term = str_replace('-', ' ', $term);
            $query->whereHas('translations', function ($q) use ($term, $locale): void {
                $q->where('locale', $locale)->where(function ($inner) use ($term): void {
                    $inner->where('title', 'like', "%{$term}%")->orWhere('content', 'like', "%{$term}%");
                });
            });
        }

        if (($from = $set->string('date_from')) !== null) {
            $query->whereDate('published_at', '>=', $from);
        }

        if (($to = $set->string('date_to')) !== null) {
            $query->whereDate('published_at', '<=', $to);
        }

        match ($set->string('sort', 'recientes')) {
            'antiguos' => $query->orderBy('published_at'),
            'populares' => $query->withCount('comments')->orderByDesc('comments_count'),
            default => $query->orderByDesc('published_at'),
        };

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Agent-facing projection of a filter request (MCP / WebMCP navigation).
     *
     * @return array{items: array<int, array<string, mixed>>, total: int, canonical_path: string}
     */
    public function browse(FilterSet $set, Market $market, string $locale, int $perPage = 10): array
    {
        $paginator = $this->listQuery($set, $market, $locale, $perPage);
        $localization = app(LocalizationServiceInterface::class);
        $prefix = $localization->getModulePrefix($this->filterModuleKey(), $locale);

        $items = [];

        foreach ($paginator?->items() ?? [] as $post) {
            $translation = $post->translations->firstWhere('locale', $locale) ?? $post->translations->first();
            $slug = (string) ($translation->slug ?? '');

            if ($slug === '') {
                continue;
            }

            $items[] = [
                'id' => (int) $post->getKey(),
                'type' => 'post',
                'title' => (string) ($translation->title ?? $slug),
                'excerpt' => mb_substr(trim(strip_tags((string) ($translation->excerpt ?? $translation->content ?? ''))), 0, 240),
                'published_at' => $post->published_at?->toIso8601String(),
                'url' => $localization->url("/{$prefix}/{$slug}", $market, $locale),
            ];
        }

        return [
            'items' => $items,
            'total' => (int) ($paginator?->total() ?? 0),
            'canonical_path' => (string) ((app(FilterUrlBuilder::class))->canonicalPath($set, $this->filterDimensions(), $locale) ?? ''),
        ];
    }

    public function filterModuleKey(): string
    {
        return 'blog';
    }

    public function filterModuleLabel(string $locale): string
    {
        return match ($locale) {
            'en' => 'Blog',
            'ca' => 'Blog',
            default => 'Blog',
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
                vocabulary: fn (string $locale): array => $this->categories($locale),
                implicit: true,
            ),
            FilterDimension::terms(
                'tag',
                multi: true,
                order: 20,
                vocabulary: fn (string $locale): array => $this->tags($locale),
            ),
            FilterDimension::text('search', order: 30),
            FilterDimension::enum('sort', [
                'recientes' => ['es' => 'Recientes', 'en' => 'Newest', 'ca' => 'Recents'],
                'antiguos' => ['es' => 'Antiguos', 'en' => 'Oldest', 'ca' => 'Antics'],
                'populares' => ['es' => 'Populares', 'en' => 'Popular', 'ca' => 'Populars'],
            ], order: 40),
            FilterDimension::date('date_from', order: 50, bound: 'from'),
            FilterDimension::date('date_to', order: 60, bound: 'to'),
        ];
    }

    /**
     * @return array<string, array{label: string, key: int}>
     */
    private function categories(string $locale): array
    {
        $map = [];

        $categories = Category::query()
            ->where('is_active', true)
            ->with('translations')
            ->get();

        foreach ($categories as $category) {
            $translation = $category->translations->firstWhere('locale', $locale)
                ?? $category->translations->first();

            $slug = (string) ($category->getSlugPath($locale) ?: ($translation->slug ?? ''));
            $label = (string) ($translation->name ?? $category->getKey());

            if ($slug !== '') {
                $map[$slug] = ['label' => $label, 'key' => (int) $category->getKey()];
            }
        }

        return $map;
    }

    /**
     * @return array<string, array{label: string, key: int}>
     */
    private function tags(string $locale): array
    {
        $map = [];

        foreach (Tag::query()->orderBy('name')->get() as $tag) {
            $slug = (string) $tag->slug;

            if ($slug !== '') {
                $map[mb_strtolower($slug)] = ['label' => (string) $tag->name, 'key' => (int) $tag->getKey()];
            }
        }

        return $map;
    }
}
