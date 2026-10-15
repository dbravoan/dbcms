<?php

declare(strict_types=1);

namespace Modules\SocialPublishing\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Capabilities\ActorContextFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\SocialPublishing\Contracts\PublicationServiceInterface;

final class PublicationSchedulePostController extends Controller
{
    public function __invoke(Request $request, string $publication, PublicationServiceInterface $service, ActorContextFactory $actors): JsonResponse
    {
        $data = $request->validate(['publish_at' => 'required|string|max:40']);

        return response()->json(['delivery' => $service->schedule($publication, (string) $data['publish_at'], $actors->fromRequest($request, 'api'))], 202);
    }
}
