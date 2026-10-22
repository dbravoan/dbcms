<?php

declare(strict_types=1);

namespace Modules\SocialPublishing\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Modules\SocialPublishing\Services\DeliveryWorker;

final class ReconcileSocial implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 90;

    public function __construct(public readonly string $deliveryId, public readonly bool $metrics = false)
    {
        $this->onConnection('marketing')->onQueue('social-reconciliation');
    }

    public function handle(DeliveryWorker $worker): void
    {
        if ($this->metrics) {
            $worker->metrics($this->deliveryId);
        } else {
            $worker->reconcile($this->deliveryId);
        }
    }
}
