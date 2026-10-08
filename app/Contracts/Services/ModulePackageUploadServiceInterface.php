<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\DTOs\Modules\ModulePackageDTO;

interface ModulePackageUploadServiceInterface
{
    public function upload(ModulePackageDTO $package): string;
}
