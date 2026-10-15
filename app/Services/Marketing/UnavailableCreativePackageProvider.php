<?php

declare(strict_types=1);

namespace App\Services\Marketing;

use App\Contracts\Services\CreativePackageProviderInterface;
use App\Core\Capabilities\ActorContext;
use App\DTOs\Marketing\CreativePackage;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class UnavailableCreativePackageProvider implements CreativePackageProviderInterface
{
    public function getPackage(string $revisionId, ActorContext $actor): CreativePackage
    {
        throw new HttpException(503, 'CreativeStudio no está disponible. Puede preparar el material manualmente.');
    }
}
