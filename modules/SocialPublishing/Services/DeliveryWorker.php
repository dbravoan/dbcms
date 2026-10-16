<?php

declare(strict_types=1);

namespace Modules\SocialPublishing\Services;

use App\Core\Capabilities\ActorContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Marketing\Contracts\CampaignAttributionGatewayInterface;
use Modules\Marketing\DTOs\CreativePackage;
use Modules\SocialPublishing\Contracts\DestinationValidatorInterface;
use Modules\SocialPublishing\DTOs\OperationResult;
use Modules\SocialPublishing\Models\SocialAccount;
use Modules\SocialPublishing\Models\SocialAttempt;
use Modules\SocialPublishing\Models\SocialDelivery;
use Modules\SocialPublishing\Models\SocialMetric;
use Modules\SocialPublishing\Models\SocialPublication;
use Modules\SocialPublishing\Models\SocialRevision;

final readonly class DeliveryWorker
{
    public function __construct(private PublicationService $publications, private SocialAuthorization $auth, private ConnectorRegistry $connectors, private DestinationValidatorInterface $destinations, private SocialOAuthService $oauth, private CampaignAttributionGatewayInterface $attribution) {}

    public function run(string $id): void
    {
        $claim = DB::transaction(function () use ($id): ?string {
            $d = SocialDelivery::whereKey($id)->lockForUpdate()->first();
            if (! $d || $d->installation_id !== $this->auth->installation() || in_array($d->status, ['published', 'failed', 'cancelled', 'pending_confirmation', 'paused', 'blocked'], true) || $d->due_at->isFuture()) {
                return null;
            }
            if ($d->claim !== null) {
                if ($d->lease_until?->isFuture()) {
                    return null;
                }
                $attempt = SocialAttempt::where('operation_key', $d->claim)->where('status', 'started')->first();
                if ($attempt?->mutation) {
                    $d->forceFill(['status' => 'pending_confirmation', 'error_code' => 'worker_lost_after_external_intent', 'claim' => null, 'lease_until' => null])->save();

                    return null;
                }
            }
            $p = SocialPublication::whereKey($d->publication_id)->lockForUpdate()->firstOrFail();
            $r = SocialRevision::findOrFail($d->revision_id);
            $account = SocialAccount::findOrFail($p->account_id);
            $connector = $this->connectors->get($account->provider);
            $mutation = $connector->mutation($d->step);
            if (! $this->auth->enabled()) {
                $d->forceFill(['status' => 'paused', 'claim' => null, 'lease_until' => null, 'error_code' => 'module_unavailable_reschedule_required'])->save();

                return null;
            }
            if ($mutation && $d->cancel_requested) {
                $d->forceFill(['status' => 'cancelled', 'error_code' => 'remote_review_may_be_required'])->save();

                return null;
            }
            if ($mutation && (! $this->publications->approved($p) || $p->revision_id !== $r->id || ! $this->auth->liveActor(ActorContext::fromArray($d->scheduler_actor), 'manage:social-publishing'))) {
                $d->forceFill(['status' => 'blocked', 'error_code' => 'approval_or_authority_changed'])->save();

                return null;
            }
            $claim = (string) Str::uuid();
            $d->forceFill(['claim' => $claim, 'lease_until' => now()->addMinutes(3), 'status' => $mutation ? 'preparing' : 'remote_processing', 'attempts' => $d->attempts + 1])->save();
            SocialAttempt::create(['delivery_id' => $id, 'operation_key' => $claim, 'step' => $d->step, 'status' => 'started', 'mutation' => $mutation, 'receipt' => []]);

            return $claim;
        });
        if ($claim === null) {
            return;
        }
        $d = SocialDelivery::findOrFail($id);
        $p = SocialPublication::findOrFail($d->publication_id);
        $r = SocialRevision::findOrFail($d->revision_id);
        $account = SocialAccount::findOrFail($p->account_id);
        $connector = $this->connectors->get($account->provider);
        $package = CreativePackage::fromArray($r->package);
        try {
            if ($connector->mutation($d->step)) {
                $this->destinations->check($package, ActorContext::fromArray($p->owner_actor));
                $this->oauth->ready($account);
                $connector->validate($package, $r->metadata);
                $account = $this->mutationFence($id, $claim, $r->id, $r->payload_hash);
            }
            $receipt = $d->receipt ?? [];
            if ($account->provider === 'instagram' && ! isset($receipt['asset_urls'])) {
                $receipt['asset_urls'] = array_map(fn (int $index): string => URL::temporarySignedRoute('social-publishing.asset', now()->addHours(6), ['delivery' => $d->id, 'index' => $index]), array_keys($package->artifacts));
            }
            $result = $connector->step($d->step, $account, $package, $receipt, $r->metadata);
        } catch (ValidationException) {
            $result = new OperationResult('blocked', error: 'preflight_approval_destination_or_format_changed');
        } catch (\Throwable) {
            $result = new OperationResult($connector->mutation($d->step) ? 'pending_confirmation' : 'remote_processing', $connector->mutation($d->step) ? null : $d->step, error: 'provider_unavailable_or_result_uncertain', retryAfter: 120);
        }
        $this->settle($id, $claim, $result);
    }

    private function mutationFence(string $id, string $claim, string $revision, string $hash): SocialAccount
    {
        return DB::transaction(function () use ($id, $claim, $revision, $hash): SocialAccount {
            $d = SocialDelivery::whereKey($id)->lockForUpdate()->firstOrFail();
            $p = SocialPublication::whereKey($d->publication_id)->lockForUpdate()->firstOrFail();
            $r = SocialRevision::findOrFail($d->revision_id);
            $c = SocialAccount::whereKey($p->account_id)->lockForUpdate()->firstOrFail();
            $valid = $d->claim === $claim && $d->lease_until?->isFuture() && ! $d->cancel_requested && $this->auth->enabled()
                && $d->revision_id === $revision && $p->revision_id === $revision && hash_equals($r->payload_hash, $hash)
                && $c->identity === $r->account_identity && $c->authorization_generation === $r->account_generation
                && $this->publications->approved($p) && $this->auth->liveActor(ActorContext::fromArray($d->scheduler_actor), 'manage:social-publishing');
            if (! $valid) {
                throw ValidationException::withMessages(['approval' => 'La revisión, la cuenta, el claim o sus permisos cambiaron antes del envío.']);
            }

            return $c;
        });
    }

    private function settle(string $id, string $claim, OperationResult $result): void
    {
        DB::transaction(function () use ($id, $claim, $result): void {
            $attempt = SocialAttempt::where('operation_key', $claim)->lockForUpdate()->firstOrFail();
            $attempt->forceFill(['status' => $result->state, 'receipt' => $result->receipt, 'error_code' => $result->error])->save();
            $d = SocialDelivery::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($d->claim !== $claim) {
                return;
            }
            $receipt = array_replace($d->receipt ?? [], $result->receipt);
            if ($result->remoteId !== null) {
                $receipt['id'] = $result->remoteId;
            }
            $status = $result->state === 'retry_safe' ? 'scheduled' : $result->state;
            if ($result->state === 'retry_safe' && $d->attempts >= 4) {
                $status = 'failed';
            }
            if ($d->cancel_requested && ! in_array($status, ['published', 'pending_confirmation', 'remote_processing'], true)) {
                $status = 'cancelled';
            }
            $d->forceFill(['status' => $status, 'step' => $result->nextStep ?? $d->step, 'remote_id' => $result->remoteId ?? $d->remote_id, 'receipt' => $receipt, 'error_code' => $result->error, 'due_at' => now()->addSeconds($result->retryAfter), 'claim' => null, 'lease_until' => null])->save();
        });
    }

    public function reconcile(string $id, ?string $candidate = null): void
    {
        $d = SocialDelivery::findOrFail($id);
        if ($d->installation_id !== $this->auth->installation() || $d->status !== 'pending_confirmation' || ($candidate !== null && $candidate !== ($d->receipt['id'] ?? $d->receipt['video_id'] ?? null))) {
            throw ValidationException::withMessages(['reconciliation' => 'Un identificador ajeno o sin correlación no confirma este intento.']);
        }
        $p = SocialPublication::findOrFail($d->publication_id);
        $c = SocialAccount::findOrFail($p->account_id);
        $r = SocialRevision::findOrFail($d->revision_id);
        abort_unless($this->auth->liveUser($p->owner_id, 'manage:social-publishing') && $c->identity === $r->account_identity && $c->authorization_generation === $r->account_generation, 403);
        $knownLinkedIn = $c->provider === 'linkedin' && (isset($d->receipt['images']) || isset($d->receipt['video']));
        if (! isset($d->receipt['id']) && ! isset($d->receipt['session']) && ! isset($d->receipt['video_id']) && ! $knownLinkedIn) {
            return;
        }
        $step = $c->provider === 'youtube' ? 'reconcile' : 'confirm';
        if ($knownLinkedIn) {
            if (str_starts_with($d->step, 'image-upload:')) {
                $step = 'poll:image:'.substr($d->step, 13);
            } elseif ($d->step === 'video-finalize' || str_starts_with($d->step, 'video-part:')) {
                $step = 'poll:video';
            }
            // An uncertain post-creation response has no correlated post ID; asset ownership cannot settle it.
            elseif ($d->step === 'publish' || $d->step === 'prepare' || str_contains($d->step, 'init')) {
                return;
            }
        }
        $connector = $this->connectors->get($c->provider);
        if ($connector->mutation($step)) {
            return;
        }
        $result = $connector->step($step, $c, CreativePackage::fromArray($r->package), $d->receipt, $r->metadata);
        DB::transaction(function () use ($id, $result): void {
            $d = SocialDelivery::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($d->status !== 'pending_confirmation') {
                return;
            }
            if ($result->state === 'published') {
                $d->forceFill(['status' => 'published', 'remote_id' => $result->remoteId, 'receipt' => array_replace($d->receipt, $result->receipt), 'error_code' => null])->save();
            } elseif ($result->nextStep !== null && $result->state !== 'pending_confirmation') {
                // Read-only reconciliation may resolve upload progress while disabled; never resume writes automatically.
                $d->forceFill(['status' => $this->auth->enabled() && ! $d->cancel_requested ? $result->state : 'paused', 'step' => $result->nextStep, 'receipt' => array_replace($d->receipt, $result->receipt), 'due_at' => now(), 'error_code' => 'reconciled_progress'])->save();
            }
        });
    }

    public function metrics(string $id): void
    {
        $d = SocialDelivery::findOrFail($id);
        if ($d->installation_id !== $this->auth->installation()) {
            return;
        }
        $p = SocialPublication::findOrFail($d->publication_id);
        $c = SocialAccount::findOrFail($p->account_id);
        if (! $this->auth->enabled() || $d->status !== 'published' || ! $d->remote_id || ! $this->auth->liveActor(ActorContext::fromArray($p->owner_actor), 'read:social-publishing')) {
            return;
        }
        try {
            $this->oauth->ready($c);
            $metrics = $this->connectors->get($c->provider)->metrics($c, $d->remote_id);
        } catch (\Throwable) {
            $metrics = ['source' => $c->provider, 'availability' => 'unavailable', 'values' => [], 'reason' => 'provider_or_permissions_unavailable'];
        }
        DB::transaction(fn () => SocialMetric::create(['delivery_id' => $id, 'metrics' => $metrics, 'source' => (string) ($metrics['source'] ?? $c->provider), 'availability' => (string) ($metrics['availability'] ?? 'unavailable'), 'observed_at' => now()]));
        $package = CreativePackage::fromArray(SocialRevision::findOrFail($d->revision_id)->package);
        if ($package->destinationUrl !== null) {
            $utm = [];
            parse_str((string) parse_url($package->destinationUrl, PHP_URL_QUERY), $utm);
            $campaign = isset($utm['utm_campaign']) && is_string($utm['utm_campaign']) ? $utm['utm_campaign'] : ($package->campaignId ?? $package->packageId);
            $content = isset($utm['utm_content']) && is_string($utm['utm_content']) ? $utm['utm_content'] : $package->revisionId;
            $from = $d->scheduled_at?->utc()->toDateString() ?? now()->subDays(30)->toDateString();
            try {
                $traffic = $this->attribution->report($campaign, $content, $from, now()->utc()->toDateString());
            } catch (\Throwable) {
                $traffic = ['source' => 'ga4-data-api', 'availability' => 'unavailable', 'reason' => 'reporting_gateway_unavailable', 'sessions' => null, 'keyEvents' => null];
            }
            DB::transaction(fn () => SocialMetric::create(['delivery_id' => $id, 'metrics' => $traffic + ['campaign' => $campaign, 'content' => $content, 'dateFrom' => $from, 'dateTo' => now()->utc()->toDateString(), 'freshness' => (string) ($traffic['status'] ?? 'unavailable'), 'limitations' => ['consentimiento_retraso_de_procesamiento_y_eventos_configurados']], 'source' => (string) ($traffic['source'] ?? 'ga4-data-api'), 'availability' => (string) ($traffic['availability'] ?? $traffic['status'] ?? 'unavailable'), 'observed_at' => now()]));
        }
        DB::transaction(fn () => $d->forceFill(['due_at' => now()->addHour()])->save());
    }

    public function pause(): void
    {
        DB::transaction(fn () => SocialDelivery::where('installation_id', $this->auth->installation())->whereIn('status', ['scheduled', 'preparing', 'ready_to_publish', 'remote_processing'])->whereNull('claim')->update(['status' => 'paused', 'error_code' => 'module_unavailable_reschedule_required']));
    }
}
