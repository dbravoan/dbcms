<?php

declare(strict_types=1);

namespace Modules\Mcp\Tools;

use App\Core\Capabilities\CapabilityRegistry;
use App\Core\Capabilities\Contracts\CapabilityInterface;

/** Compatibility facade; the CMS owns the registry independently of MCP. */
final class McpToolRegistry
{
    public function __construct(private readonly CapabilityRegistry $registry) {}

    public function register(CapabilityInterface $tool): void
    {
        $this->registry->register($tool);
    }

    /** @param iterable<int,CapabilityInterface> $tools */
    public function registerMany(iterable $tools): void
    {
        foreach ($tools as $tool) {
            $this->register($tool);
        }
    }

    public function has(string $name): bool
    {
        return $this->registry->has($name);
    }

    public function get(string $name): ?CapabilityInterface
    {
        return $this->registry->get($name);
    }

    /** @return array<string,CapabilityInterface> */
    public function all(): array
    {
        return $this->registry->all();
    }

    /** @return list<string> */
    public function names(): array
    {
        return $this->registry->names();
    }

    /** @return list<array<string,mixed>> */
    public function manifest(): array
    {
        return array_values(array_map(static fn (CapabilityInterface $tool): array => ['name' => $tool->name(), 'description' => $tool->description(), 'parameters' => ['type' => 'object', 'properties' => $tool->properties(), 'required' => $tool->required()]], $this->all()));
    }
}
