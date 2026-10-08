<?php

declare(strict_types=1);

namespace App\DTOs\Seo;

use App\DTOs\BaseDTO;
use App\Models\Market;
use Illuminate\Support\Collection;

final readonly class SeoAuditReportDTO extends BaseDTO
{
    /** @param list<array<string, mixed>> $auditedPosts
     * @param  Collection<int, Market>  $markets
     */
    public function __construct(
        public array $auditedPosts,
        public int $pagesCount,
        public int $postsCount,
        public Collection $markets,
        public string $siteSchema,
        public bool $geoRoutesAvailable,
    ) {}
}
