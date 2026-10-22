<?php

declare(strict_types=1);

namespace Modules\Marketing\Services;

use App\Core\Capabilities\ActorContext;
use Modules\Marketing\Contracts\CreativePackageProviderInterface;
use Modules\Marketing\DTOs\CreativePackage;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class UnavailableCreativePackageProvider implements CreativePackageProviderInterface
{
    public function getPackage(string $revisionId, ActorContext $actor): CreativePackage
    {
        throw new HttpException(503, 'CreativeStudio no está disponible. Puede preparar el material manualmente.');
    }
}
