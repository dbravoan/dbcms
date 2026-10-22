<?php

declare(strict_types=1);

namespace Modules\ContentAudit\Capabilities;

use App\Core\Capabilities\CapabilityRegistry;
use App\Core\Capabilities\Tools\Action\ActionTool;
use App\Services\Capabilities\EntityReadService;

/** Module-owned descriptions; handlers delegate to the existing shared service. */
final class EditorialActionCatalog
{
    public static function register(CapabilityRegistry $registry, EditorialActions $service): void
    {
        foreach (self::all($service) as $tool) {
            $registry->register($tool, module: 'content_audit', effect: 'publish');
        }
    }

    /**
     * @return list<ActionTool>
     */
    public static function all(EditorialActions $service): array
    {
        $entity = [
            'type' => ['type' => 'string', 'description' => 'Entity type: post, page, product…'],
            'id' => ['type' => ['integer', 'string'], 'description' => 'Entity id'],
        ];

        return [
            new ActionTool(
                toolName: 'cms_audit_approve',
                toolDescription: 'CMS: approves the pending revision of an entity, publishing it (the editorial review queue)',
                toolProperties: $entity + ['notes' => ['type' => 'string', 'description' => 'Optional note for the author']],
                toolRequired: ['type', 'id'],
                toolPermission: 'access:admin',
                toolAbility: ActionTool::ABILITY_WRITE,
                handler: static fn (array $args): array => $service->auditApprove($args),
                permissionResolver: static fn (array $args): ?string => empty($args['type']) ? null : app(EntityReadService::class)->schemaFor((string) $args['type'])->permission,
            ),
            new ActionTool(
                toolName: 'cms_audit_reject',
                toolDescription: 'CMS: rejects the pending revision with a reason, sending it back to the author',
                toolProperties: $entity + [
                    'reason' => ['type' => 'string', 'description' => 'Why it was rejected (required)'],
                    'notes' => ['type' => 'string', 'description' => 'Extra note'],
                ],
                toolRequired: ['type', 'id', 'reason'],
                toolPermission: 'access:admin',
                toolAbility: ActionTool::ABILITY_WRITE,
                handler: static fn (array $args): array => $service->auditReject($args),
                permissionResolver: static fn (array $args): ?string => empty($args['type']) ? null : app(EntityReadService::class)->schemaFor((string) $args['type'])->permission,
            ),
            new ActionTool(
                toolName: 'cms_audit_rollback',
                toolDescription: 'CMS: restores an entity to a previous revision. Requires review and human approval in the backoffice',
                toolProperties: $entity + ['target_version' => ['type' => 'integer', 'description' => 'Revision number to restore']],
                toolRequired: ['type', 'id', 'target_version'],
                toolPermission: 'access:admin',
                toolAbility: ActionTool::ABILITY_DESTRUCTIVE,
                handler: static fn (array $args): array => $service->auditRollback($args),
                permissionResolver: static fn (array $args): ?string => empty($args['type']) ? null : app(EntityReadService::class)->schemaFor((string) $args['type'])->permission,
            ),
        ];
    }
}
