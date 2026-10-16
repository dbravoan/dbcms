<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Models\Market;

interface SeoServiceInterface
{
    /**
     * Builds the global website/organization Schema.org JSON-LD graph.
     *
     * @return array<string, mixed>
     */
    public function generateSiteSchema(): array;

    /**
     * Generates a valid XML sitemap string.
     */
    public function generateSitemapXml(?Market $market = null, ?string $locale = null, ?int $part = null): string;
}
