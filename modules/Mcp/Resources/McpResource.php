<?php

declare(strict_types=1);

namespace Modules\Mcp\Resources;

use App\Core\Capabilities\Resources\CapabilityResource;

/** Compatibility adapter. Domain logic belongs to the CMS capability layer. */
readonly class McpResource extends CapabilityResource {}
