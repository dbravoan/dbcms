<?php

declare(strict_types=1);

namespace App\DTOs\Install;

final readonly class InstallationAdminDTO
{
    public function __construct(public string $name, public string $email, public string $password, public string $siteName, public string $adminPath, public string $brand, public string $frontTheme) {}
}
