<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Services;

use App\Core\Capabilities\ActorContext;
use App\Core\Capabilities\CallableCapability;
use App\Core\Capabilities\CapabilityRegistry;
use App\Core\Content\Contracts\EntitySchemaRegistryInterface;

final class AiCapabilityRegistrar
{
    public function register(CapabilityRegistry $registry): void
    {
        foreach (AiActionRunner::MCP_TOOL_ACTIONS as $name => $action) {
            $properties = [
                'prompt' => ['type' => 'string'], 'topic' => ['type' => 'string'], 'content' => ['type' => 'string'], 'title' => ['type' => 'string'],
                'locale' => ['type' => 'string'], 'market' => ['type' => 'string'], 'entity_type' => ['type' => 'string'], 'entity_id' => ['type' => 'integer'],
                'context_source' => ['type' => 'string', 'enum' => ['prompt', 'editor']], 'editor_market_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                'block_type' => ['type' => 'string'], 'style' => ['type' => 'string'], 'include_blocks' => ['type' => 'boolean'], 'persist_draft' => ['type' => 'boolean'],
                'generate_image' => ['type' => 'boolean'], 'generate_video' => ['type' => 'boolean'], 'research_web' => ['type' => 'boolean'], 'generate_media' => ['type' => 'boolean'],
                'provider' => ['type' => 'string'], 'model' => ['type' => 'string'], 'text_provider' => ['type' => 'string'], 'image_provider' => ['type' => 'string'], 'video_provider' => ['type' => 'string'],
                'text_model' => ['type' => 'string'], 'image_model' => ['type' => 'string'], 'video_model' => ['type' => 'string'], 'duration' => ['type' => 'integer'], 'aspect_ratio' => ['type' => 'string'],
                'element_type' => ['type' => 'string'], 'intent' => ['type' => 'string'], 'summary' => ['type' => 'string'], 'proofread_action' => ['type' => 'string'],
            ];
            $required = [];
            if ($action === 'translate_entity') {
                $properties['source_locale'] = ['type' => 'string', 'minLength' => 1, 'maxLength' => 10];
                $properties['target_locale'] = ['type' => 'string', 'minLength' => 1, 'maxLength' => 10];
                $properties['fields'] = ['type' => 'object'];
                $properties['entity_id'] = ['type' => 'integer', 'minimum' => 1];
                $properties['editor_market_ids'] = ['type' => 'array', 'items' => ['type' => 'integer', 'minimum' => 1]];
                $properties['sections'] = ['type' => 'array', 'items' => ['type' => 'object']];
                $required = ['entity_type', 'source_locale', 'target_locale', 'editor_market_ids', 'fields', 'sections'];
            }
            $capability = new CallableCapability($name, 'Asistente CMS: '.$action, $properties, $required, 'use:ai', 'write', static fn (array $args): array => app(AiActionRunner::class)->run($action, $args));
            if ($action === 'translate_entity') {
                $capability = new EntityTranslationCapability($capability, app(EntitySchemaRegistryInterface::class));
            }
            $registry->register($capability, 'ai_assistant', 'generation', static function (ActorContext $actor, array $args) use ($action): ?string {
                return app(AiCapabilityAvailability::class)->reason($action, $actor, $args);
            }, dependencies: match ($action) {
                'image' => ['model:image'], 'video' => ['model:video'], 'research' => [],
                'orchestrate_multimodal' => ['model:text', 'model:image'], default => ['model:text'],
            });
        }
    }
}
