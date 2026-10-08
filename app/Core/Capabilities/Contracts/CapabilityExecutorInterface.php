<?php

declare(strict_types=1);

namespace App\Core\Capabilities\Contracts;

use App\Core\Capabilities\ActorContext;

interface CapabilityExecutorInterface
{
    /**
     * $approved is server authority resolved from persisted approvals, never transport input.
     *
     * @param  array<string,mixed>  $args
     * @return array<string,mixed>
     */
    public function execute(string $name, array $args, ActorContext $actor, bool $approved = false): array;
}
