<?php

declare(strict_types=1);

namespace Modules\Search\Services;

use App\Contracts\Services\ModuleSettingsServiceInterface;

/**
 * Settings of the general search module, with the documented defaults.
 */
final class SearchSettings
{
    public const MODULE = 'search';

    public function __construct(
        private readonly ModuleSettingsServiceInterface $settings,
    ) {}

    public function isIndexable(): bool
    {
        return (bool) $this->settings->get(self::MODULE, 'is_indexable', false);
    }

    public function resultsPerPage(): int
    {
        return max(1, (int) $this->settings->get(self::MODULE, 'results_per_page', 12));
    }

    public function minResultsToIndex(): int
    {
        return max(1, (int) $this->settings->get(self::MODULE, 'min_results_to_index', 3));
    }

    public function tracksTerms(): bool
    {
        return (bool) $this->settings->get(self::MODULE, 'track_terms', true);
    }

    public function set(string $key, mixed $value): void
    {
        $this->settings->set(self::MODULE, $key, $value);
    }
}
