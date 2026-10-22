<?php

declare(strict_types=1);

namespace Modules\AiAssistant\DTOs;

final readonly class VideoGenerationRequest
{
    public function __construct(
        public string $prompt,
        public ?string $motionPrompt = null,
        public ?string $referenceImageUrl = null,
        public int $durationSeconds = 5,
        public string $aspectRatio = '16:9',
        public int $fps = 24,
        public string $cameraMotion = 'cinematic_push',
        public ?int $seed = null,
        /** @var array<string, mixed> */
        public array $extraParameters = [],
    ) {}
}
