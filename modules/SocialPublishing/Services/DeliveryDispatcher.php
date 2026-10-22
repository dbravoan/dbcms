<?php

declare(strict_types=1);

namespace Modules\SocialPublishing\Services;

use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Support\Facades\Cache;
use Modules\SocialPublishing\Jobs\DeliverSocial;
use Modules\SocialPublishing\Jobs\ReconcileSocial;
use Modules\SocialPublishing\Models\SocialDelivery;

final readonly class DeliveryDispatcher
{
    public function __construct(private SocialAuthorization $auth, private DeliveryWorker $worker) {}

    public function dispatch(bool $metrics = false): int
    {
        if (! $this->auth->enabled()) {
            $this->worker->pause();

            return 0;
        }
        $store = Cache::store('marketing')->getStore();
        if (! $store instanceof LockProvider) {
            throw new \LogicException('La cola social requiere un cache con locks.');
        }

        return (int) $store->lock('social-dispatch:'.$this->auth->installation(), 50)->get(function () use ($metrics): int {
            $query = SocialDelivery::where('installation_id', $this->auth->installation());
            if ($metrics) {
                $query->where('status', 'published')->where('due_at', '<=', now());
            } else {
                $query->whereIn('status', ['scheduled', 'preparing', 'remote_processing', 'ready_to_publish'])->where('due_at', '<=', now())->where(fn ($q) => $q->whereNull('lease_until')->orWhere('lease_until', '<=', now()));
            }
            $rows = $query->orderBy('due_at')->limit(20)->get();
            foreach ($rows as $d) {
                if ($metrics) {
                    ReconcileSocial::dispatch($d->id, true);
                } else {
                    DeliverSocial::dispatch($d->id);
                }
            }

            return $rows->count();
        });
    }
}
