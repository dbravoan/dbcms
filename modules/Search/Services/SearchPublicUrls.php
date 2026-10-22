<?php

declare(strict_types=1);

namespace Modules\Search\Services;

use App\Contracts\Services\LocalizationServiceInterface;
use App\Core\Content\Contracts\PublicUrlContributorInterface;
use App\Models\Market;

final class SearchPublicUrls implements PublicUrlContributorInterface
{
    public function __construct(private readonly LocalizationServiceInterface $localization) {}

    public function ownerModule(): string
    {
        return 'search';
    }

    public function urls(Market $market, string $locale): array
    {
        if (! (bool) module_setting('search', 'is_indexable', false)) {
            return [];
        }
        $prefix = $this->localization->getModulePrefix('search', $locale);

        return [$this->localization->url($prefix, $market, $locale) => 'General Search'];
    }
}
