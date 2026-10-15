<?php

declare(strict_types=1);

namespace Modules\SocialPublishing\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Capabilities\ActorContextFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\SocialPublishing\Services\SocialAuthorization;
use Modules\SocialPublishing\Services\SocialPresentation;

final class PublicationsGetController extends Controller
{
    public function __invoke(Request $request, SocialPresentation $presentation, SocialAuthorization $auth, ActorContextFactory $actors): JsonResponse
    {
        $auth->authorize($actors->fromRequest($request, 'api'), 'read:social-publishing');

        return response()->json($presentation->dashboard());
    }
}
