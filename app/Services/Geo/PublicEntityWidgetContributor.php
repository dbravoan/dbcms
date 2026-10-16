<?php

declare(strict_types=1);

namespace App\Services\Geo;

use App\Contracts\Services\PublicContentDiscoveryInterface;
use App\Core\Content\Contracts\PublicWidgetSemanticContributorInterface;
use App\Models\Market;
use Illuminate\Database\Eloquent\Model;

/** A module registers this adapter only for widgets backed by its public entities. */
final class PublicEntityWidgetContributor implements PublicWidgetSemanticContributorInterface
{
    /** @param list<string> $widgetKeys */
    public function __construct(private readonly string $module, private readonly string $entityType, private readonly array $widgetKeys) {}

    public function keys(): array
    {
        return $this->widgetKeys;
    }

    public function ownerModule(): string
    {
        return $this->module;
    }

    public function text(array $payload, Market $market, string $locale): string
    {
        $discovery = app(PublicContentDiscoveryInterface::class);
        $query = $discovery->query($this->entityType, $market, $locale);
        $widgetKey = $payload['type'] ?? '';
        if ($widgetKey === 'blog.featured_posts') {
            $query->where('is_featured', true);
        }
        if ($widgetKey === 'shop.featured_products' && (clone $query)->where('is_featured', true)->exists()) {
            $query->where('is_featured', true);
        }
        $ids = $payload['ids'] ?? $payload['post_ids'] ?? $payload['product_ids'] ?? null;
        if (is_array($ids) && $ids !== []) {
            $query->whereKey(array_values(array_filter($ids, 'is_numeric')));
        }
        $specific = $payload['post_id'] ?? $payload['product_id'] ?? null;
        if (is_numeric($specific)) {
            $query->whereKey((int) $specific);
        } elseif (is_scalar($payload['category'] ?? null) && (string) $payload['category'] !== '') {
            $category = (string) $payload['category'];
            $query->whereHas('category', fn ($selection) => $selection->where('is_active', true)->where(fn ($value) => $value->whereKey(is_numeric($category) ? (int) $category : 0)->orWhereHas('translations', fn ($translation) => $translation->where('locale', $locale)->where('slug', $category))));
        }
        $slug = $payload['album_slug'] ?? null;
        if ($widgetKey === 'gallery.grid' && $slug === null) {
            $slug = 'default';
        }
        if (is_scalar($slug) && (string) $slug !== '') {
            $query->where(fn ($selection) => $selection->whereKey(is_numeric($slug) ? (int) $slug : 0)->orWhereHas('translations', fn ($translation) => $translation->where('locale', $locale)->where('slug', $slug)));
        }
        $limit = min(50, max(1, is_numeric($payload['limit'] ?? null) ? (int) $payload['limit'] : 4));
        $text = '';
        if ($this->entityType === 'post') {
            $query->latest($widgetKey === 'blog.latest_posts_grid' ? 'published_at' : 'created_at');
        }
        foreach ($query->limit($limit)->get() as $entity) {
            $translation = $entity->getRelation('translations')->firstWhere('locale', $locale);
            if (! $translation instanceof Model) {
                continue;
            }
            $title = (string) ($translation->getAttribute('title') ?? $translation->getAttribute('name') ?? '');
            $url = $discovery->url($this->entityType, $market, $locale, (string) $translation->getAttribute('slug'));
            $text .= '- ['.strip_tags($title).']('.$url.")\n";
        }

        return $text;
    }
}
