<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Ai;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

final class AiPromptRawGetController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $locale = (string) $request->get('locale', 'es');
        $fileName = $locale === 'en'
            ? 'EDITORIAL_HUMAN_AI_SYSTEM_PROMPT.md'
            : 'PROMPT_EDITORIAL_HUMAN_IA.md';

        $filePath = base_path("docs/prompts/{$fileName}");
        $content = File::exists($filePath) ? (string) File::get($filePath) : '';

        return response()->json([
            'locale' => $locale,
            'filename' => $fileName,
            'content' => $content,
        ]);
    }
}
