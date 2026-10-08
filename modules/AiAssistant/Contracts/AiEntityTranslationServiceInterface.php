<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Contracts;

use Modules\AiAssistant\DTOs\EntityTranslationRequest;

interface AiEntityTranslationServiceInterface
{
    /** @return array<string,mixed> */
    public function translate(EntityTranslationRequest $request): array;
}
