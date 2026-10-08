<?php

declare(strict_types=1);

namespace App\Enums;

enum OAuthScope: string
{
    case READ_CONTENT = 'read:content';
    case WRITE_CONTENT = 'write:content';
    case MANAGE_USERS = 'manage:users';
    case ADMIN_SYSTEM = 'admin:system';
    case AI_USE = 'ai:use';
    case AI_MANAGE = 'ai:manage';

    // MCP and Machine-to-Machine scopes
    case MCP_ACCESS = 'mcp:access';
    case MCP_PUBLISH = 'mcp:publish';
    case MCP_READ = 'mcp:read';
    case MCP_WRITE = 'mcp:write';
    case MCP_DESTRUCTIVE = 'mcp:destructive';
    case M2M_SERVICE = 'm2m:service';

    // Module scopes (Shop and clients)
    case SHOP_READ = 'shop:read';
    case SHOP_MANAGE = 'shop:manage';
    case SHOP_ORDERS = 'shop:orders';
    case SHOP_PROFILE = 'shop:profile';

    /**
     * @return array<string, string>
     */
    public static function definitions(): array
    {
        return [
            self::READ_CONTENT->value => 'Read content, sitemaps, and GEO metadata',
            self::WRITE_CONTENT->value => 'Create, update, or archive content',
            self::MANAGE_USERS->value => 'Manage user accounts and scopes',
            self::ADMIN_SYSTEM->value => 'System administration and agent monitoring',
            self::AI_USE->value => 'Use authorized AI capabilities',
            self::AI_MANAGE->value => 'Manage AI provider configuration',
            self::MCP_ACCESS->value => 'Inspect system topology, read entities, search content and run anti-AI audits',
            self::MCP_PUBLISH->value => 'Publish and mutate posts, pages, and products via MCP (legacy alias of mcp:write)',
            self::MCP_READ->value => 'MCP: read any backoffice resource (listings, entities, settings, audit trail)',
            self::MCP_WRITE->value => 'MCP: create and update content and platform resources through the backoffice surface',
            self::MCP_DESTRUCTIVE->value => 'MCP: delete, roll back, uninstall, clear caches or toggle maintenance mode',
            self::M2M_SERVICE->value => 'Machine-to-Machine autonomous client service',
            self::SHOP_READ->value => 'Read catalog and product details',
            self::SHOP_MANAGE->value => 'Manage shop settings, stock, and orders',
            self::SHOP_ORDERS->value => 'Customer order management and history',
            self::SHOP_PROFILE->value => 'Customer account profile and delivery addresses',
        ];
    }
}
