<?php

declare(strict_types=1);

namespace Modules\Mcp\Native\Servers;

use App\Core\Capabilities\AvailableCapabilities;
use App\Services\Capabilities\ActorContextFactory;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Tool;
use Modules\Mcp\Native\Tools\CapabilityTool;

/** Publishes the shared capability catalog through the standard MCP protocol. */
#[Version('1.0.0')]
class CmsMcpServer extends Server
{
    public const DEFAULT_TOOL_LIMIT = 200;

    protected string $name = 'CMS';

    protected string $version = '1.0.0';

    protected string $instructions = 'Exposes every backoffice operation as a capability.';

    public int $defaultPaginationLength = 100;

    /**
     * Native tools built from the capability registry, cached per request.
     *
     * @var array<int, Tool>|null
     */
    private ?array $resolvedTools = null;

    /**
     * The SDK builds ServerContext with $this->tools; filling it here is what
     * turns the capability registry into native MCP tools.
     *
     * The advertised name and instructions follow the active product brand
     * (`config('cms.name')`), never a hardcoded one.
     */
    public function createContext(): ServerContext
    {
        $brand = (string) config('cms.name', 'CMS');

        $this->name = $brand;
        $this->instructions = $brand.' exposes every backoffice operation as a capability. '
            .'Start with cms_capabilities to see what you may do, then cms_entity_types and cms_resource_types '
            .'for the exact fields you can write. Publishing, deletion and external changes return an execution reference for human approval in the backoffice. Everything '
            .'you do is recorded in the audit trail (cms_mcp_audit).';

        $this->tools = $this->capabilityTools();

        return parent::createContext();
    }

    /**
     * Builds (and caches) the native tool list from the capability registry.
     *
     * @return array<int, Tool>
     */
    public function capabilityTools(): array
    {
        if ($this->resolvedTools !== null) {
            return $this->resolvedTools;
        }

        $actor = app(ActorContextFactory::class)->fromRequest(request(), 'mcp');
        $manifest = app(AvailableCapabilities::class)->manifest($actor);
        $limit = max(1, min(count($manifest), (int) (request()->input('limit') ?: self::DEFAULT_TOOL_LIMIT)));

        $tools = [];

        foreach (array_slice($manifest, 0, $limit) as $entry) {
            $tools[] = CapabilityTool::for((string) $entry['name'], $entry);
        }

        return $this->resolvedTools = $tools;
    }
}
