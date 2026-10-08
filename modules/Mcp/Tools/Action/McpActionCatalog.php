<?php

declare(strict_types=1);

namespace Modules\Mcp\Tools\Action;

use App\Services\Capabilities\CmsActionCatalog;

/** Compatibility adapter. Domain logic belongs to the CMS capability layer. */
class McpActionCatalog extends CmsActionCatalog {}
