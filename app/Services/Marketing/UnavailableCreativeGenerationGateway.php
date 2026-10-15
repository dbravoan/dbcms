<?php

declare(strict_types=1);

namespace App\Services\Marketing;

use App\Contracts\Services\CreativeGenerationGatewayInterface;
use Illuminate\Validation\ValidationException;

final class UnavailableCreativeGenerationGateway implements CreativeGenerationGatewayInterface
{
    public function assertAvailable(string $modality, string $provider, ?string $model = null): void
    {
        throw ValidationException::withMessages(['provider' => 'No hay un proveedor de generación disponible.']);
    }

    public function generate(string $modality, string $prompt, string $provider, ?string $model, array $options, string $operationId): array
    {
        $this->assertAvailable($modality, $provider, $model);
        throw new \LogicException('Generation is unavailable.');
    }
}
