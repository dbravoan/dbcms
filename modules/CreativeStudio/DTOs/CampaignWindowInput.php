<?php

declare(strict_types=1);

namespace Modules\CreativeStudio\DTOs;

use Carbon\CarbonImmutable;

final readonly class CampaignWindowInput
{
    public function __construct(public ?string $startsAt, public ?string $endsAt) {}

    /** @param array<string,mixed> $data */
    public static function fromValidated(array $data): self
    {
        return new self(empty($data['starts_at']) ? null : CarbonImmutable::parse((string) $data['starts_at'], (string) config('app.timezone', 'UTC'))->utc()->toIso8601String(), empty($data['ends_at']) ? null : CarbonImmutable::parse((string) $data['ends_at'], (string) config('app.timezone', 'UTC'))->utc()->toIso8601String());
    }

    /** @return array{starts_at:?string,ends_at:?string} */
    public function toArray(): array
    {
        return ['starts_at' => $this->startsAt, 'ends_at' => $this->endsAt];
    }
}
