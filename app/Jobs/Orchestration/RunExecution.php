<?php

declare(strict_types=1);

namespace App\Jobs\Orchestration;

use App\Contracts\Services\ExecutionServiceInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class RunExecution implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 1200;

    public function __construct(public readonly string $executionId) {}

    public function handle(ExecutionServiceInterface $executions): void
    {
        $executions->run($this->executionId);
    }
}
