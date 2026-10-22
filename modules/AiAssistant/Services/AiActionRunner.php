<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Services;

use App\Core\Capabilities\ExecutionContext;
use InvalidArgumentException;
use Modules\AiAssistant\Contracts\AiAssistantDomainServiceInterface;
use Modules\AiAssistant\Contracts\AiEntityTranslationServiceInterface;
use Modules\AiAssistant\Contracts\AiGenerationContextResolverInterface;
use Modules\AiAssistant\Contracts\AiMultimodalOrchestratorInterface;
use Modules\AiAssistant\DTOs\EntityTranslationRequest;
use Modules\AiAssistant\DTOs\MultimodalOrchestrationRequest;

/**
 * Single source of truth mapping a named AI action to its domain call.
 *
 * Every surface —backoffice synchronous requests, queued (async) jobs and the
 * MCP bridge— runs through here, so the AI agent and external MCP agents
 * execute exactly the same logic with the same parameters.
 */
final class AiActionRunner
{
    /**
     * @var list<string>
     */
    public const ACTIONS = [
        'translate_entity',
        'block',
        'recommend_blocks',
        'compose_blocks',
        'draft',
        'proofread',
        'humanize',
        'seo',
        'page_element',
        'image',
        'video',
        'research',
        'orchestrate_multimodal',
    ];

    /**
     * MCP AI tool name => canonical action.
     *
     * @var array<string, string>
     */
    public const MCP_TOOL_ACTIONS = [
        'cms_ai_translate_entity' => 'translate_entity',
        'cms_ai_generate_block' => 'block',
        'cms_ai_draft_article' => 'draft',
        'cms_ai_humanize' => 'humanize',
        'cms_ai_page_element' => 'page_element',
        'cms_ai_proofread' => 'proofread',
        'cms_ai_seo' => 'seo',
        'cms_ai_recommend_blocks' => 'recommend_blocks',
        'cms_ai_compose_blocks' => 'compose_blocks',
        'cms_ai_generate_image' => 'image',
        'cms_ai_generate_video' => 'video',
        'cms_ai_research_and_draft' => 'research',
        'cms_ai_orchestrate_content' => 'orchestrate_multimodal',
    ];

    public function __construct(
        private readonly AiAssistantDomainServiceInterface $service,
        private readonly ?AiMultimodalOrchestratorInterface $multimodal = null,
        private readonly ?BlockIntelligenceService $blockIntelligence = null,
        private readonly ?AiGenerationContextResolverInterface $contextResolver = null,
        private readonly ?AiEntityTranslationServiceInterface $translation = null,
    ) {}

