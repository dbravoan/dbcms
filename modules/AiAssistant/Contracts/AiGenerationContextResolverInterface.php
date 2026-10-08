<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Contracts;

use Modules\AiAssistant\DTOs\GenerationContext;

interface AiGenerationContextResolverInterface
{
    /** @param array<string,mixed> $params */
    public function resolve(string $action, array $params): GenerationContext;
}
