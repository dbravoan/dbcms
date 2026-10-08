<?php

declare(strict_types=1);

namespace Modules\Blog\Widgets;

use App\Core\Widgets\BaseWidgetComponent;
use App\Models\Market;
use Illuminate\Contracts\View\View;
use Modules\Blog\Models\Post;

class LatestPostsGridWidget extends BaseWidgetComponent
{
    public static function getKey(): string
    {
        return 'blog.latest_posts_grid';
    }

    public static function getTitle(): string
    {
        return 'Latest Blog Posts Grid';
    }

    public static function getOptionsSchema(): array
    {
        return [
            'limit' => ['type' => 'number', 'default' => 6, 'label' => 'Total Posts'],
            'columns' => ['type' => 'select', 'options' => [2, 3, 4], 'default' => 3],
        ];
    }

    public function render(array $parameters = []): View|string
    {
        $limit = isset($parameters['limit']) && is_numeric($parameters['limit']) ? (int) $parameters['limit'] : 6;
        /** @var Market|null $market */
        $market = app()->bound('current.market') ? app('current.market') : null;
        $locale = app()->getLocale();

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
