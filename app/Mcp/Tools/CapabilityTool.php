<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Modules\Mcp\Services\McpToolDispatcher;
use Modules\Mcp\Tools\McpToolRegistry;

/**
 * Bridges ONE dbCMS capability to the native `laravel/mcp` SDK.
 *
 * The capability (name, schema, permission, OAuth ability, audit) already lives
 * in `modules/Mcp`; this class only adapts it so standard MCP clients — Claude
 * Desktop, Cursor, VS Code, the MCP Inspector, OpenCode's MCP transport — can
 * call it. A single class serves every capability, so nothing is duplicated.
 */
final class CapabilityTool extends Tool
{
    /**
     * @param  array<string, array<string, mixed>>  $schema
     * @param  list<string>  $required
     */
    public function __construct(
        private readonly string $capabilityName,
        private readonly string $capabilityDescription,
        private readonly array $schema,
        private readonly array $required,
    ) {
        $this->name = $capabilityName;
        $this->description = $capabilityDescription;
    }

    /**
     * Native tool for a registered capability.
     */
    /** @param array<string,mixed>|null $entry */
    public static function for(string $name, ?array $entry = null): self
    {
        $capability = app(McpToolRegistry::class)->get($name);

        if ($capability === null) {
            throw new \InvalidArgumentException("Unknown capability [{$name}].");
        }

        return new self(
            capabilityName: $capability->name(),
            capabilityDescription: $capability->description(),
            schema: (array) ($entry['parameters']['properties'] ?? $capability->properties()),
            required: $capability->required(),
        );
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        $properties = [];

        foreach ($this->schema as $name => $definition) {
            $properties[(string) $name] = $this->typeFor($schema, (string) $name, (array) $definition);
        }

        return $properties;
    }

    public function handle(Request $request): Response
    {
        $arguments = array_filter(
            (array) $request->all(),
            fn (mixed $value, mixed $key): bool => array_key_exists((string) $key, $this->schema),
            ARRAY_FILTER_USE_BOTH,
        );

        $result = app(McpToolDispatcher::class)->dispatch($this->capabilityName, $arguments, self::context());

        if (isset($result['error']) && is_string($result['error'])) {
            return Response::error($result['error']);
        }

        return Response::text((string) json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @return array<string, mixed>
     */
    private static function context(): array
    {
        $request = request();
        $user = $request->user() ?? auth()->user();

        return [
            'user' => $user instanceof User ? $user : null,
            'scopes' => array_values(array_map('strval', (array) $request->attributes->get('oauth_scopes', []))),
            'token_id' => is_string($request->attributes->get('oauth_token_id')) ? (string) $request->attributes->get('oauth_token_id') : null,
            'client_id' => is_string($request->attributes->get('oauth_client_id')) ? (string) $request->attributes->get('oauth_client_id') : null,
            'is_m2m' => (bool) $request->attributes->get('is_m2m', false),
            'ip' => $request->ip(),
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function typeFor(JsonSchema $schema, string $name, array $definition): Type
    {
        $description = (string) ($definition['description'] ?? '');
        $type = $definition['type'] ?? 'string';

        if (is_array($type)) {
            $type = (string) (reset($type) ?: 'string');
        }

        $property = match ((string) $type) {
            'integer' => $schema->integer(),
            'number' => $schema->number(),
            'boolean' => $schema->boolean(),
            'array' => $schema->array(),
            'object' => $schema->object(),
            default => $schema->string(),
        };

        if ($description !== '') {
            $property->description($description);
        }

        if (isset($definition['enum'])) {
            $property->enum((array) $definition['enum']);
        }
        $property->required(in_array($name, $this->required, true));

        return $property;
    }
}
