<?php

declare(strict_types=1);

namespace Modules\Mcp\Tools\Contracts;

use App\Core\Capabilities\Contracts\CapabilityInterface;

/**
 * One MCP capability.
 *
 * A tool is a thin adapter over an existing domain service: it declares what it
 * needs (admin permission + OAuth ability) and the JSON schema of its
 * arguments, and delegates the work. Everything the backoffice can do is
 * therefore reachable by an agent without a second implementation of the rules.
 */
interface McpToolInterface extends CapabilityInterface
{
    /**
     * Unique tool name (`cms_…`), the identifier agents call.
     */
    public function name(): string;

    public function description(): string;

    /**
     * JSON schema properties of the arguments.
     *
     * @return array<string, array<string, mixed>>
     */
    public function properties(): array;

    /**
     * @return list<string>
     */
    public function required(): array;

    /**
     * Admin permission required (`manage:shop`, `manage:seo`…). Null means any
     * authenticated agent may call it.
     */
    public function permission(): ?string;

    /**
     * Permission required for this specific call: generic capabilities (entity
     * and resource engines) resolve it from the requested type/resource, so one
     * tool can never be used to bypass another module's permission.
     *
     * @param  array<string, mixed>  $args
     */
    public function permissionFor(array $args): ?string;

    /**
     * OAuth ability: `read`, `write` or `destructive`.
     */
    public function ability(): string;

    public function mutating(): bool;

    /**
     * True when the tool can destroy data or take the site down; the dispatcher
     * creates a persisted approval request.
     */
    public function destructive(): bool;

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function handle(array $args): array;
}
