<?php

declare(strict_types=1);

namespace Modules\CreativeStudio\DTOs;

final readonly class CampaignBriefInput
{
    public function __construct(public string $locale, public string $title, public string $brief, public ?string $startsAt = null, public ?string $endsAt = null) {}

    /** @param array<string,mixed> $data */
    public static function fromValidated(array $data): self
    {
        $window = CampaignWindowInput::fromValidated($data);

        return new self((string) $data['locale'], (string) $data['title'], (string) $data['brief'], $window->startsAt, $window->endsAt);
    }
}
