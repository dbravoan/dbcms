<?php

declare(strict_types=1);

namespace App\DTOs\Modules;

final readonly class ModulePackageDTO
{
    public function __construct(public string $path, public int $bytes) {}
}
