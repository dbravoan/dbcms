<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Http\Controllers\Admin\Generate\Status;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AiAssistant\Models\AiGenerationTask;
use Modules\AiAssistant\Services\AiTaskPresentation;

/**
 * Polls the state of a queued AI generation task.
 */
final class GetController extends Controller
{
    public function __invoke(Request $request, string $task, AiTaskPresentation $presentation): JsonResponse
    {
        $record = AiGenerationTask::where('id', $task)
            ->where('user_id', $request->user()?->getKey())
            ->first();

        if ($record === null) {
            return response()->json([
                'success' => false,
                'error' => 'Tarea de generación no encontrada.',
            ], 404);
        }

        return response()->json($presentation->payload($record));
    }
}
