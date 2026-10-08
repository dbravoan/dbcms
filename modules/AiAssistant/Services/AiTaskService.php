<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Services;

use App\Contracts\Services\ExecutionServiceInterface;
use App\Core\Capabilities\ActorContext;
use App\Core\Capabilities\CapabilityExecutor;
use App\Services\Capabilities\ActorContextFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\AiAssistant\Jobs\ProcessAiGeneration;
use Modules\AiAssistant\Models\AiGenerationTask;

final class AiTaskService
{
    public function __construct(private readonly CapabilityExecutor $executor, private readonly ActorContextFactory $actors, private readonly ExecutionServiceInterface $executions) {}

    /**
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    public function execute(string $action, array $params, ActorContext $actor): array
    {
        $name = array_search($action, AiActionRunner::MCP_TOOL_ACTIONS, true);

        if ($name === false) {
            return ['error' => 'Acción desconocida.'];
        }
        $result = $this->executor->execute($name, $params, $actor);
        if (! empty($result['confirmation_required'])) {
            $execution = $this->executions->startCapability($name, $params, $actor);
            $result['execution_id'] = $execution->id;
            $result['review_url'] = route('admin.executions.review', $execution->id);
        }

        return $result;
    }

    /**
     * @param  array<string,mixed>  $params
     */
    public function enqueue(Request $request, array $params): AiGenerationTask
    {
        $actor = $this->actors->fromRequest($request);
        $params['_actor'] = $actor->toArray();
        $task = AiGenerationTask::create(['id' => (string) Str::uuid(), 'user_id' => $actor->userId, 'action' => $params['action'], 'payload' => $params, 'status' => 'pending']);
        ProcessAiGeneration::dispatch((string) $task->id);

        return $task;
    }

    /**
     * @return array<string,mixed>
     */
    public function runTask(AiGenerationTask $task): array
    {
        if ($task->status === 'completed') {
            return (array) $task->result;
        }
        $payload = (array) $task->payload;
        if (! isset($payload['_actor']) || (int) ($payload['_actor']['userId'] ?? 0) !== (int) $task->user_id) {
            return ['error' => 'Identidad de tarea no válida.'];
        }
        $claimed = AiGenerationTask::whereKey($task->id)->whereIn('status', ['pending', 'pending_remote'])->update(['status' => 'processing']);
        if ($claimed !== 1) {
            return ['error' => 'La tarea ya está en curso; no se repetirá.', 'uncertain' => true];
        }
        $actor = ActorContext::fromArray((array) $payload['_actor']);
        unset($payload['_actor']);

        return $this->execute((string) $task->action, $payload, $actor);
    }
}
