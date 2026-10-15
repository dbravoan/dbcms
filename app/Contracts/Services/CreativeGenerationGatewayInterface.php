<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\DTOs\Marketing\ArtifactDescriptor;
use App\DTOs\Marketing\GenerationReceipt;

interface CreativeGenerationGatewayInterface
{
    public function assertAvailable(string $modality, string $provider, ?string $model = null): void;

    /**
     * @param  array<string,mixed>  $options
     * @return array{receipt:GenerationReceipt,artifact:?ArtifactDescriptor,text:?string,pending:bool}
     */
    public function generate(string $modality, string $prompt, string $provider, ?string $model, array $options, string $operationId): array;
}
