<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Http\Controllers\Admin\Providers\Catalog;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\AiAssistant\Contracts\AiConfigBridgeInterface;
use Modules\AiAssistant\Services\AiModelCatalogService;

/**
 * Returns the live model catalog for a provider (cached, refreshable).
 */
final class GetController extends Controller
{
    public function __construct(
        private readonly AiConfigBridgeInterface $configBridge,
        private readonly AiModelCatalogService $catalog
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'string', Rule::in($this->configBridge->getProviderIds())],
            'refresh' => ['nullable', 'boolean'],
        ]);

        $provider = (string) $validated['provider'];
        $refresh = (bool) ($validated['refresh'] ?? false);

        $models = $this->configBridge->listModels($provider, $refresh);

        return response()->json([
            'success' => true,
            'provider' => $provider,
            'models' => $models,
            'count' => count($models),
            'refreshed' => $refresh,
            'connection' => $this->catalog->connectionState($provider),
        ]);
    }
}
