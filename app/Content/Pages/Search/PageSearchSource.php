<?php

declare(strict_types=1);

namespace App\Content\Pages\Search;

use App\Content\Pages\Models\Page;
use App\Contracts\Services\PublishedSectionTextInterface;
use App\Core\Search\ContextualSearchSource;
use App\Core\Search\SearchDocument;
use App\Models\Market;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class PageSearchSource extends ContextualSearchSource
{
    public function type(): string
    {
        return 'page';
    }

    public function label(string $locale): string
    {
        return match ($locale) {
            'en' => 'Page', 'ca' => 'Pàgina', 'fr' => 'Page', default => 'Página'
        };
    }

    /** @return Builder<Page> */
    protected function query(): Builder
    {
        // Prepare scheduled publications internally; SearchEngine revalidates the deadline.
        return Page::query()->where('is_published', true)->with(['translations', 'markets', 'publishedSections.translations']);
    }

    protected function document(Model $entity, Model $translation, Market $market): ?SearchDocument
    {
        $slug = (string) $translation->getAttribute('slug');
        $title = (string) $translation->getAttribute('title');
        $locale = (string) $translation->getAttribute('locale');
        if ($slug === '' || $title === '') {
            return null;
        }

        return new SearchDocument($this->type(), (string) $entity->getKey(), $locale,
            $this->discovery->url($this->type(), $market, $locale, $slug), $title,
            excerpt: (string) ($translation->getAttribute('meta_description') ?? ''),
            body: mb_substr(app(PublishedSectionTextInterface::class)->text($entity, $market, $locale, false), 0, 20000),
            market: $market->code);
    }
}
