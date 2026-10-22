<?php

declare(strict_types=1);

namespace App\Core\Capabilities\Contracts;

use App\Core\Capabilities\ActorContext;
use App\Core\Capabilities\DTOs\ExecutionPlan;

interface PlannerInterface
{
    /**
     * @param  array<string,mixed>  $context
     */
    public function plan(string $instruction, ActorContext $actor, array $context = []): ExecutionPlan;
}
