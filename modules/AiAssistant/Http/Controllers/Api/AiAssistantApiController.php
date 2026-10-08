<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Http\Controllers\Api;

use App\Core\Capabilities\CapabilityExecutor;
use App\Http\Controllers\Controller;
use App\Services\Capabilities\ActorContextFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AiAssistant\DTOs\GenerationResponse;

class AiAssistantApiController extends Controller
{
    public function __construct(
        private readonly CapabilityExecutor $executor,
        private readonly ActorContextFactory $actors
    ) {}

    /**
     * Generates a CMS block with JSON schema integrity.
     */
    public function generateBlock(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'block_type' => 'required|string',
            'prompt' => 'required|string',
            'locale' => 'nullable|string|max:10',
            'market' => 'nullable|string|max:10',
            ...$this->contextRules(),
        ]);

        $result = $this->executor->execute('cms_ai_generate_block', $validated, $this->actors->fromRequest($request, 'api'));

        return response()->json((new GenerationResponse($result))->toArray(), isset($result['error']) ? (isset($result['denied']) ? 403 : 422) : 200);
    }

    /**
     * Drafts an article with anti-AI editorial guardrails.
     */
    public function draftArticle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'topic' => 'required|string',
            'market' => 'nullable|string|max:10',
            'locale' => 'nullable|string|max:10',
            'style' => 'nullable|string',
            'include_blocks' => 'nullable|boolean',
            ...$this->contextRules(),
        ]);

        $result = $this->executor->execute('cms_ai_draft_article', $validated, $this->actors->fromRequest($request, 'api'));

        return response()->json((new GenerationResponse($result))->toArray(), isset($result['error']) ? (isset($result['denied']) ? 403 : 422) : 200);
    }

    /**
     * Humanizes synthetic prose and validates anti-AI metrics.
     */
    public function humanize(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'required|string',
            'locale' => 'nullable|string|max:10',
            'style' => 'nullable|string',
            'prompt' => 'nullable|string',
            ...$this->contextRules(),
        ]);

        $result = $this->executor->execute('cms_ai_humanize', $validated, $this->actors->fromRequest($request, 'api'));

        return response()->json((new GenerationResponse($result))->toArray(), isset($result['error']) ? (isset($result['denied']) ? 403 : 422) : 200);
    }

    /** @return array<string,mixed> */
    private function contextRules(): array
    {
        return ['context_source' => 'nullable|string|in:prompt,editor', 'editor_market_ids' => 'nullable|array|max:100', 'editor_market_ids.*' => 'integer|min:1|distinct'];
    }
}
