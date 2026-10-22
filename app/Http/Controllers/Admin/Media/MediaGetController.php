<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Media;

use App\Http\Controllers\Controller;
use App\Services\Media\MediaManagerService;
use Illuminate\Http\JsonResponse;

final class MediaGetController extends Controller
{
    public function __construct(
        private readonly MediaManagerService $mediaService
    ) {}

    public function __invoke(): JsonResponse
    {
        return response()->json([
            'files' => $this->mediaService->listMedia(),
        ]);
    }
}
