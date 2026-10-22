<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Contracts;

use Modules\AiAssistant\DTOs\VideoGenerationRequest;
use Modules\AiAssistant\DTOs\VideoGenerationResult;

interface AiVideoProviderInterface
{
    /**
     * Generate a cinematic video clip from text prompt or image-to-video seed frame.
     */
    public function generateVideo(
        VideoGenerationRequest $request,
        string $apiKey,
        string $model,
        ?string $baseUrl = null
    ): VideoGenerationResult;
}
