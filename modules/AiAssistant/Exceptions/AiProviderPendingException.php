<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Exceptions;

use App\Core\Capabilities\Contracts\PendingOperationInterface;

final class AiProviderPendingException extends AiProviderException implements PendingOperationInterface
{
    public function provider(): string
    {
        return $this->provider;
    }

    public function requestId(): string
    {
        return $this->requestId;
    }

    public function __construct(public readonly string $provider, public readonly string $requestId)
    {
        parent::__construct('Generation is still pending; resume the existing provider request.');
    }
}
