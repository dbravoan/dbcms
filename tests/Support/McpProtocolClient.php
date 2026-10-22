<?php

declare(strict_types=1);

namespace Tests\Support;

use Closure;
use Laravel\Mcp\Server\Contracts\Transport;
use Modules\Mcp\Native\Servers\CmsMcpServer;

/** Runs the real MCP protocol without an HTTP socket; HTTP auth has its own tests. */
final class McpProtocolClient
{
    /** @param array<string,mixed> $request @return array<string,mixed> */
    public function handle(array $request): array
    {
        $transport = new class implements Transport
        {
            public array $message = [];

            public function onReceive(Closure $handler): void {}

            public function run(): void {}

            public function send(string $message): void
            {
                $this->message = json_decode($message, true, 512, JSON_THROW_ON_ERROR);
            }

            public function stream(Closure $stream): void
            {
                $stream();
            }
        };
        $server = new CmsMcpServer($transport);
        $server->start();
        $server->handle(json_encode($request, JSON_THROW_ON_ERROR));

        return $transport->message;
    }
}
