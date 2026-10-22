<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\AiAssistant\Services\EditorialGuidanceService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class AiPromptDownloadGetController extends Controller
{
    public function __invoke(Request $request): BinaryFileResponse
    {
        $locale = (string) $request->get('locale', 'es');
        $guide = app(EditorialGuidanceService::class)->guide($locale);

        return response()->download($guide['path'], $guide['filename'], [
            'Content-Type' => 'text/markdown; charset=UTF-8',
        ]);
    }
}
