<?php

declare(strict_types=1);

namespace Modules\Blog\Widgets;

use App\Core\Widgets\BaseWidgetComponent;
use App\Models\Market;
use Modules\Blog\Models\Post;

class FeaturedPostsWidget extends BaseWidgetComponent
{
    public function getRequiredModule(): ?string
    {
        return 'blog';
    }

    public function getType(): string
    {
        return 'blog.featured_posts';
    }

    public function getName(): string
    {
        return 'Featured Blog Posts';
    }

    public function getEditorSchema(): array
    {
        return [
            'limit' => ['type' => 'number', 'default' => 3, 'label' => 'Number of Posts'],
            'layout' => ['type' => 'select', 'options' => ['cards', 'carousel', 'minimal'], 'default' => 'cards'],
        ];
    }

    public function render(array $parameters = [], ?Market $market = null, ?string $locale = null): string
    {
        $limit = isset($parameters['limit']) && is_numeric($parameters['limit']) ? (int) $parameters['limit'] : 3;
        /** @var Market|null $market */
        $market ??= app()->bound('current.market') ? app('current.market') : null;
        $locale ??= app()->getLocale();

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
