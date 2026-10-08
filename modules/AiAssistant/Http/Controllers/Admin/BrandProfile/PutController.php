<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Http\Controllers\Admin\BrandProfile;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AiAssistant\Contracts\AiBrandProfileServiceInterface;

final class PutController extends Controller
{
    public function __construct(
        private readonly AiBrandProfileServiceInterface $brandProfileService
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'brand_archetype' => 'required|string|in:autoridad,cercano,tecnico,disruptivo,institucional,humoristico',
            'brand_treatment' => 'required|string|in:tu,usted,nosotros',
            'brand_target_audience' => 'required|string',
            'brand_value_proposition' => 'required|string',
            'brand_preferred_terms' => 'nullable|string',
            'brand_taboo_terms' => 'nullable|string',
            'brand_cta_goals' => 'nullable|string',
            'brand_custom_directives' => 'nullable|string',
        ]);

        $this->brandProfileService->saveProfile($validated);

        return response()->json([
            'success' => true,
            'message' => 'Perfil de marca y tono editorial guardados con éxito para esta instancia.',
            'profile' => $this->brandProfileService->getProfile(),
        ]);
    }
}
