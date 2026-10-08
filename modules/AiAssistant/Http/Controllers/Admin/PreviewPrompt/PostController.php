<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Http\Controllers\Admin\PreviewPrompt;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AiAssistant\Services\AiPromptTemplateEngine;

final class PostController extends Controller
{
    public function __construct(
        private readonly AiPromptTemplateEngine $promptEngine
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $locale = (string) ($request->input('locale') ?: 'es');
        $market = (string) ($request->input('market') ?: 'es');

        $prompt = $this->promptEngine->buildSystemPrompt($locale, $market);

        return response()->json([
            'success' => true,
            'prompt' => $prompt,
            'locale' => $locale,
            'market' => $market,
        ]);
    }
}