    public function supports(string $action): bool
    {
        return in_array($action, self::ACTIONS, true);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function run(string $action, array $params): array
    {
        $actor = app(ExecutionContext::class)->actor;
        unset($params['actor_id']);
        if ($actor !== null) {
            $params['actor_id'] = $actor->userId;
        }
        if ($action === 'translate_entity') {
            return ($this->translation ?? app(AiEntityTranslationServiceInterface::class))->translate(EntityTranslationRequest::fromArray($params));
        }
        $resolvedContext = null;
        if (in_array($params['context_source'] ?? '', ['prompt', 'editor'], true)) {
            $resolvedContext = ($this->contextResolver ?? app(AiGenerationContextResolverInterface::class))->resolve($action, $params);
            if ($resolvedContext->question !== null) {
                return ['success' => false, 'status' => 'needs_input', 'question' => $resolvedContext->question, 'message' => $resolvedContext->question];
            }
            $params['locale'] = $resolvedContext->locale;
            $params['market'] = $resolvedContext->market;
            $params['market_ids'] = $resolvedContext->marketIds;
        }
        $locale = (string) ($params['locale'] ?? 'es');
        $market = (string) ($params['market'] ?? 'es');
        $style = (string) ($params['style'] ?? 'periodistico');

        $result = match ($action) {
            'block' => $this->runBlockAction($params, $locale, $market),
            'recommend_blocks' => $this->service->recommendBlocks(
                intent: (string) ($params['prompt'] ?? $params['intent'] ?? $params['topic'] ?? ''),
                locale: $locale
            ),
            'compose_blocks' => $this->service->composeBlocks(
                brief: (string) ($params['prompt'] ?? $params['intent'] ?? $params['topic'] ?? ''),
                locale: $locale,
                title: (string) ($params['title'] ?? ''),
                summary: (string) ($params['summary'] ?? '')
            ),
            'draft' => $this->service->draftArticle(
                topic: (string) ($params['topic'] ?? $params['prompt'] ?? ''),
                market: $market,
                locale: $locale,
                style: $style,
                includeBlocks: (bool) ($params['include_blocks'] ?? false)
            ),
            'proofread' => $this->service->proofread(
                content: (string) ($params['content'] ?? $params['prompt'] ?? ''),
                locale: $locale,
                action: (string) ($params['proofread_action'] ?? 'improve')
            ),
            'humanize' => $this->service->humanize(
                content: (string) ($params['content'] ?? $params['prompt'] ?? ''),
                locale: $locale,
                style: $style
            ),
            'seo' => $this->service->generateSeo(
                title: (string) ($params['title'] ?? ''),
                content: (string) ($params['content'] ?? $params['prompt'] ?? ''),
                locale: $locale
            ),
            'page_element' => $this->service->buildPageElement(
                type: (string) ($params['element_type'] ?? 'post'),
                prompt: (string) ($params['prompt'] ?? $params['topic'] ?? ''),
                locale: $locale,
                market: $market
            ),
            'image' => $this->runImageAction($params),
            'video' => $this->runVideoAction($params),
            'research' => $this->runResearchAction($params, $locale, $market, $style),
            'orchestrate_multimodal' => $this->runOrchestrateAction($params, $locale, $market, $style),
            default => throw new InvalidArgumentException("Unsupported AI action [{$action}]."),
        };

        if ($resolvedContext !== null) {
            $result['resolved_context'] = $resolvedContext->toArray();
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function runBlockAction(array $params, string $locale, string $market): array
    {
        $prompt = (string) ($params['prompt'] ?? '');
        $blockType = (string) ($params['block_type'] ?? '');

        if (($blockType === '' || $blockType === 'auto') && $this->blockIntelligence !== null) {
            $blockType = $this->blockIntelligence->detectBlockTypeFromIntent($prompt);
        }

        if ($blockType === '' || $blockType === 'auto') {
            $blockType = 'hero';
        }

        $block = $this->service->generateBlock(
            blockType: $blockType,
            prompt: $prompt,
            locale: $locale,
            market: $market
        );

        if (($block['status'] ?? '') === 'needs_input') {
            return $block;
        }

        if (! empty($params['generate_media']) && $this->multimodal !== null) {
            if ($blockType === 'hero' && empty($block['data']['image_url'])) {
                $img = $this->multimodal->generateImage(prompt: $prompt, title: (string) ($block['data']['title'] ?? 'Hero'), options: $params);
                $block['data']['image_url'] = $img['url'];
            }
            if ($blockType === 'video' && empty($block['data']['video_url'])) {
                $vid = $this->multimodal->generateVideo(prompt: $prompt, title: (string) ($block['data']['title'] ?? 'Video'), options: $params);
                $block['data']['video_url'] = $vid['url'];
            }
        }

        return $block;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function runImageAction(array $params): array
    {
        if ($this->multimodal === null) {
            throw new InvalidArgumentException('Multimodal orchestrator is not available.');
        }

        return $this->multimodal->generateImage(
            prompt: (string) ($params['prompt'] ?? ''),
            entityType: isset($params['entity_type']) ? (string) $params['entity_type'] : null,
            entityId: isset($params['entity_id']) ? (int) $params['entity_id'] : null,
            content: isset($params['content']) ? (string) $params['content'] : null,
            title: isset($params['title']) ? (string) $params['title'] : null,
            options: $params
        );
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function runVideoAction(array $params): array
    {
        if ($this->multimodal === null) {
            throw new InvalidArgumentException('Multimodal orchestrator is not available.');
        }

        return $this->multimodal->generateVideo(
            prompt: (string) ($params['prompt'] ?? ''),
            entityType: isset($params['entity_type']) ? (string) $params['entity_type'] : null,
            entityId: isset($params['entity_id']) ? (int) $params['entity_id'] : null,
            content: isset($params['content']) ? (string) $params['content'] : null,
            title: isset($params['title']) ? (string) $params['title'] : null,
            options: $params
        );
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function runResearchAction(array $params, string $locale, string $market, string $style): array
    {
        $topic = (string) ($params['topic'] ?? $params['prompt'] ?? '');
        $service = app(AiResearchService::class);

        return $service->researchTopic($topic, $locale, $market);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function runOrchestrateAction(array $params, string $locale, string $market, string $style): array
    {
        if ($this->multimodal === null) {
            throw new InvalidArgumentException('Multimodal orchestrator is not available.');
        }

        $entityType = (string) ($params['entity_type'] ?? 'post');
        $request = new MultimodalOrchestrationRequest(
            topic: (string) ($params['topic'] ?? $params['prompt'] ?? ''),
            intent: (string) ($params['intent'] ?? 'informative'),
            entityType: $entityType,
            market: $market,
            locale: $locale,
            style: $style,
            generateImage: (bool) ($params['generate_image'] ?? true),
            generateVideo: (bool) ($params['generate_video'] ?? false),
            researchWeb: (bool) ($params['research_web'] ?? false),
            persistDraft: (bool) ($params['persist_draft'] ?? false),
            textProvider: isset($params['text_provider']) ? (string) $params['text_provider'] : null,
            imageProvider: isset($params['image_provider']) ? (string) $params['image_provider'] : null,
            videoProvider: isset($params['video_provider']) ? (string) $params['video_provider'] : null,
            options: $params
        );

        return $entityType === 'page'
            ? $this->multimodal->orchestratePage($request)->toArray()
            : $this->multimodal->orchestratePost($request)->toArray();
    }
}
