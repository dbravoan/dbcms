<?php

declare(strict_types=1);

namespace App\Events;

final readonly class ModuleAvailabilityChanged
{
    public function __construct(public string $module, public bool $available, public string $operation) {}
}
