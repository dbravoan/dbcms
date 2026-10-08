<?php

declare(strict_types=1);

namespace Modules\Mcp\Resources;

use App\Core\Capabilities\Resources\CapabilityResource;
use App\Core\Capabilities\Resources\CapabilityResourceRegistry;

/** Compatibility facade over the CMS-owned registry. */
class McpResourceRegistry extends CapabilityResourceRegistry
{
    private readonly CapabilityResourceRegistry $registry;

    public function __construct(?CapabilityResourceRegistry $registry = null)
    {
        $this->registry = $registry ?? new CapabilityResourceRegistry;
    }

    public function register(CapabilityResource $resource): void
    {
        $this->registry->register($resource);
    }

    /** @param iterable<int,CapabilityResource> $resources */
    public function registerMany(iterable $resources): void
    {
        $this->registry->registerMany($resources);
    }

    public function has(string $key): bool
    {
        return $this->registry->has($key);
    }

    public function get(string $key): ?CapabilityResource
    {
        return $this->registry->get($key);
    }

    /** @return array<string,CapabilityResource> */
    public function all(): array
    {
        return $this->registry->all();
    }

    /** @return list<string> */
    public function keys(): array
    {
        return $this->registry->keys();
    }
}
