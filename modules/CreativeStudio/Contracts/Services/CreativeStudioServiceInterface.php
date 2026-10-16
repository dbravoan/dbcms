<?php

declare(strict_types=1);

namespace Modules\CreativeStudio\Contracts\Services;

use App\Core\Capabilities\ActorContext;
use Modules\CreativeStudio\Models\CreativeCampaign;
use Modules\CreativeStudio\Models\CreativeGenerationTask;
use Modules\CreativeStudio\Models\CreativeRevision;
use Modules\Marketing\Contracts\CreativePackageProviderInterface;

interface CreativeStudioServiceInterface extends CreativePackageProviderInterface
{
    /** @param array<string,mixed> $input */
    public function createCampaign(array $input, ActorContext $actor): CreativeCampaign;

    /** @param array<string,mixed> $input */
    public function createRevision(string $campaignId, array $input, ActorContext $actor): CreativeRevision;

    /** @param array<string,mixed> $input */
    public function enqueueGeneration(string $campaignId, array $input, ActorContext $actor): CreativeGenerationTask;

    /** @param array<string,mixed> $input */
    public function setDates(string $campaignId, array $input, ActorContext $actor): CreativeCampaign;

    public function processGeneration(string $taskId): void;

    public function resumeGeneration(string $taskId, ActorContext $actor): CreativeGenerationTask;
}
