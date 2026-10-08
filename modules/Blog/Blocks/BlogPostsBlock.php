<?php

declare(strict_types=1);

namespace Modules\Blog\Blocks;

use App\Contracts\Services\LocalizationServiceInterface;
use App\Contracts\Services\PublicContentDiscoveryInterface;
use App\Core\Blocks\AbstractBlockType;
use App\Core\Blocks\Contracts\TranslatableBlockInterface;
use App\Core\Sections\SectionLayout;
use App\Models\Market;
use Illuminate\Database\Eloquent\Builder;
use Modules\Blog\Models\Post;

class BlogPostsBlock extends AbstractBlockType implements TranslatableBlockInterface
{
    public function getType(): string
    {
        return 'widget_posts';
    }

    public function getName(): string
    {
        return 'Artículos del Blog';
    }

    public function getIcon(): string
    {
        return 'fa-solid fa-newspaper';
    }

    public function getCategory(): string
    {
        return 'dynamic';
    }

    public function getDescription(): string
    {
        return 'Listado dinámico de artículos recientes, filtrado por categoría o destacado individual.';
    }

    public function getRequiredModule(): ?string
    {
        return 'blog';
    }

    /**
     * The block manages its own grid: it is added as a section, never nested
     * inside a column (a grid inside a column renders as a grid inside a grid).
     */
    public function isFullWidth(): bool
    {
        return true;
    }

    /** @return list<string> */
    public function getTranslationPaths(): array
    {
        return ['title'];
    }

    public function getDefaultPayload(): array
    {
        return [
            'title' => 'Últimos Artículos del Blog',
            'category' => '',
            'post_id' => '',
            'limit' => 3,
            'grid_columns' => '3',
        ];
    }

    public function render(array $payload, ?Market $market = null, ?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        $title = htmlspecialchars((string) ($payload['title'] ?? 'Últimos Artículos'));
        $limit = (int) ($payload['limit'] ?? ($payload['post_count'] ?? 3));
        $category = (string) ($payload['category'] ?? '');
        $specificPostId = (string) ($payload['post_id'] ?? '');
        $columns = SectionLayout::gridColumns($payload, '3');

        $market ??= app()->bound('current.market') ? app('current.market') : app(PublicContentDiscoveryInterface::class)->markets()->firstWhere('is_default', true);
        if (! $market instanceof Market) {
            return '';
        }
        /** @var Builder<Post> $query */
        $query = app(PublicContentDiscoveryInterface::class)->query('post', $market, $locale, ['category.translations']);

        if (! empty($specificPostId)) {
            $query->where('id', $specificPostId);
        } elseif (! empty($category)) {
            $query->whereHas('category', function ($q) use ($category): void {
                $q->where('is_active', true)->where(fn ($selected) => $selected->where('id', $category)->orWhereHas('translations', fn ($tr) => $tr->where('slug', $category)));
            });
        }

        $posts = $query->latest()->take($limit)->get();

        $locService = app()->bound(LocalizationServiceInterface::class) ? app(LocalizationServiceInterface::class) : null;

        $items = $posts->map(function ($p) use ($locale, $market, $locService) {
            $trans = $p->translations->where('locale', $locale)->first();
            $slug = $trans->slug ?? (string) $p->id;
            $url = $locService ? $locService->url("/blog/{$slug}", $market, $locale) : url("/blog/{$slug}");
            $catTrans = $p->category?->translations->where('locale', $locale)->first() ?? $p->category?->translations->first();

            return [
                'title' => $trans->title ?? 'Artículo',
                'summary' => $trans->summary ?? '',
                'image' => $p->featured_image ?: 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?w=600',
                'badge' => $catTrans->name ?? 'Blog',
                'url' => $url,
                'cta_label' => 'Leer artículo',
            ];
        })->toArray();

        $allUrl = $locService ? $locService->url('/blog', $market, $locale) : url('/blog');
        $headerHtml = <<<HTML
        <div class="flex items-center justify-between pb-3 border-b cms-border font-mono mb-6">
            <h2 class="text-2xl font-bold cms-text flex items-center gap-2"><span class="cms-accent">&gt;</span> {$title}</h2>
            <a href="{$allUrl}" class="text-xs cms-accent hover:underline">Ver todos &rarr;</a>
        </div>
        HTML;

        $cardsHtml = view('components.cms.card-grid', [
            'items' => $items,
            'columns' => $columns,
            'mapping' => [
                'title' => 'title',
                'summary' => 'summary',
                'image' => 'image',
                'badge' => 'badge',
                'url' => 'url',
                'cta_label' => 'cta_label',
            ],
            'style' => 'cms-card',
        ])->render();

        return <<<HTML
        <div class="space-y-6 w-full font-mono">
            {$headerHtml}
            {$cardsHtml}
        </div>
        HTML;
    }
}
