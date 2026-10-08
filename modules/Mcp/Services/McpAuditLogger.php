<?php

declare(strict_types=1);

namespace Modules\Mcp\Services;

use App\Services\Capabilities\CapabilityAuditLogger;

/** Compatibility adapter. Domain logic belongs to the CMS capability layer. */
class McpAuditLogger extends CapabilityAuditLogger {}
