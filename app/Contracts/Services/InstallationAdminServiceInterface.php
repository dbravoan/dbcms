<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\DTOs\Install\InstallationAdminDTO;

interface InstallationAdminServiceInterface
{
    public function finalize(InstallationAdminDTO $admin, bool $force = false): void;
}
