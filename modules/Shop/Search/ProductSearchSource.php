<?php

declare(strict_types=1);

namespace Modules\Shop\Search;

use App\Contracts\Services\LocalizationServiceInterface;
use App\Core\Search\AbstractSearchSource;
use App\Core\Search\SearchDocument;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Modules\Shop\Models\Product;

/**
 * Indexes active shop products, one document per translation.
 */
final class ProductSearchSource extends AbstractSearchSource
{
    public function __construct(
        private readonly LocalizationServiceInterface $localization,
    ) {}

    public function type(): string
    {
        return 'product';
    }

    public function label(string $locale): string
    {
        return match ($locale) {
            'en' => 'Product',
            'ca' => 'Producte',
            default => 'Producto',
        };
    }

    public function weight(): float
    {
        return 1.1;
    }

    /**
     * @return Builder<Product>
     */
    protected function query(): Builder
    {
        return Product::query()
            ->where('is_active', true)
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
        $title = (string) $translation->getAttribute('name');
        $locale = (string) $translation->getAttribute('locale');

        if ($slug === '' || $title === '' || $locale === '') {
            return null;
        }

        $summary = (string) ($translation->getAttribute('summary') ?? '');
        $description = (string) ($translation->getAttribute('meta_description') ?? '');

        return new SearchDocument(
            type: $this->type(),
            entityId: (string) $entity->getKey(),
            locale: $locale,
            url: $this->localization->frontUrl('shop', $slug, $locale),
            title: $title,
            excerpt: $summary !== '' ? $summary : $description,
            body: $this->plainText($translation->getAttribute('description')),
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
