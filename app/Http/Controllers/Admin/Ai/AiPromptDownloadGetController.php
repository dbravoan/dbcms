<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Ai;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class AiPromptDownloadGetController extends Controller
{
    public function __invoke(Request $request): BinaryFileResponse
    {
        $locale = (string) $request->get('locale', 'es');
        $fileName = $locale === 'en'
            ? 'EDITORIAL_HUMAN_AI_SYSTEM_PROMPT.md'
            : 'PROMPT_EDITORIAL_HUMAN_IA.md';

        $filePath = base_path("docs/prompts/{$fileName}");

        if (! File::exists($filePath)) {
            abort(404, "El archivo de prompt [{$fileName}] no existe.");
        }

        return response()->download($filePath, $fileName, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
        ]);
    }
}
