<?php

declare(strict_types=1);

namespace Modules\Gallery\Search;

use App\Core\Search\ContextualSearchSource;
use App\Core\Search\SearchDocument;
use App\Models\Market;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Gallery\Models\Gallery;

final class GallerySearchSource extends ContextualSearchSource
{
    public function type(): string
    {
        return 'gallery';
    }

    public function label(string $locale): string
    {
        return match ($locale) {
            'en' => 'Gallery', 'ca' => 'Galeria', 'fr' => 'Galerie', default => 'Galería'
        };
    }

    /** @return Builder<Gallery> */
    protected function query(): Builder
    {
        return Gallery::query()->where('is_active', true)->with(['translations', 'markets', 'items']);
    }

    protected function document(Model $entity, Model $translation, Market $market): ?SearchDocument
    {
        $slug = (string) $translation->getAttribute('slug');
        $title = (string) $translation->getAttribute('title');
        $locale = (string) $translation->getAttribute('locale');
        if ($slug === '' || $title === '') {
            return null;
        }
        $body = (string) ($translation->getAttribute('description') ?? '');
        foreach ($entity->getRelation('items') as $item) {
            $body .= ' '.(string) $item->getAttribute('title').' '.(string) $item->getAttribute('caption').' '.(string) $item->getAttribute('alt_text');
        }

        return new SearchDocument($this->type(), (string) $entity->getKey(), $locale,
            $this->discovery->url($this->type(), $market, $locale, $slug), $title,
            excerpt: strip_tags((string) ($translation->getAttribute('description') ?? '')),
            body: mb_substr(strip_tags($body), 0, 20000), market: $market->code);
    }
}
