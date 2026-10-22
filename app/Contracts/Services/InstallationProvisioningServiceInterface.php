<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\DTOs\Install\InstallationResultDTO;
use Symfony\Component\Console\Output\OutputInterface;

interface InstallationProvisioningServiceInterface
{
    public function provision(bool $withDemo = false, ?OutputInterface $output = null): InstallationResultDTO;
}
