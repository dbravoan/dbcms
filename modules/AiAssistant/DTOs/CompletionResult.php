<?php

declare(strict_types=1);

namespace Modules\AiAssistant\DTOs;

/**
 * Immutable normalized completion returned by any provider adapter.
 */
final readonly class CompletionResult
{
    public function __construct(
        public string $content,
        public string $provider,
        public string $model,
        public int $promptTokens = 0,
        public int $completionTokens = 0,
    ) {}

    /**
     * @return array{content: string, provider: string, model: string, prompt_tokens: int, completion_tokens: int}
     */
    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'provider' => $this->provider,
            'model' => $this->model,
            'prompt_tokens' => $this->promptTokens,
            'completion_tokens' => $this->completionTokens,
        ];
    }
}
