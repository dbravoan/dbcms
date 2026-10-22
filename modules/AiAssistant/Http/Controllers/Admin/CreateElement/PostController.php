<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Http\Controllers\Admin\CreateElement;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AiAssistant\Services\AiDraftPersistenceService;

final class PostController extends Controller
{
    public function __construct(private readonly AiDraftPersistenceService $drafts) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate(['type' => 'required|string', 'fields' => 'required|array', 'fields.*' => 'nullable|string', 'sections' => 'nullable|array', 'locale' => 'nullable|string|max:10', 'market' => 'nullable|string|max:10']);
        $result = $this->drafts->createElement($validated, $request->user());

        return response()->json(['success' => true] + $result);
    }
}
