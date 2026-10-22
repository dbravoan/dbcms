<?php

declare(strict_types=1);

namespace Modules\CreativeStudio\Services;

use App\Core\Capabilities\ActorContext;
use App\Core\Capabilities\CallableCapability;
use App\Core\Capabilities\CapabilityRegistry;
use App\Core\Capabilities\ExecutionContext;
use Illuminate\Auth\Access\AuthorizationException;
use Modules\CreativeStudio\Models\CreativeCampaign;
use Modules\CreativeStudio\Models\CreativeGenerationTask;

final class CreativeCapabilityRegistrar
{
    public function register(CapabilityRegistry $registry): void
    {
        $operations = ['sources_read' => 'read', 'dates_update' => 'write', 'budget_update' => 'write', 'campaigns_read' => 'read', 'campaign_create' => 'write', 'brief_update' => 'write', 'brand_update' => 'write', 'revision_create' => 'write', 'generation_enqueue' => 'write', 'composition_enqueue' => 'write', 'task_read' => 'read', 'task_resume' => 'write', 'task_cancel' => 'write', 'task_reconcile' => 'write', 'package_read' => 'read', 'export_read' => 'read'];
        $properties = ['campaign_id' => ['type' => 'string'], 'revision_id' => ['type' => 'string'], 'task_id' => ['type' => 'string'], 'input' => ['type' => 'object']];
        foreach ($operations as $action => $ability) {
            $required = match ($action) {
                'campaigns_read' => [],'sources_read' => ['input'],'campaign_create' => ['input'],'package_read','export_read' => ['revision_id'],'task_read','task_resume','task_cancel','task_reconcile' => ['task_id'],default => ['campaign_id', 'input']
            };
            $registry->register(new CallableCapability('cms_creative_'.$action, 'CreativeStudio: '.$action, $properties, $required, 'manage:creative-studio', $ability, fn (array $args): array => $this->run($action, $args)), 'creative-studio', $action === 'generation_enqueue' ? 'generation' : ($ability === 'read' ? 'read' : 'draft'));
        }
    }

    /**
     * @param  array<string,mixed>  $args
     * @return array<string,mixed>
     */
    private function run(string $action, array $args): array
    {
        $actor = app(ExecutionContext::class)->actor;
        if (! $actor instanceof ActorContext) {
            throw new AuthorizationException('Falta una identidad de ejecución.');
        }
        $service = app(CreativeStudioDomainService::class);
        app(CreativeStudioAccess::class)->check($actor, str_ends_with($action, '_read') ? 'read' : 'write');
        $input = (array) ($args['input'] ?? []);
        $campaign = (string) ($args['campaign_id'] ?? '');
        $taskId = (string) ($args['task_id'] ?? '');
        $revision = (string) ($args['revision_id'] ?? '');

        return match ($action) {
            'sources_read' => ['data' => $service->sourceChoices((string) ($input['locale'] ?? ''), (int) ($input['market_id'] ?? 0), $actor)],
            'dates_update' => ['data' => $service->setDates($campaign, $input, $actor)->toArray()],
            'budget_update' => ['data' => $service->setBudget($campaign, $input, $actor)->toArray()],
            'campaigns_read' => ['data' => CreativeCampaign::with(['translations', 'markets'])->latest()->limit(100)->get()->toArray()],
            'campaign_create' => ['data' => $service->createCampaign($input, $actor)->toArray()],
            'brief_update' => ['data' => $service->setBrief($campaign, $input, $actor)->toArray()],
            'brand_update' => ['data' => $service->setBrand($campaign, $input, $actor)->toArray()],
            'revision_create' => ['data' => $service->createRevision($campaign, $input, $actor)->toArray()],
            'generation_enqueue' => ['data' => app(CreativeStudioHttpService::class)->taskData($service->enqueueGeneration($campaign, $input, $actor))],
            'composition_enqueue' => ['data' => app(CreativeStudioHttpService::class)->taskData($service->enqueueComposition($campaign, $input, $actor))],
            'task_read' => ['data' => app(CreativeStudioHttpService::class)->taskData(CreativeGenerationTask::findOrFail($taskId))],
            'task_resume' => ['data' => app(CreativeStudioHttpService::class)->taskData($service->resumeGeneration($taskId, $actor))],
            'task_cancel' => ['data' => app(CreativeStudioHttpService::class)->taskData($service->cancelGeneration($taskId, $actor))],
            'task_reconcile' => ['data' => app(CreativeStudioHttpService::class)->taskData($service->reconcileGeneration($taskId, $actor))],
            'package_read' => ['data' => $service->getPackage($revision, $actor)->toArray()],
            'export_read' => ['revision_id' => $service->getPackage($revision, $actor)->revisionId, 'download_url' => route('api.creative-studio.export', $revision), 'requires' => 'Bearer token with read:creative-studio'],
            default => throw new \InvalidArgumentException('Acción CreativeStudio no reconocida.'),
        };
    }
}
