<?php

declare(strict_types=1);

namespace App\Core\Capabilities\Contracts;

interface PendingOperationInterface
{
    public function provider(): string;

    public function requestId(): string;
}
