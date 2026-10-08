<?php

declare(strict_types=1);

namespace Modules\Mcp\Tools\Entity;

use Modules\Mcp\Tools\Contracts\McpToolInterface;

/** Compatibility adapter. Domain logic belongs to the CMS capability layer. */
class EntityGetTool extends \App\Core\Capabilities\Tools\Entity\EntityGetTool implements McpToolInterface {}
