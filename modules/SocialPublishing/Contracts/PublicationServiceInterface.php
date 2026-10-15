<?php

declare(strict_types=1);

namespace Modules\SocialPublishing\Contracts;

use App\Core\Capabilities\ActorContext;
use App\DTOs\Marketing\CreativePackage;
use Modules\SocialPublishing\Models\SocialDelivery;
use Modules\SocialPublishing\Models\SocialPublication;

interface PublicationServiceInterface
{
    /** @param array<string,mixed> $metadata */
    public function create(CreativePackage $package, string $accountId, array $metadata, ActorContext $actor): SocialPublication;

    /** @param array<string,mixed> $metadata */
    public function revise(string $id, string $expectedRevision, CreativePackage $package, string $accountId, array $metadata, ActorContext $actor): SocialPublication;

    public function approve(string $id, string $expectedHash, ActorContext $actor): SocialPublication;

    public function approveAndSchedule(string $id, string $hash, string $at, ActorContext $actor): SocialPublication;

    public function schedule(string $id, string $at, ActorContext $actor): SocialDelivery;

    public function cancel(string $deliveryId, ActorContext $actor): void;
}
