<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Http\Controllers\Admin\Providers\SetDefault;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\AiAssistant\Contracts\AiConfigBridgeInterface;

final class PostController extends Controller
{
    public function __construct(
        private readonly AiConfigBridgeInterface $configBridge
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'string', Rule::in($this->configBridge->getProviderIds())],
            'modality' => ['nullable', 'string', 'in:text,image,video'],
        ]);

        $provider = (string) $validated['provider'];
        $modality = (string) ($validated['modality'] ?? 'text');
        $success = $this->configBridge->setDefaultProvider($provider, $modality);

        return response()->json([
            'success' => $success,
            'message' => "Proveedor '{$provider}' establecido como predeterminado para {$modality}.",
            'default_provider' => $this->configBridge->getActiveProvider($modality),
            'modality' => $modality,
        ]);
    }
}
