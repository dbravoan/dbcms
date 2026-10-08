<?php

declare(strict_types=1);

namespace Modules\Mcp\Services;

use App\Core\Capabilities\AvailableCapabilities;
use App\Services\Capabilities\ActorContextFactory;
use App\Services\Capabilities\LegacyCmsService;
use Modules\Mcp\Tools\McpToolRegistry;

/** JSON-RPC adapter. Business operations always use the common executor.
 */
class McpServerService
{
    public const SUPPORTED_TOOLS = LegacyCmsService::SUPPORTED_TOOLS;

    public function __construct(private readonly McpToolRegistry $capabilities, private readonly McpToolDispatcher $dispatcher, private readonly AvailableCapabilities $available, private readonly ActorContextFactory $actors) {}

    public function supportsTool(string $toolName): bool
    {
        return $this->capabilities->has($toolName);
    }

    /**
     * @param  array<string,mixed>  $request
     * @return array<string,mixed>
     */
    public function handle(array $request): array
    {
        $response = ['jsonrpc' => '2.0', 'id' => $request['id'] ?? null];
        $actor = $this->actors->fromRequest(request(), 'mcp');
        $params = (array) ($request['params'] ?? []);
        switch ($request['method'] ?? '') {
            case 'tools/list':
                $tools = $this->available->manifest($actor);
                foreach ($tools as &$tool) {
                    $tool['inputSchema'] = $tool['parameters'];
                    unset($tool['parameters']);
                }
                unset($tool);
                $response['result'] = ['tools' => $tools];
                break;
            case 'tools/call':
                $result = $this->dispatcher->dispatch((string) ($params['name'] ?? ''), (array) ($params['arguments'] ?? []));
                $response['result'] = ['content' => [['type' => 'text', 'text' => json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)]]];
                break;
            default:
                $response['error'] = ['code' => -32601, 'message' => 'Method not found.'];
        }

        return $response;
    }
}
