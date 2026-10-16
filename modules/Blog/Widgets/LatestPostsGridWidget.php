<?php

declare(strict_types=1);

namespace Modules\Blog\Widgets;

use App\Core\Widgets\BaseWidgetComponent;
use App\Models\Market;
use Modules\Blog\Models\Post;

class LatestPostsGridWidget extends BaseWidgetComponent
{
    public function getRequiredModule(): ?string
    {
        return 'blog';
    }

    public function getType(): string
    {
        return 'blog.latest_posts_grid';
    }

    public function getName(): string
    {
        return 'Latest Blog Posts Grid';
    }

    public function getEditorSchema(): array
    {
        return [
            'limit' => ['type' => 'number', 'default' => 6, 'label' => 'Total Posts'],
            'grid_columns' => ['type' => 'select', 'options' => [2, 3, 4], 'default' => 3],
        ];
    }

    public function render(array $parameters = [], ?Market $market = null, ?string $locale = null): string
    {
        $limit = isset($parameters['limit']) && is_numeric($parameters['limit']) ? (int) $parameters['limit'] : 6;
        /** @var Market|null $market */
        $market ??= app()->bound('current.market') ? app('current.market') : null;
        $locale ??= app()->getLocale();

        $posts = Post::published()
            ->forMarket($market)
            ->whereHas('translations', function ($q) use ($locale): void {
                $q->where('locale', $locale);
            })
            ->with(['translations', 'author', 'category'])
            ->latest('published_at')
            ->take($limit)
            ->get();

        return $this->renderWidgetView('blog', 'latest_posts_grid', [
            'posts' => $posts,
            'parameters' => $parameters,
        ]);
    }
}
