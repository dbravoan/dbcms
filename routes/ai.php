<?php

declare(strict_types=1);

use App\Http\Middleware\AuthenticateMcpPassport;
use App\Mcp\Servers\CmsMcpServer;
use Laravel\Mcp\Facades\Mcp;

/*
|--------------------------------------------------------------------------
| Native MCP server (Model Context Protocol)
|--------------------------------------------------------------------------
| Exposes the dbCMS capability surface over the standard MCP protocol, so any
| client that speaks `initialize` + `tools/list` + `tools/call` can connect:
|
|   Endpoint: POST /mcp/cms          (Streamable HTTP)
|   Auth:     Bearer <Passport token> with mcp:access + mcp:read/write/destructive
|
| The same capabilities are available over the CMS JSON-RPC transport
| (POST /api/mcp) and over SSE (GET /api/mcp/sse).
|
| Tips: `POST /mcp/cms?limit=8` publishes a subset (handy for clients that need a
| short tool list); the capability layer always allows `cms_capabilities`.
*/
Mcp::web('mcp/cms', CmsMcpServer::class)
    // Same Passport guard as the JSON-RPC transport: it validates the token,
    // resolves the acting user and exposes its scopes to the capability policy.
    ->middleware(['module.enabled:mcp', AuthenticateMcpPassport::class.':mcp:access']);
