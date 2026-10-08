<?php

declare(strict_types=1);

namespace Modules\AiAssistant\DTOs;

/**
 * Immutable outcome of a provider connectivity probe.
 */
final readonly class ConnectionResult
{
    public function __construct(
        public bool $success,
        public string $provider,
        public string $model,
        public string $message,
        public int $latencyMs = 0,
    ) {}

    /**
     * @return array{success: bool, status: string, provider: string, model: string, message: string, latency_ms: int}
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'status' => $this->success ? 'connected' : 'error',
            'provider' => $this->provider,
            'model' => $this->model,
            'message' => $this->message,
            'latency_ms' => $this->latencyMs,
        ];
    }
}
