<?php

declare(strict_types=1);

namespace App\DTOs\Seo;

use App\DTOs\BaseDTO;
use App\Enums\SchemaOrgType;

readonly class SeoMetadataDTO extends BaseDTO
{
    /**
     * @param  list<string>  $keywords
     */
    public function __construct(
        public string $title,
        public string $description,
        public string $canonicalUrl,
        public ?string $ogImage = null,
        public SchemaOrgType $schemaType = SchemaOrgType::ARTICLE,
        public array $keywords = []
    ) {}
}
