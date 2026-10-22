<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Exceptions;

use RuntimeException;

/**
 * Raised when a provider rejects or cannot serve a chat completion request.
 */
class AiProviderException extends RuntimeException
{
    public static function requestFailed(string $provider, int $status, string $body): self
    {
        return new self("AI provider [{$provider}] returned HTTP {$status}.");
    }
}
