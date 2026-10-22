<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Http\Controllers\Admin\Preset;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AiAssistant\Contracts\AiBrandProfileServiceInterface;

final class PostController extends Controller
{
    public function __construct(
        private readonly AiBrandProfileServiceInterface $brandProfileService
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'preset' => 'required|string',
        ]);

        $success = $this->brandProfileService->applyPreset((string) $validated['preset']);
        if (! $success) {
            return response()->json([
                'success' => false,
                'message' => 'Preset no encontrado.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Preset aplicado correctamente a la instancia.',
            'profile' => $this->brandProfileService->getProfile(),
        ]);
    }
}
