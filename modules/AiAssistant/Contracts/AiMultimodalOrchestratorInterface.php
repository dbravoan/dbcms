<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Contracts;

use Modules\AiAssistant\DTOs\MultimodalOrchestrationRequest;
use Modules\AiAssistant\DTOs\MultimodalOrchestrationResult;

interface AiMultimodalOrchestratorInterface
{
    /**
     * Generate an image from explicit prompt OR by analyzing entity content (content-aware).
     *
     * @param  array<string, mixed>  $options
     * @return array{url: string, media_file_id: int|string|null, prompt: string, provider: string, model: string}
     */
    public function generateImage(
        string $prompt = '',
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $content = null,
        ?string $title = null,
        array $options = []
    ): array;

    /**
     * Generate a video from explicit prompt OR by summarizing entity content (content-aware teaser).
     *
     * @param  array<string, mixed>  $options
     * @return array{url: string, media_file_id: int|string|null, poster_url: ?string, duration_seconds: int, provider: string, model: string}
     */
    public function generateVideo(
        string $prompt = '',
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $content = null,
        ?string $title = null,
        array $options = []
    ): array;

    /**
     * Orchestrate end-to-end creation of a Post entity with text, image, video and blocks.
     */
    public function orchestratePost(MultimodalOrchestrationRequest $request): MultimodalOrchestrationResult;

    /**
     * Orchestrate end-to-end creation of a Page entity with text, visual hero, and structured blocks.
     */
    public function orchestratePage(MultimodalOrchestrationRequest $request): MultimodalOrchestrationResult;
}
