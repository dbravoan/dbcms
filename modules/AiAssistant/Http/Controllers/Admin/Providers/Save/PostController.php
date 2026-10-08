<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Http\Controllers\Admin\Providers\Save;

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
            'api_key' => 'nullable|string',
            'model' => 'nullable|string',
            'text_model' => 'nullable|string|max:200',
            'image_model' => 'nullable|string|max:200',
            'video_model' => 'nullable|string|max:200',
            'base_url' => 'nullable|string',
        ]);

        $provider = (string) $validated['provider'];
        $success = $this->configBridge->saveProviderConfig($provider, $validated);

        return response()->json([
            'success' => $success,
            'message' => "Configuración para el proveedor '{$provider}' guardada correctamente.",
            'has_key' => $this->configBridge->isConfigured($provider),
            'model' => $this->configBridge->getModelForProvider($provider),
        ]);
    }
}
