<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Http\Controllers\Admin\TestConnection;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\AiAssistant\Contracts\AiAssistantDomainServiceInterface;
use Modules\AiAssistant\Contracts\AiConfigBridgeInterface;

final class PostController extends Controller
{
    public function __construct(
        private readonly AiAssistantDomainServiceInterface $aiService,
        private readonly AiConfigBridgeInterface $configBridge,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'string', Rule::in($this->configBridge->getProviderIds())],
            'modality' => ['sometimes', 'string', Rule::in(['text', 'image', 'video'])],
        ]);

        $result = $this->aiService->testProviderConnection((string) $validated['provider'], (string) ($validated['modality'] ?? 'text'));

        return response()->json($result);
    }
}
