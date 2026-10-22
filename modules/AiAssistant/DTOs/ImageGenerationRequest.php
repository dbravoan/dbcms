<?php

declare(strict_types=1);

namespace Modules\AiAssistant\DTOs;

final readonly class ImageGenerationRequest
{
    public function __construct(
        public string $prompt,
        public ?string $negativePrompt = null,
        public string $aspectRatio = '16:9',
        public string $resolution = '1080p',
        public string $style = 'photorealistic',
        public ?string $referenceImageUrl = null,
        public ?int $seed = null,
        /** @var array<string, mixed> */
        public array $extraParameters = [],
    ) {}
}
