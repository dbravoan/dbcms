<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Contracts;

use Modules\AiAssistant\DTOs\ImageGenerationRequest;
use Modules\AiAssistant\DTOs\ImageGenerationResult;

interface AiImageProviderInterface
{
    /**
     * Generate an image based on an artistic prompt and optional reference image.
     */
    public function generateImage(
        ImageGenerationRequest $request,
        string $apiKey,
        string $model,
        ?string $baseUrl = null
    ): ImageGenerationResult;

    /**
     * Upscale, enhance or relight an existing image.
     *
     * @param  array<string, mixed>  $options
     */
    public function upscaleImage(
        string $imageUrl,
        string $apiKey,
        array $options = [],
        ?string $baseUrl = null
    ): ImageGenerationResult;
}
