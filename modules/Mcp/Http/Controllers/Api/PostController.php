<?php

declare(strict_types=1);

namespace Modules\Mcp\Http\Controllers\Api;

use App\Core\Modules\ModuleManager;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Mcp\Services\McpServerService;

final class PostController extends Controller
{
    public function __construct(
        private readonly McpServerService $mcpServerService,
        private readonly ModuleManager $moduleManager
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        if (! $this->moduleManager->isModuleEnabled('mcp')) {
            return new JsonResponse([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32000,
                    'message' => 'MCP Server module is currently disabled by CMS administration.',
                ],
                'id' => null,
            ], 503);
        }

        $payload = $request->all();

        if (empty($payload)) {
            return new JsonResponse([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32700,
                    'message' => 'Parse error: Empty or invalid JSON-RPC payload.',
                ],
                'id' => null,
            ], 400);
        }

        $response = $this->mcpServerService->handle($payload);

        return new JsonResponse($response, isset($response['error']) && $response['error']['code'] === -32601 ? 404 : 200);
    }
}
