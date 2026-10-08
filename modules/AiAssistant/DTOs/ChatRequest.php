<?php

declare(strict_types=1);

namespace Modules\AiAssistant\DTOs;

/**
 * Immutable normalized chat request sent to any provider adapter.
 */
final readonly class ChatRequest
{
    public function __construct(
        public string $systemPrompt,
        public string $userPrompt,
        public float $temperature = 0.7,
        public bool $jsonMode = true,
    ) {}
}
