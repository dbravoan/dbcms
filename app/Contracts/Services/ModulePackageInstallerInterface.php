<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\DTOs\Modules\InstalledModulePackageDTO;
use App\DTOs\Modules\ModulePackageDTO;

interface ModulePackageInstallerInterface
{
    public function install(ModulePackageDTO $package): InstalledModulePackageDTO;

    public function rollback(InstalledModulePackageDTO $package): void;
}
