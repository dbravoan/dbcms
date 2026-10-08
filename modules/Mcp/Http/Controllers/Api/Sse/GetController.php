<?php

declare(strict_types=1);

namespace Modules\Mcp\Http\Controllers\Api\Sse;

use App\Core\Modules\ModuleManager;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class GetController extends Controller
{
    public function __construct(
        private readonly ModuleManager $moduleManager
    ) {}

    public function __invoke(Request $request): StreamedResponse|JsonResponse
    {
        if (! $this->moduleManager->isModuleEnabled('mcp')) {
            return new JsonResponse([
                'error' => 'MCP Server module is currently disabled.',
            ], 503);
        }

        $sessionId = (string) Str::uuid();
        $scopes = (array) $request->attributes->get('oauth_scopes', []);
        $userId = $request->attributes->get('oauth_user_id');
        $clientId = $request->attributes->get('oauth_client_id');

        Cache::put("mcp:session:{$sessionId}", [
            'user_id' => $userId,
            'client_id' => $clientId,
            'token_id' => $request->attributes->get('oauth_token_id'),
            'scopes' => $scopes,
            'created_at' => now()->toISOString(),
        ], now()->addHours(2));

        $endpointUrl = url('/api/mcp/message?sessionId='.$sessionId);

        return response()->stream(function () use ($endpointUrl): void {
            echo "event: endpoint\n";
            echo "data: {$endpointUrl}\n\n";

            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream; charset=utf-8',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
