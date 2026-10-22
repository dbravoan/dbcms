<?php

declare(strict_types=1);

namespace Modules\AiAssistant\DTOs;

final readonly class GenerationResponse
{
    /** @param array<string,mixed> $data */
    public function __construct(public array $data) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        $needsInput = ($this->data['status'] ?? '') === 'needs_input';
        $payload = ['success' => ! $needsInput && ! isset($this->data['error']), 'data' => $this->data];
        if ($needsInput) {
            $payload += ['status' => 'needs_input', 'question' => $this->data['question'] ?? null, 'message' => $this->data['message'] ?? null];
        }

        return $payload;
    }
}
