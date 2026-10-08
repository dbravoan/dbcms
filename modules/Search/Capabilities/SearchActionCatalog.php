<?php

declare(strict_types=1);

namespace Modules\Search\Capabilities;

use App\Core\Capabilities\CapabilityRegistry;
use App\Core\Capabilities\Tools\Action\ActionTool;
use App\Services\Capabilities\CmsActionService;

/** Module-owned descriptions; handlers delegate to the existing shared service. */
final class SearchActionCatalog
{
    public static function register(CapabilityRegistry $registry, CmsActionService $service): void
    {
        foreach (self::all($service) as $tool) {
            $registry->register($tool, module: 'search', effect: $tool->name() === 'cms_search_reindex' ? 'draft' : 'read');
        }
    }

    /**
     * @return list<ActionTool>
     */
    public static function all(CmsActionService $service): array
    {
        return [
            new ActionTool(
                toolName: 'cms_search_reindex',
                toolDescription: 'CMS: rebuilds the cross-entity search index (all entities, one type or one locale)',
                toolProperties: [
                    'type' => ['type' => 'string', 'description' => 'Entity type to reindex (post, product…)'],
                    'locale' => ['type' => 'string', 'description' => 'Only this locale'],
                    'keep' => ['type' => 'boolean', 'description' => 'Upsert without flushing the index first'],
                ],
                toolRequired: [],
                toolPermission: 'manage:seo',
                toolAbility: ActionTool::ABILITY_WRITE,
                handler: static fn (array $args): array => $service->searchReindex($args),
            ),
            new ActionTool(
                toolName: 'cms_search_stats',
                toolDescription: 'CMS: reports the search index coverage per entity and the most searched terms (and what found nothing)',
                toolProperties: ['locale' => ['type' => 'string', 'description' => 'Locale to inspect']],
                toolRequired: [],
                toolPermission: 'manage:seo',
                toolAbility: ActionTool::ABILITY_READ,
                handler: static fn (array $args): array => $service->searchStats($args),
            ),
        ];
    }
}
