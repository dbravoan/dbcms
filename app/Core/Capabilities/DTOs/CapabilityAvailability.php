<?php

declare(strict_types=1);

namespace App\Core\Capabilities\DTOs;

final readonly class CapabilityAvailability
{
    public function __construct(public bool $available, public ?string $reason) {}
}
