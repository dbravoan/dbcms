<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Http\Controllers\Api;

use App\Core\Capabilities\CapabilityExecutor;
use App\Services\Capabilities\ActorContextFactory;
use Illuminate\Http\JsonResponse;
use Modules\AiAssistant\DTOs\GenerationResponse;
use Modules\AiAssistant\Http\Requests\GenerateRequest;

final class EntityTranslationPostController
{
    public function __construct(private readonly CapabilityExecutor $executor, private readonly ActorContextFactory $actors) {}

    public function __invoke(GenerateRequest $request): JsonResponse
    {
        abort_unless($request->validated('action') === 'translate_entity', 422);
        $result = $this->executor->execute('cms_ai_translate_entity', $request->validated(), $this->actors->fromRequest($request, 'api'));

        return response()->json((new GenerationResponse($result))->toArray(), isset($result['error']) ? (isset($result['denied']) ? 403 : 422) : 200);
    }
}
