<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\DTOs\Seo\SeoMetadataDTO;
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
     * Returns resolved OpenGraph metadata.
     */
    /** @return array<string, mixed> */
    public function resolveMetadata(SeoMetadataDTO $dto): array;

    /**
     * Generates a valid XML sitemap string.
     */
    public function generateSitemapXml(?Market $market = null, ?string $locale = null, ?int $part = null): string;
}
