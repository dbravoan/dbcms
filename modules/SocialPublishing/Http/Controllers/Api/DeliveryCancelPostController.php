<?php

declare(strict_types=1);

namespace Modules\SocialPublishing\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Capabilities\ActorContextFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\SocialPublishing\Contracts\PublicationServiceInterface;

final class DeliveryCancelPostController extends Controller
{
    public function __invoke(Request $request, string $delivery, PublicationServiceInterface $service, ActorContextFactory $actors): JsonResponse
    {
        $service->cancel($delivery, $actors->fromRequest($request, 'api'));

        return response()->json(['cancellation_requested' => true], 202);
    }
}
