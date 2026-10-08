<?php

declare(strict_types=1);

namespace Modules\Mcp\Tools\Action;

use Modules\Mcp\Tools\Contracts\McpToolInterface;

/** Compatibility adapter. Domain logic belongs to the CMS capability layer. */
class ActionTool extends \App\Core\Capabilities\Tools\Action\ActionTool implements McpToolInterface {}
