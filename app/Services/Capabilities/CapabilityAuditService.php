<?php

declare(strict_types=1);

namespace App\Services\Capabilities;

use App\Core\Capabilities\Contracts\CapabilityAuditInterface;
use App\Services\Capabilities\CapabilityAuditLogger as McpAuditLogger;

final readonly class CapabilityAuditService implements CapabilityAuditInterface
{
    public function __construct(private McpAuditLogger $logger) {}

    public function log(array $context): void
    {
        $this->logger->log($context);
    }
}
