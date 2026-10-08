<?php

declare(strict_types=1);

namespace Modules\Mcp\Services;

use App\Core\Capabilities\ActorContext;
use App\Core\Capabilities\CapabilityExecutor;
use App\Models\User;
use App\Services\Capabilities\ActorContextFactory;
use App\Services\Orchestration\ExecutionService;

/** Protocol adapter. A model-supplied confirm flag never grants authority.
 */
final class McpToolDispatcher
{
    public function __construct(private readonly CapabilityExecutor $executor, private readonly ActorContextFactory $actors, private readonly ExecutionService $executions) {}

    /**
     * @param  array<string,mixed>  $args
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    public function dispatch(string $name, array $args, array $context = []): array
    {
        $user = $context['user'] ?? null;
        $actor = $context === [] ? $this->actors->fromRequest(request(), 'mcp') : new ActorContext($user instanceof User ? (int) $user->getKey() : null, 'mcp', array_values(array_map('strval', (array) ($context['scopes'] ?? []))), $context['client_id'] ?? null, $context['token_id'] ?? null, (bool) ($context['is_m2m'] ?? false));
        unset($args['confirm'], $args['approval_id']);
        $result = $this->executor->execute($name, $args, $actor);
        if (! empty($result['confirmation_required'])) {
            $execution = $this->executions->startCapability($name, $args, $actor);

            return ['confirmation_required' => true, 'execution_id' => $execution->id, 'review_url' => route('admin.executions.review', $execution->id), 'execution' => $execution->toArray()];
        }

        return $result;
    }
}
