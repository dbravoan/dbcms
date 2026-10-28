<?php

declare(strict_types=1);

namespace Modules\Blog\Blocks;

use App\Contracts\Services\LocalizationServiceInterface;
use App\Contracts\Services\PublicContentDiscoveryInterface;
use App\Core\Blocks\AbstractBlockType;
use App\Core\Content\Contracts\PublicWidgetSemanticContributorInterface;
use App\Core\Sections\SectionLayout;
use App\Models\Market;
use App\Services\Cms\FrontViewResolver;
use Illuminate\Database\Eloquent\Builder;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\Post;

class BlogPostsBlock extends AbstractBlockType implements PublicWidgetSemanticContributorInterface
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

    public function getEditorData(): array
    {
        return [
            'blog_categories' => Category::with('translations')->get()->map(fn ($category): array => ['id' => $category->id, 'name' => $category->translations->first()->name ?? (string) $category->id, 'slug' => $category->translations->first()->slug ?? ''])->all(),
            'blog_posts' => Post::published()->with('translations')->latest()->limit(20)->get()->map(fn ($post): array => ['id' => $post->id, 'title' => $post->translations->first()->title ?? (string) $post->id, 'slug' => $post->translations->first()->slug ?? ''])->all(),
        ];
    }

    public function getEditorSchema(): array
    {
        return [
            'title' => ['type' => 'text', 'label' => 'Título', 'description' => 'Título opcional sobre la lista de artículos.'],

            'selection' => ['type' => 'select', 'label' => 'Selección', 'options' => ['latest' => 'Recientes', 'featured' => 'Destacados'], 'default' => 'latest', 'description' => 'Orden o filtro de los artículos cuando no eliges uno concreto.', 'show_when' => ['field' => 'post_id', 'falsy' => true]],
            'category' => ['type' => 'select', 'label' => 'Categoría', 'options' => ['' => 'Todas'], 'options_source' => 'blog_categories', 'option_label' => 'name', 'option_value' => 'id', 'rules' => ['nullable', 'integer', 'min:1'], 'description' => 'Limita la lista a una categoría.', 'show_when' => ['field' => 'post_id', 'falsy' => true]],
            'post_id' => ['type' => 'select', 'label' => 'Artículo', 'options' => ['' => 'Selección automática'], 'options_source' => 'blog_posts', 'option_label' => 'title', 'option_value' => 'id', 'rules' => ['nullable', 'integer', 'min:1'], 'description' => 'Si eliges un artículo concreto se ignoran selección, categoría y límite.'],
            'limit' => ['type' => 'integer', 'label' => 'Límite', 'default' => 3, 'rules' => ['nullable', 'integer', 'between:1,50'], 'description' => 'Número máximo de artículos.', 'show_when' => ['field' => 'post_id', 'falsy' => true]],
            'grid_columns' => ['type' => 'select', 'label' => 'Columnas', 'options' => ['2', '3', '4'], 'default' => '3', 'description' => 'Columnas de la rejilla en pantallas grandes.'],
        ];
    }

    public function getDefaultPayload(): array
    {
        return [
            'title' => 'Últimos Artículos del Blog',
            'selection' => 'latest',
            'category' => '',
            'post_id' => '',
            'limit' => 3,
            'grid_columns' => '3',
        ];
    }

    public function keys(): array
    {
        return [$this->getType()];
    }

    public function ownerModule(): string
    {
        return 'blog';
    }

    public function text(array $payload, Market $market, string $locale): string
    {
        $text = '';
        foreach ($this->items($payload, $market, $locale) as $item) {
            $text .= '- ['.strip_tags($item['title']).']('.$item['url'].")\n";
        }

        return $text;
    }

    /** @param array<string,mixed> $payload
     * @return array<int,array<string,string>>
     */
    private function items(array $payload, Market $market, string $locale): array
    {
        $discovery = app(PublicContentDiscoveryInterface::class);
        /** @var Builder<Post> $query */
        $query = $discovery->query('post', $market, $locale, ['category.translations']);
        $specific = $payload['post_id'] ?? '';
        $category = (string) ($payload['category'] ?? '');
        if ($specific !== '') {
            $query->whereKey($specific);
        } elseif ($category !== '') {
            $query->whereHas('category', fn ($categories) => $categories->where('is_active', true)->whereKey($category));
        }
        if (($payload['selection'] ?? 'latest') === 'featured') {
            $query->where('is_featured', true);
        }
        $limit = min(50, max(1, (int) ($payload['limit'] ?? 3)));

        return $query->latest('published_at')->orderByDesc('id')->limit($limit)->get()->map(function (Post $entity) use ($discovery, $market, $locale): array {
            $translation = $entity->translations->firstWhere('locale', $locale);

            return [
                'title' => (string) $translation?->title,
                'summary' => (string) $translation?->summary,
                'image' => (string) $entity->featured_image,
                'url' => $discovery->url('post', $market, $locale, (string) $translation?->slug),
                'badge' => (string) $entity->category?->translations->firstWhere('locale', $locale)?->name,
                'cta_label' => 'Leer artículo',
            ];
        })->all();
    }

    public function render(array $payload, ?Market $market = null, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $market ??= app()->bound('current.market') ? app('current.market') : app(PublicContentDiscoveryInterface::class)->markets()->firstWhere('is_default', true);
        if (! $market instanceof Market) {
            return '';
        }
        $view = app(FrontViewResolver::class)->resolve('blog.blocks.posts', 'blog::blocks.posts');

        return view($view, [
            'items' => $this->items($payload, $market, $locale),
            'title' => (string) ($payload['title'] ?? ''),

            'columns' => SectionLayout::gridColumns($payload, '3'),
            'indexUrl' => app(LocalizationServiceInterface::class)->frontUrl('blog', '', $locale, $market),
        ])->render();
    }
}
