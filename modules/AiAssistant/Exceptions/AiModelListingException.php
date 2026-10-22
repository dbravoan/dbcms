<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Exceptions;

use RuntimeException;

/**
 * Raised when a provider model catalog cannot be retrieved.
 */
class AiModelListingException extends RuntimeException
{
    public static function requestFailed(string $provider, int $status): self
    {
        return new self("Could not list models for provider [{$provider}] (HTTP {$status}).");
    }
}
