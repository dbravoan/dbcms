<?php

declare(strict_types=1);

namespace Modules\Marketing\Contracts;

use App\Core\Capabilities\ActorContext;
use Modules\Marketing\DTOs\CreativePackage;

interface CreativePackageProviderInterface
{
    public function getPackage(string $revisionId, ActorContext $actor): CreativePackage;
}
