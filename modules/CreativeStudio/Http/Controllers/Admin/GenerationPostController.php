<?php

declare(strict_types=1);

namespace Modules\CreativeStudio\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Modules\CreativeStudio\Services\CreativeStudioHttpService;
use Symfony\Component\HttpFoundation\Response;

final class GenerationPostController
{
    public function __invoke(Request $request, CreativeStudioHttpService $service, string $campaign): Response
    {
        return $service->generate($request, $campaign);
    }
}
