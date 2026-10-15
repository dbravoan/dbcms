<?php

declare(strict_types=1);

namespace Modules\SocialPublishing\Services;

use App\Contracts\Services\MarketingArtifactStoreInterface;
use App\Core\Capabilities\ActorContext;
use App\DTOs\Marketing\CreativePackage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\SocialPublishing\Contracts\PublicationServiceInterface;
use Modules\SocialPublishing\DTOs\PublicationSnapshot;
use Modules\SocialPublishing\Models\SocialAccount;
use Modules\SocialPublishing\Models\SocialApproval;
use Modules\SocialPublishing\Models\SocialDelivery;
use Modules\SocialPublishing\Models\SocialPublication;
use Modules\SocialPublishing\Models\SocialRevision;

final readonly class PublicationService implements PublicationServiceInterface
{
    public function __construct(private SocialAuthorization $auth, private MarketingArtifactStoreInterface $files, private ConnectorRegistry $connectors) {}

    /** @param array<string,mixed> $metadata */
    public function create(CreativePackage $package, string $accountId, array $metadata, ActorContext $actor): SocialPublication
    {
        $user = $this->auth->authorize($actor);
        $id = (string) Str::uuid();
        $owned = $this->copyPackage($package, $id);

        return DB::transaction(function () use ($id, $owned, $accountId, $metadata, $user, $actor): SocialPublication {
            $account = SocialAccount::whereKey($accountId)->lockForUpdate()->firstOrFail();
            $p = SocialPublication::create(['id' => $id, 'account_id' => $account->id, 'owner_id' => $user->id, 'owner_actor' => $actor->toArray(), 'installation_id' => $this->auth->installation()]);

            return $this->seal($p, $owned, $account, $metadata);
        });
    }

    /** @param array<string,mixed> $metadata */
    public function revise(string $id, string $expectedRevision, CreativePackage $package, string $accountId, array $metadata, ActorContext $actor): SocialPublication
    {
        $this->auth->authorize($actor);
        $owned = $this->copyPackage($package, $id);

        return DB::transaction(function () use ($id, $expectedRevision, $owned, $accountId, $metadata): SocialPublication {
            $p = SocialPublication::whereKey($id)->lockForUpdate()->firstOrFail();
            abort_unless($p->installation_id === $this->auth->installation(), 404);
            $this->require($p->revision_id === $expectedRevision, 'La revisión cambió. Actualiza la vista previa.');
            foreach (SocialDelivery::where('publication_id', $id)->lockForUpdate()->get() as $d) {
                $d->cancel_requested = true;
                if (in_array($d->status, ['scheduled', 'paused', 'blocked'], true)) {
                    $d->status = 'cancelled';
                }
                $d->save();
            }
            $account = SocialAccount::whereKey($accountId)->lockForUpdate()->firstOrFail();

            return $this->seal($p, $owned, $account, $metadata);
        });
    }

    private function copyPackage(CreativePackage $package, string $owner): CreativePackage
    {
        $files = [];
        foreach ($package->artifacts as $file) {
            $files[] = $this->files->copy($file, 'social/'.$this->auth->installation().'/'.$owner);
        }

        return new CreativePackage($package->packageId, $package->revisionId, $package->campaignId, $package->locale, $package->market, $package->text, $package->destinationUrl, $files, $package->provenance, $package->version);
    }

    /** @param array<string,mixed> $metadata */
    private function seal(SocialPublication $p, CreativePackage $package, SocialAccount $account, array $metadata): SocialPublication
    {
        $this->connectors->get($account->provider)->validate($package, $metadata);
        $snapshot = new PublicationSnapshot($package, $account->identity, $account->authorization_generation, $metadata);
        $revision = SocialRevision::create(['publication_id' => $p->id, 'number' => (int) SocialRevision::where('publication_id', $p->id)->max('number') + 1, 'package' => $package->toArray(), 'metadata' => $metadata, 'account_identity' => $account->identity, 'account_generation' => $account->authorization_generation, 'payload_hash' => $snapshot->hash()]);
        $p->forceFill(['account_id' => $account->id, 'revision_id' => $revision->id, 'approved_revision_id' => null, 'payload_hash' => $revision->payload_hash, 'status' => 'draft'])->save();

        return $p;
    }

    public function approve(string $id, string $expectedHash, ActorContext $actor): SocialPublication
    {
        $user = $this->auth->authorize($actor, 'approve:social-publishing', true);

        return DB::transaction(function () use ($id, $expectedHash, $user): SocialPublication {
            $p = SocialPublication::whereKey($id)->lockForUpdate()->firstOrFail();
            $this->require($p->installation_id === $this->auth->installation() && hash_equals($p->payload_hash, $expectedHash), 'La vista previa ya no corresponde a esta revisión.');
            $r = SocialRevision::findOrFail($p->revision_id);
            $account = SocialAccount::whereKey($p->account_id)->lockForUpdate()->firstOrFail();
            $this->require($this->validRevision($r, $account), 'Los archivos o la cuenta han cambiado.');
            SocialApproval::firstOrCreate(['revision_id' => $r->id], ['payload_hash' => $r->payload_hash, 'approved_by' => $user->id, 'origin' => 'web', 'account_identity' => $account->identity, 'account_generation' => $account->authorization_generation]);
            $p->forceFill(['approved_revision_id' => $r->id, 'status' => 'approved'])->save();

            return $p;
        });
    }

    public function schedule(string $id, string $at, ActorContext $actor): SocialDelivery
    {
        $this->auth->authorize($actor);
        $when = app(ScheduleTime::class)->parse($at);
        $this->require($when->gte(now()->subSeconds(5)), 'Elige una fecha actual o futura.');

        return DB::transaction(function () use ($id, $when, $actor): SocialDelivery {
            $p = SocialPublication::whereKey($id)->lockForUpdate()->firstOrFail();
            $this->require($this->approved($p), 'La programación requiere una aprobación exacta vigente.');
            $d = SocialDelivery::where('revision_id', $p->revision_id)->lockForUpdate()->first();
            if ($d) {
                $this->require(in_array($d->status, ['scheduled', 'paused', 'blocked'], true) && $d->attempts === 0, 'El envío ya se inició; no puede volver a programarse.');
            }
            $d ??= new SocialDelivery(['publication_id' => $p->id, 'revision_id' => $p->revision_id, 'installation_id' => $p->installation_id, 'receipt' => []]);
            $d->forceFill(['status' => 'scheduled', 'due_at' => $when, 'scheduled_at' => $when, 'cancel_requested' => false, 'claim' => null, 'lease_until' => null, 'scheduler_actor' => $actor->toArray()])->save();

            return $d;
        });
    }

    public function cancel(string $deliveryId, ActorContext $actor): void
    {
        $this->auth->authorize($actor);
        DB::transaction(function () use ($deliveryId): void {
            $d = SocialDelivery::whereKey($deliveryId)->lockForUpdate()->firstOrFail();
            abort_unless($d->installation_id === $this->auth->installation(), 404);
            $d->cancel_requested = true;
            if (in_array($d->status, ['scheduled', 'paused', 'blocked'], true)) {
                $d->status = 'cancelled';
            }
            $d->save();
        });
    }

    public function approveAndSchedule(string $id, string $hash, string $at, ActorContext $actor): SocialPublication
    {
        return DB::transaction(function () use ($id, $hash, $at, $actor): SocialPublication {
            $p = $this->approve($id, $hash, $actor);
            $this->schedule($id, $at, $actor);

            return $p;
        });
    }

    public function approved(SocialPublication $p): bool
    {
        $a = SocialApproval::where('revision_id', $p->revision_id)->first();
        $r = SocialRevision::find($p->revision_id);
        $c = SocialAccount::find($p->account_id);

        return $a && $r && $c && $p->installation_id === $this->auth->installation() && $p->approved_revision_id === $r->id && $a->origin === 'web' && $a->payload_hash === $r->payload_hash && $this->auth->liveUser($a->approved_by, 'approve:social-publishing') && $this->auth->liveUser($p->owner_id, 'manage:social-publishing') && $this->auth->liveActor(ActorContext::fromArray($p->owner_actor), 'manage:social-publishing') && $this->validRevision($r, $c);
    }

    public function validRevision(SocialRevision $r, SocialAccount $c): bool
    {
        $package = CreativePackage::fromArray($r->package);
        foreach ($package->artifacts as $file) {
            if (! $this->files->verify($file)) {
                return false;
            }
        }

        return $r->account_identity === $c->identity && $r->account_generation === $c->authorization_generation && hash_equals($r->payload_hash, (new PublicationSnapshot($package, $c->identity, $c->authorization_generation, $r->metadata))->hash());
    }

    private function require(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['publication' => $message]);
        }
    }
}
