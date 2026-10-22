<?php

declare(strict_types=1);

namespace Modules\Blog\Search;

use App\Contracts\Services\LocalizationServiceInterface;
use App\Core\Search\AbstractSearchSource;
use App\Core\Search\SearchDocument;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Modules\Blog\Models\Post;

/**
 * Indexes published blog posts, one document per translation.
 *
 * The URL is the canonical front-office URL of the post in that locale, so a
 * result always links to the exact page the crawler would index.
 */
final class PostSearchSource extends AbstractSearchSource
{
    public function __construct(
        private readonly LocalizationServiceInterface $localization,
    ) {}

    public function type(): string
    {
        return 'post';
    }

    public function label(string $locale): string
    {
        return match ($locale) {
            'en' => 'Blog post',
            'ca' => 'Article del blog',
            default => 'Artículo del blog',
        };
    }

    public function weight(): float
    {
        return 1.2;
    }

    /**
     * @return Builder<Post>
     */
    protected function query(): Builder
    {
        return Post::query()
            ->where('is_published', true)
            ->with('translations');
    }

    /**
     * @return iterable<int, Model>
     */
    protected function translations(Model $entity): iterable
    {
        /** @var iterable<int, Model> $translations */
        $translations = $entity->getRelation('translations');

        return $translations;
    }

    protected function document(Model $entity, Model $translation): ?SearchDocument
    {
        $slug = (string) $translation->getAttribute('slug');
        $title = (string) $translation->getAttribute('title');
        $locale = (string) $translation->getAttribute('locale');

        if ($slug === '' || $title === '' || $locale === '') {
            return null;
        }

        return new SearchDocument(
            type: $this->type(),
            entityId: (string) $entity->getKey(),
            locale: $locale,
            url: $this->localization->frontUrl('blog', $slug, $locale),
            title: $title,
            excerpt: (string) ($translation->getAttribute('summary') ?? ''),
            body: $this->plainText($translation->getAttribute('content')),
            weight: $this->weight(),
        );
    }

    private function plainText(mixed $content): string
    {
        if (! is_string($content)) {
            return '';
        }

        return Str::limit(trim(preg_replace('/\s+/u', ' ', strip_tags($content)) ?? ''), 20000, '');
    }
}
