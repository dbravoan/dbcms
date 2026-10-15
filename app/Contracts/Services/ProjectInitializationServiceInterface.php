<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\DTOs\ProjectProfile;

interface ProjectInitializationServiceInterface
{
    public function initialize(string $name, string $brand, string $themes, string $deployment): ProjectProfile;
}
