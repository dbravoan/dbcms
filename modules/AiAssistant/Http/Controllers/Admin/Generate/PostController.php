<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Http\Controllers\Admin\Generate;

use App\Http\Controllers\Controller;
use App\Services\Capabilities\ActorContextFactory;
use Illuminate\Http\JsonResponse;
use Modules\AiAssistant\DTOs\GenerationResponse;
use Modules\AiAssistant\Http\Requests\GenerateRequest;
use Modules\AiAssistant\Services\AiTaskService;

final class PostController extends Controller
{
    public function __construct(private readonly AiTaskService $tasks, private readonly ActorContextFactory $actors) {}

    public function __invoke(GenerateRequest $request): JsonResponse
    {
        $data = $this->tasks->execute((string) $request->validated('action'), $request->validated(), $this->actors->fromRequest($request));

        return response()->json((new GenerationResponse($data))->toArray() + ['action' => $request->validated('action'), 'error' => $data['error'] ?? null], isset($data['error']) ? 422 : 200);
    }
}
