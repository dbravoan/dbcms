<?php

declare(strict_types=1);

namespace Modules\Mcp\Models;

use App\Models\CapabilityAuditLog;

/** Compatibility adapter. Domain logic belongs to the CMS capability layer. */
class McpAuditLog extends CapabilityAuditLog {}
