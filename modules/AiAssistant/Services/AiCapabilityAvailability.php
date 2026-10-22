<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Services;

use App\Core\Capabilities\ActorContext;
use App\Core\Capabilities\CapabilityAuthorizer;
use App\Core\Content\Contracts\EntitySchemaRegistryInterface;
use Modules\AiAssistant\Contracts\AiProviderRegistryInterface;

final class AiCapabilityAvailability
{
    public function __construct(private readonly CapabilityAuthorizer $authorizer, private readonly EntitySchemaRegistryInterface $entities, private readonly AiProviderRegistryInterface $providers, private readonly AiProviderSettings $settings) {}

    /** @param array<string, mixed> $args */
    public function reason(string $action, ActorContext $actor, array $args): ?string
    {
        if (! $this->authorizer->allowed($actor, 'write', 'use:ai')) {
            return 'No tienes permiso para utilizar IA.';
        }
        $action = AiActionRunner::MCP_TOOL_ACTIONS[$action] ?? str_replace('cms_ai_', '', $action);
        if ($action === 'orchestrate_multimodal') {
            $args['generate_image'] ??= true;
        }
        $media = in_array($action, ['image', 'video'], true) || ! empty($args['generate_image']) || ! empty($args['generate_video']) || ! empty($args['generate_media']);
        if ($media && ! $this->authorizer->allowed($actor, 'write', 'manage:media')) {
            return 'La generación de medios requiere permiso para gestionar la biblioteca.';
        }
        if ($action === 'translate_entity' && isset($args['entity_type'])) {
            $schema = $this->entities->get((string) $args['entity_type']);
            if ($schema === null || ! $this->authorizer->allowed($actor, 'write', $schema->permission ?? 'access:admin')) {
                return 'No tienes permiso para traducir la entidad solicitada.';
            }
        }
        $persist = ! empty($args['persist_draft']) || $action === 'create_element';
        if ($persist || (! empty($args['entity_id']) && in_array($action, ['image', 'video'], true))) {
            $type = (string) ($args['entity_type'] ?? $args['type'] ?? ($args['entity_type'] ?? 'post'));
            $schema = $this->entities->get($type);
            if ($schema === null || ! $this->authorizer->allowed($actor, 'write', $schema->permission ?? 'access:admin')) {
                return 'No tienes permiso para modificar la entidad solicitada.';
            }
        }
        if ($action === 'create_element') {
            return null;
        }
        $modalities = match ($action) {
            'image' => ['image'], 'video' => ['video'], 'research' => [], default => ['text']
        };
        if (in_array($args['context_source'] ?? '', ['prompt', 'editor'], true) && trim((string) ($args['prompt'] ?? $args['topic'] ?? $args['intent'] ?? '')) !== '') {
            $modalities[] = 'text';
        }
        if (! empty($args['generate_image'])) {
            $modalities[] = 'image';
        }
        if (! empty($args['generate_video'])) {
            $modalities[] = 'video';
        }
        foreach (array_unique($modalities) as $modality) {
            $provider = (string) ($args[$modality.'_provider'] ?? (in_array($action, ['image', 'video'], true) ? ($modality === $action ? ($args['provider'] ?? null) : null) : ($modality === 'text' ? ($args['provider'] ?? null) : null)) ?? $this->settings->activeProvider($modality));
            if (! $this->providers->has($provider) || ! $this->providers->resolve($provider)->definition()->supportsModality($modality)) {
                return 'El proveedor no admite la modalidad solicitada.';
            }
            if (! $this->settings->isConfigured($provider)) {
                return 'El proveedor de '.$modality.' no está configurado.';
            }
        }

        return null;
    }
}
