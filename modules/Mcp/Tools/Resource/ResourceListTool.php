<?php

declare(strict_types=1);

namespace Modules\Mcp\Tools\Resource;

use Modules\Mcp\Tools\Contracts\McpToolInterface;

/** Compatibility adapter. Domain logic belongs to the CMS capability layer. */
class ResourceListTool extends \App\Core\Capabilities\Tools\Resource\ResourceListTool implements McpToolInterface {}
