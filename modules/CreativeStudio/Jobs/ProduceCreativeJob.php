<?php

declare(strict_types=1);

namespace Modules\CreativeStudio\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Modules\CreativeStudio\Services\CreativeStudioDomainService;

final class ProduceCreativeJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 720;

    public int $tries = 1;

    public function __construct(public readonly string $taskId, bool $manual = false)
    {
        $this->onConnection('marketing');
        $this->onQueue($manual ? 'creative-render' : 'creative-generation');
    }

    public function handle(CreativeStudioDomainService $service): void
    {
        $service->processGeneration($this->taskId);
    }
}
