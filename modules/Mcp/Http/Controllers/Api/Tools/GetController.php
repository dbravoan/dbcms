<?php

declare(strict_types=1);

namespace Modules\Mcp\Http\Controllers\Api\Tools;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Mcp\Services\McpServerService;

final class GetController extends Controller
{
    public function __construct(
        private readonly McpServerService $mcpServerService
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $response = $this->mcpServerService->handle([
            'jsonrpc' => '2.0',
            'method' => 'tools/list',
            'id' => 'tools-manifest',
        ]);

        return new JsonResponse([
            'tools' => $response['result']['tools'] ?? [],
            'transport' => [
                'http' => url('/api/mcp'),
                'sse' => url('/api/mcp/sse'),
            ],
            'authentication' => [
                'type' => 'Bearer (Laravel Passport OAuth2)',
                'token_endpoint' => url('/oauth/token'),
                'required_scopes' => ['mcp:access', 'mcp:read', 'mcp:write', 'mcp:destructive'],
            ],
        ]);
    }
}
