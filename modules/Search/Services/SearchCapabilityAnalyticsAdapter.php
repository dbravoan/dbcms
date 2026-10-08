<?php

declare(strict_types=1);

namespace Modules\Search\Services;

use App\Contracts\Services\SearchAnalyticsInterface;
use App\Core\Modules\ModuleManager;
use Modules\Search\Models\SearchTerm;

/** Optional private metrics: module ownership and current availability stay local. */
final class SearchCapabilityAnalyticsAdapter implements SearchAnalyticsInterface
{
    public function __construct(private readonly ModuleManager $modules) {}

    public function topTerms(?string $locale, int $limit = 10): array
    {
        if (! $this->modules->isModuleEnabled('search')) {
            return [];
        }

        $terms = SearchTerm::query()->when($locale !== null, fn ($query) => $query->where('locale', $locale))
            ->popular()->limit(min(100, max(1, $limit)))->get()
            ->map(static fn (SearchTerm $term): array => ['term' => (string) $term->term, 'hits' => (int) $term->hits, 'results' => (int) $term->results])->values()->all();

        return array_values($terms);
    }
}
