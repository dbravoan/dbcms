<?php

declare(strict_types=1);

namespace Modules\SocialPublishing\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Capabilities\ActorContextFactory;
use Illuminate\Http\JsonResponse;
use Modules\SocialPublishing\Http\Requests\DraftRequest;
use Modules\SocialPublishing\Services\PublicationInputService;

final class PublicationDraftPostController extends Controller
{
    public function __invoke(DraftRequest $request, PublicationInputService $input, ActorContextFactory $actors): JsonResponse
    {
        $p = $input->create($request->payload(), array_values((array) $request->file('files', [])), $actors->fromRequest($request, 'api'));

        return response()->json(['publication' => $p], 201);
    }
}
