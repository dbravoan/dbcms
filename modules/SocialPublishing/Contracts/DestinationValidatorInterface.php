<?php

declare(strict_types=1);

namespace Modules\SocialPublishing\Contracts;

use App\Core\Capabilities\ActorContext;
use Modules\Marketing\DTOs\CreativePackage;

interface DestinationValidatorInterface
{
    public function check(CreativePackage $package, ?ActorContext $actor = null): void;
}
