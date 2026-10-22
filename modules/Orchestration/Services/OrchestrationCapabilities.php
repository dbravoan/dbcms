<?php

declare(strict_types=1);

namespace Modules\Orchestration\Services;

use App\Core\Capabilities\CallableCapability;
use App\Core\Capabilities\CapabilityRegistry;
use App\Core\Capabilities\ExecutionContext;
use App\Core\Modules\ModuleManager;
use App\Jobs\Orchestration\RunExecution;
use App\Services\Orchestration\ExecutionService;

final class OrchestrationCapabilities
{
    public function register(CapabilityRegistry $registry): void
    {
        foreach (['start', 'status', 'respond', 'resume', 'cancel'] as $operation) {
            $properties = ['execution_id' => ['type' => 'string'], 'instruction' => ['type' => 'string'], 'idempotency_key' => ['type' => 'string'], 'answer' => ['type' => 'string']];
            $required = match ($operation) {
                'start' => ['instruction', 'idempotency_key'], 'respond' => ['execution_id', 'answer'], default => ['execution_id']
            };
            $registry->register(new CallableCapability('cms_orchestration_'.$operation, 'Orquestación CMS: '.$operation, $properties, $required, 'use:ai', $operation === 'status' ? 'read' : 'write', static function (array $args) use ($operation): array {
                $actor = app(ExecutionContext::class)->actor ?? throw new \LogicException('Missing actor.');
                $service = app(ExecutionService::class);
                $id = (string) ($args['execution_id'] ?? '');
                $record = match ($operation) {
                    'start' => $service->start((string) $args['instruction'], $actor, (string) $args['idempotency_key']),
                    'status' => $service->owned($id, $actor),
                    'respond' => $service->answer($id, $actor, (string) $args['answer']),
                    'resume' => $service->resume($id, $actor),
                    'cancel' => $service->cancel($id, $actor),
                };
                if ($record->status === 'pending' && $operation !== 'status') {
                    RunExecution::dispatch((string) $record->id);
                }

                return $record->toArray();
            }), 'orchestration', effect: $operation === 'status' ? 'read' : 'draft', availability: static fn (): ?string => in_array($operation, ['start', 'respond'], true) && ! app(ModuleManager::class)->isModuleEnabled('orchestration') ? 'La entrada de instrucciones está desactivada.' : null);
        }
    }
}
