<?php

declare(strict_types=1);

namespace App\Core\Content\Contracts;

use App\Models\Market;

interface PublicWidgetSemanticContributorInterface
{
    /** @return list<string> */
    public function keys(): array;

    public function ownerModule(): string;

    /** @param array<string,mixed> $payload */
    public function text(array $payload, Market $market, string $locale): string;
}
