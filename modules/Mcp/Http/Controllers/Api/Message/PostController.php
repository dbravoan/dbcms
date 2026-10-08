<?php

declare(strict_types=1);

namespace Modules\Mcp\Http\Controllers\Api\Message;

use App\Core\Modules\ModuleManager;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Modules\Mcp\Http\Controllers\Api\PostController as HandlePostController;

final class PostController extends Controller
{
    public function __construct(
        private readonly HandlePostController $handleController,
        private readonly ModuleManager $moduleManager
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        if (! $this->moduleManager->isModuleEnabled('mcp')) {
            return new JsonResponse([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32000,
                    'message' => 'MCP Server module is currently disabled.',
                ],
                'id' => null,
            ], 503);
        }

        $sessionId = (string) $request->query('sessionId', '');
        if ($sessionId === '' || ! Cache::has("mcp:session:{$sessionId}")) {
            return new JsonResponse([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32002,
                    'message' => 'Session expired or invalid. Re-initialize via SSE /api/mcp/sse.',
                ],
                'id' => null,
            ], 400);
        }

        /** @var array{user_id?: mixed, client_id?: mixed, token_id?: mixed, scopes?: list<string>} $sessionData */
        $sessionData = Cache::get("mcp:session:{$sessionId}");
        foreach (['user_id', 'client_id', 'token_id'] as $attribute) {
            $current = $request->attributes->get('oauth_'.$attribute);
            if (! isset($sessionData[$attribute]) || $current === null || (string) $sessionData[$attribute] !== (string) $current) {
                return new JsonResponse([
                    'jsonrpc' => '2.0',
                    'error' => ['code' => -32003, 'message' => 'The SSE session does not belong to this token.'],
                    'id' => null,
                ], 403);
            }
        }
        // The freshly authenticated token is authoritative; cached scopes never
        // grant abilities that have since been removed or revoked.

        return ($this->handleController)($request);
    }
}
