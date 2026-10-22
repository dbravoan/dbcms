<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Seo\JsonLdSchemaService;
use Illuminate\Http\JsonResponse;

final class SiteSchemaGetController extends Controller
{
    public function __invoke(JsonLdSchemaService $service): JsonResponse
    {
        return response()->json($service->generateSiteGraph());
    }
}
