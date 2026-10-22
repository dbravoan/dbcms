<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\DTOs\Install\DatabaseSetupDTO;
use App\DTOs\Install\InstallationResultDTO;

interface InstallationDatabaseServiceInterface
{
    public function test(DatabaseSetupDTO $database): InstallationResultDTO;

    public function save(DatabaseSetupDTO $database): void;
}
