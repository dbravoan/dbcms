<?php

declare(strict_types=1);

namespace Modules\Mcp\Resources;

use App\Services\Capabilities\PlatformResourceCatalog;

/** Compatibility adapter. Domain logic belongs to the CMS capability layer. */
class McpResourceCatalog extends PlatformResourceCatalog {}
