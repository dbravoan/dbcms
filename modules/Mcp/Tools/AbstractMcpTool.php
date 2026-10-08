<?php

declare(strict_types=1);

namespace Modules\Mcp\Tools;

use App\Core\Capabilities\Tools\AbstractCapabilityTool;
use Modules\Mcp\Tools\Contracts\McpToolInterface;

/** Compatibility adapter. Domain logic belongs to the CMS capability layer. */
abstract class AbstractMcpTool extends AbstractCapabilityTool implements McpToolInterface {}
