<?php

declare(strict_types=1);

namespace Modules\Blog\Widgets;

use App\Core\Widgets\BaseWidgetComponent;
use App\Models\Market;
use Illuminate\Contracts\View\View;
use Modules\Blog\Models\Post;

class FeaturedPostsWidget extends BaseWidgetComponent
{
    public static function getKey(): string
    {
        return 'blog.featured_posts';
    }

    public static function getTitle(): string
    {
        return 'Featured Blog Posts';
    }

    public static function getOptionsSchema(): array
    {
        return [
            'limit' => ['type' => 'number', 'default' => 3, 'label' => 'Number of Posts'],
            'layout' => ['type' => 'select', 'options' => ['cards', 'carousel', 'minimal'], 'default' => 'cards'],
        ];
    }

    public function render(array $parameters = []): View|string
    {
        $limit = isset($parameters['limit']) && is_numeric($parameters['limit']) ? (int) $parameters['limit'] : 3;
        /** @var Market|null $market */
        $market = app()->bound('current.market') ? app('current.market') : null;
        $locale = app()->getLocale();

        $posts = Post::published()->featured()
            ->forMarket($market)
            ->whereHas('translations', function ($q) use ($locale): void {
                $q->where('locale', $locale);
            })
            ->with(['translations', 'author', 'category'])
            ->take($limit)
            ->get();

        return $this->renderWidgetView('blog', 'featured_posts', [
            'posts' => $posts,
            'parameters' => $parameters,
        ]);
    }
}
