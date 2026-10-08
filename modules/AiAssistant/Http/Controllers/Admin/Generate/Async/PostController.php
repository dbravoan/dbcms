<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Http\Controllers\Admin\Generate\Async;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\AiAssistant\Http\Requests\GenerateRequest;
use Modules\AiAssistant\Services\AiTaskService;

final class PostController extends Controller
{
    public function __construct(private readonly AiTaskService $tasks) {}

    public function __invoke(GenerateRequest $request): JsonResponse
    {
        $task = $this->tasks->enqueue($request, $request->validated());

        return response()->json(['success' => true, 'task_id' => $task->id, 'status' => $task->status]);
    }
}
