<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AiAssistant\Services\EditorialGuidanceService;

final class AiPromptRawGetController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $locale = (string) $request->get('locale', 'es');
        $guide = app(EditorialGuidanceService::class)->guide($locale);

        return response()->json([
            'locale' => $locale,
            'filename' => $guide['filename'],
            'content' => $guide['content'],
        ]);
    }
}
