<?php

declare(strict_types=1);

namespace App\DTOs\Marketing;

final readonly class GenerationReceipt
{
    /** @param array<string, mixed> $usage */
    public function __construct(
        public string $operationId,
        public string $state,
        public string $provider,
        public string $model,
        public ?string $providerRequestId = null,
        public array $usage = [],
        public ?int $estimatedCost = null,
        public ?int $confirmedCost = null,
        public string $currency = 'USD',
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
