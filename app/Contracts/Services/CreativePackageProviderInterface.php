<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Core\Capabilities\ActorContext;
use App\DTOs\Marketing\CreativePackage;

interface CreativePackageProviderInterface
{
    public function getPackage(string $revisionId, ActorContext $actor): CreativePackage;
}
