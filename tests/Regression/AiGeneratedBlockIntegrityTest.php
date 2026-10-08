<?php

declare(strict_types=1);

namespace Tests\Regression;

use App\Core\Blocks\Contracts\BlockRegistryInterface;
use App\Core\Blocks\Contracts\BlockTypeInterface;
use Modules\AiAssistant\Services\AiAssistantDomainService;
use Modules\AiAssistant\Services\AiConfigBridge;
use Modules\AiAssistant\Services\AiPromptTemplateEngine;
use Modules\AiAssistant\Services\BlockIntelligenceService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

final class AiGeneratedBlockIntegrityTest extends TestCase
{
    private function intelligence(): BlockIntelligenceService
    {
        $blocks = [];
        foreach (['hero', 'cta', 'features', 'charts', 'kpi', 'widget_posts', 'gallery', 'testimonials'] as $type) {
            $block = $this->createStub(BlockTypeInterface::class);
            $block->method('getDefaultPayload')->willReturn(['title' => 'Métricas de rendimiento', 'cta_text' => 'Comenzar ahora', 'items' => [['label' => 'Velocidad', 'value' => 98]], 'chart_type' => 'bar']);
            $block->method('getName')->willReturn($type);
            $block->method('getCategory')->willReturn('content');
            $blocks[$type] = $block;
        }
        $registry = $this->createStub(BlockRegistryInterface::class);
        $registry->method('get')->willReturnCallback(static fn (string $type): ?BlockTypeInterface => $blocks[$type] ?? null);
        $registry->method('getActiveBlocks')->willReturn($blocks);
        $registry->method('getDynamicDataPayloads')->willReturn([]);

        return new BlockIntelligenceService($registry);
    }

    public function test_ai_sanitization_does_not_insert_editor_demo_copy_or_statistics(): void
    {
        $data = $this->intelligence()->sanitizeBlockPayload('charts', ['title' => 'Library classification']);
        self::assertSame('Library classification', $data['title']);
        self::assertSame([], $data['items']);
        self::assertSame('', $data['cta_text']);
        self::assertSame('bar', $data['chart_type']);
    }

    public function test_quantitative_blocks_require_the_users_explicit_labels_and_values(): void
    {
        $service = $this->intelligence();
        $payload = ['chart_type' => 'bar', 'items' => [['label' => 'Speed', 'value' => 98]]];
        self::assertFalse($service->hasSuppliedQuantitativeData('charts', $payload, 'Write an article without statistics'));
        self::assertFalse($service->hasSuppliedQuantitativeData('charts', $payload, 'Create chart from Speed 84'));
        self::assertTrue($service->hasSuppliedQuantitativeData('charts', $payload, 'Create chart using these measured data: Speed 98'));
    }

    public function test_composed_english_article_drops_fabricated_metrics_and_unsolicited_widgets(): void
    {
        $service = (new ReflectionClass(AiAssistantDomainService::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty($service, 'blockIntelligence'))->setValue($service, $this->intelligence());
        $prompts = $this->createStub(AiPromptTemplateEngine::class);
        $prompts->method('buildBlocksCompositionPrompt')->willReturn(['system' => 'English only', 'user' => 'Compose']);
        (new ReflectionProperty($service, 'promptEngine'))->setValue($service, $prompts);
        $bridge = $this->createMock(AiConfigBridge::class);
        $bridge->expects(self::once())->method('generateCompletion')->willReturn(['content' => json_encode(['sections' => [
            ['type' => 'hero', 'data' => ['title' => 'Library organisation']],
            ['type' => 'charts', 'data' => ['title' => 'Library performance']],
            ['type' => 'kpi', 'data' => ['items' => [['label' => 'Efficiency', 'value' => '99%']]]],
            ['type' => 'widget_posts', 'data' => []],
        ]]), 'provider' => 'fake', 'model' => 'fake', 'prompt_tokens' => 0, 'completion_tokens' => 0, 'latency_ms' => 0]);
        (new ReflectionProperty($service, 'configBridge'))->setValue($service, $bridge);
        $sections = (new ReflectionMethod($service, 'composeSections'))->invoke($service, 'Write an English article about organising books, without statistics or widgets.', 'Library organisation', 'An editorial guide', 'en');
        self::assertNotContains('charts', array_column($sections, 'type'));
        self::assertNotContains('kpi', array_column($sections, 'type'));
        self::assertNotContains('widget_posts', array_column($sections, 'type'));
        self::assertStringNotContainsString('Métricas', json_encode($sections, JSON_UNESCAPED_UNICODE));
        self::assertStringNotContainsString('Comenzar', json_encode($sections, JSON_UNESCAPED_UNICODE));
    }

    public function test_default_landing_recommendations_do_not_invent_authority(): void
    {
        $types = array_column($this->intelligence()->recommendSequence('SaaS landing', 'en'), 'type');
        self::assertNotContains('kpi', $types);
        self::assertNotContains('testimonials', $types);
    }

    public function test_chart_without_values_requests_data_before_any_provider_access(): void
    {
        // An uninitialized provider collaborator would throw if generation ran.
        $service = (new ReflectionClass(AiAssistantDomainService::class))->newInstanceWithoutConstructor();
        $result = $service->generateBlock('charts', 'Create a chart about CMS performance', 'en', 'uk');
        self::assertSame('needs_input', $result['status']);
        self::assertFalse($result['success']);
    }
}
