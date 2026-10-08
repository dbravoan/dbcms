<?php

declare(strict_types=1);

namespace App\Services\Search;

use App\Contracts\Services\SearchAnalyticsInterface;

final class NoSearchAnalytics implements SearchAnalyticsInterface
{
    public function topTerms(?string $locale, int $limit = 10): array
    {
        return [];
    }
}
