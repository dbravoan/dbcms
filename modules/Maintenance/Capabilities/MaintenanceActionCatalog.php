<?php

declare(strict_types=1);

namespace Modules\Maintenance\Capabilities;

use App\Core\Capabilities\CapabilityRegistry;
use App\Core\Capabilities\Tools\Action\ActionTool;
use App\Services\Capabilities\CmsActionService;

/** Module-owned descriptions; handlers delegate to the existing shared service. */
final class MaintenanceActionCatalog
{
    public static function register(CapabilityRegistry $registry, CmsActionService $service): void
    {
        foreach (self::all($service) as $tool) {
            $registry->register($tool, module: 'maintenance', effect: $tool->name() === 'cms_maintenance_toggle' ? 'external' : 'read');
        }
    }

    /**
     * @return list<ActionTool>
     */
    public static function all(CmsActionService $service): array
    {
        return [
            new ActionTool(
                toolName: 'cms_maintenance_get',
                toolDescription: 'CMS: reports the maintenance mode state, its message and end date',
                toolProperties: [],
                toolRequired: [],
                toolPermission: 'manage:modules',
                toolAbility: ActionTool::ABILITY_READ,
                handler: static fn (array $args): array => $service->maintenanceGet(),
            ),
            new ActionTool(
                toolName: 'cms_maintenance_toggle',
                toolDescription: 'CMS: turns the public maintenance mode on or off (optionally with a message and an end date). Requires review and human approval in the backoffice',
                toolProperties: [
                    'enabled' => ['type' => 'boolean', 'description' => 'true to enable, false to disable; omit to flip'],
                    'message' => ['type' => 'string', 'description' => 'Message shown to visitors'],
                    'until' => ['type' => 'string', 'description' => 'ISO date when maintenance ends'],
                ],
                toolRequired: [],
                toolPermission: 'manage:modules',
                toolAbility: ActionTool::ABILITY_DESTRUCTIVE,
                handler: static fn (array $args): array => $service->maintenanceToggle($args),
            ),
        ];
    }
}
