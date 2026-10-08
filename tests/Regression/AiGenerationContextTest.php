<?php

declare(strict_types=1);

namespace Tests\Regression;

use App\Contracts\Services\LocalizationServiceInterface;
use App\Core\Capabilities\ExecutionContext;
use App\Models\Language;
use App\Models\Market;
use Illuminate\Container\Container;
use Illuminate\Support\Collection;
use Modules\AiAssistant\Contracts\AiAssistantDomainServiceInterface;
use Modules\AiAssistant\Contracts\AiConfigBridgeInterface;
use Modules\AiAssistant\Contracts\AiGenerationContextResolverInterface;
use Modules\AiAssistant\DTOs\GenerationContext;
use Modules\AiAssistant\Exceptions\AiProviderException;
use Modules\AiAssistant\Models\AiGenerationTask;
use Modules\AiAssistant\Services\AiActionRunner;
use Modules\AiAssistant\Services\AiGenerationContextResolver;
use Modules\AiAssistant\Services\AiTaskPresentation;
use PHPUnit\Framework\TestCase;

final class AiGenerationContextTest extends TestCase
{
    private function resolver(string $response, bool $expectInference = true, bool $configured = true): AiGenerationContextResolver
    {
        $localization = $this->createStub(LocalizationServiceInterface::class);
        $localization->method('activeLanguages')->willReturn(new Collection([
            new Language(['code' => 'es', 'name' => 'Spanish']), new Language(['code' => 'en', 'name' => 'English']),
        ]));
        $es = new Market(['code' => 'es', 'name' => 'Spain']);
        $es->id = 1;
        $uk = new Market(['code' => 'uk', 'name' => 'United Kingdom']);
        $uk->id = 2;
        $localization->method('activeMarkets')->willReturn(new Collection([$es, $uk]));
        $localization->method('defaultLocale')->willReturn('es');
        $localization->method('defaultMarket')->willReturn($es);
        $bridge = $this->createMock(AiConfigBridgeInterface::class);
        $bridge->method('getActiveProvider')->willReturn('gemini');
        $bridge->method('isConfigured')->willReturn($configured);
        $bridge->method('getModelForProvider')->willReturn('configured-text-model');
        $bridge->expects($expectInference ? self::once() : self::never())->method('generateCompletion')
            ->with(self::callback(static fn (string $system): bool => str_contains($system, 'SOLO referencia') && str_contains($system, 'CATÁLOGO DE REFERENCIA')), self::callback(static fn (string $user): bool => array_keys((array) json_decode($user, true)) === ['instruction']), 'gemini', ['model' => 'configured-text-model', 'temperature' => 0.0, 'json' => true])
            ->willReturn(['content' => $response, 'provider' => 'gemini', 'model' => 'configured-text-model', 'prompt_tokens' => 1, 'completion_tokens' => 1, 'latency_ms' => 1]);

        return new AiGenerationContextResolver($localization, $bridge, new ExecutionContext);
    }

    public function test_explicit_prompt_uses_validated_language_and_market(): void
    {
        $result = $this->resolver('{"locales":["en"],"markets":["uk"],"ambiguous":false}')
            ->resolve('draft', ['context_source' => 'prompt', 'prompt' => 'Write in English for the United Kingdom']);
        self::assertNull($result->question);
        self::assertSame(['locale' => 'en', 'market' => 'uk', 'market_ids' => [2]], $result->toArray());
    }

    public function test_missing_override_preserves_defaults(): void
    {
        $result = $this->resolver('{"locales":[],"markets":[],"ambiguous":false}')
            ->resolve('draft', ['context_source' => 'prompt', 'prompt' => 'Write about architecture']);
        self::assertSame('es', $result->locale);
        self::assertSame('es', $result->market);
    }

    public function test_catalog_is_reference_and_neutral_instruction_has_no_explicit_targets(): void
    {
        $result = $this->resolver('{"locales":[],"markets":[],"ambiguous":false}')
            ->resolve('seo', ['context_source' => 'editor', 'locale' => 'en', 'editor_market_ids' => [2], 'prompt' => 'Escribe un consejo breve para clasificar libros, sin estadísticas, imágenes ni widgets.']);
        self::assertSame(['locale' => 'en', 'market' => 'uk', 'market_ids' => [2]], $result->toArray());
        self::assertNull($result->question);
    }

    public function test_multiple_explicit_targets_need_clarification(): void
    {
        $result = $this->resolver('{"locales":["es","en"],"markets":[],"ambiguous":false}')
            ->resolve('draft', ['context_source' => 'prompt', 'prompt' => 'Write in Spanish and English']);
        self::assertNotNull($result->question);
    }

    public function test_unavailable_target_is_not_silently_replaced(): void
    {
        $result = $this->resolver('{"locales":["fr"],"markets":[],"ambiguous":false}')
            ->resolve('draft', ['context_source' => 'prompt', 'prompt' => 'Write in French']);
        self::assertNotNull($result->question);
    }

    public function test_editor_inherits_context_without_prompt(): void
    {
        $result = $this->resolver('', false)->resolve('seo', ['context_source' => 'editor', 'locale' => 'en', 'editor_market_ids' => [2], 'content' => 'Spanish text to optimise']);
        self::assertSame(['locale' => 'en', 'market' => 'uk', 'market_ids' => [2]], $result->toArray());
    }

    public function test_conflicting_editor_prompt_never_redirects_entity(): void
    {
        $result = $this->resolver('{"locales":["es"],"markets":[],"ambiguous":false}')
            ->resolve('block', ['context_source' => 'editor', 'locale' => 'en', 'editor_market_ids' => [2], 'prompt' => 'Create a Spanish hero']);
        self::assertNotNull($result->question);
    }

    public function test_malformed_model_output_cannot_confer_authority(): void
    {
        $result = $this->resolver('{"locales":"en","markets":["uk"],"actor_id":1,"confirm":true}')
            ->resolve('draft', ['context_source' => 'prompt', 'prompt' => 'Write an article']);
        self::assertNotNull($result->question);
    }

    public function test_multimarket_editor_block_preserves_selected_markets(): void
    {
        $result = $this->resolver('{"locales":[],"markets":[],"ambiguous":false}')
            ->resolve('block', ['context_source' => 'editor', 'locale' => 'en', 'editor_market_ids' => [1, 2], 'prompt' => 'Generate a hero']);
        self::assertNull($result->question);
        self::assertSame([1, 2], $result->marketIds);
    }

    public function test_multimarket_editor_cannot_silently_target_a_subset(): void
    {
        $result = $this->resolver('{"locales":[],"markets":["uk"],"ambiguous":false}')
            ->resolve('block', ['context_source' => 'editor', 'locale' => 'en', 'editor_market_ids' => [1, 2], 'prompt' => 'Generate a hero only for UK']);
        self::assertNotNull($result->question);
    }

    public function test_unconfigured_provider_does_not_infer_or_fallback(): void
    {
        $this->expectException(AiProviderException::class);
        $this->resolver('', false, false)->resolve('draft', ['context_source' => 'prompt', 'prompt' => 'Write an article']);
    }

    public function test_unavailable_editor_market_is_rejected_before_inference(): void
    {
        $result = $this->resolver('', false)->resolve('block', ['context_source' => 'editor', 'locale' => 'en', 'editor_market_ids' => [99], 'prompt' => 'Generate a hero']);
        self::assertNotNull($result->question);
    }

    public function test_editor_without_market_selection_does_not_acquire_a_default_assignment(): void
    {
        $result = $this->resolver('', false)->resolve('seo', ['context_source' => 'editor', 'locale' => 'en', 'editor_market_ids' => []]);
        self::assertNull($result->question);
        self::assertSame([], $result->marketIds);
    }

    public function test_clarification_prevents_any_content_generation(): void
    {
        $service = $this->createMock(AiAssistantDomainServiceInterface::class);
        $service->expects(self::never())->method('draftArticle');
        $resolver = $this->createMock(AiGenerationContextResolverInterface::class);
        $resolver->expects(self::once())->method('resolve')->willReturn(new GenerationContext('', '', [], 'Which language?'));
        $result = $this->runAction(new AiActionRunner($service, contextResolver: $resolver), ['context_source' => 'prompt', 'prompt' => 'Draft']);
        self::assertSame('needs_input', $result['status']);
        self::assertSame('Which language?', $result['question']);
    }

    public function test_legacy_explicit_context_does_not_infer(): void
    {
        $service = $this->createMock(AiAssistantDomainServiceInterface::class);
        $service->expects(self::once())->method('draftArticle')->with('Draft', 'uk', 'en', 'periodistico', false)->willReturn(['title' => 'Legacy']);
        $resolver = $this->createMock(AiGenerationContextResolverInterface::class);
        $resolver->expects(self::never())->method('resolve');
        $result = $this->runAction(new AiActionRunner($service, contextResolver: $resolver), ['topic' => 'Draft', 'locale' => 'en', 'market' => 'uk']);
        self::assertSame('Legacy', $result['title']);
        self::assertArrayNotHasKey('resolved_context', $result);
    }

    public function test_runner_passes_and_returns_the_same_resolved_context(): void
    {
        $service = $this->createMock(AiAssistantDomainServiceInterface::class);
        $service->expects(self::once())->method('draftArticle')->with('Draft', 'uk', 'en', 'periodistico', false)->willReturn(['title' => 'Draft']);
        $resolver = $this->createMock(AiGenerationContextResolverInterface::class);
        $resolver->expects(self::once())->method('resolve')->willReturn(new GenerationContext('en', 'uk', [2]));
        $result = $this->runAction(new AiActionRunner($service, contextResolver: $resolver), ['context_source' => 'prompt', 'prompt' => 'Draft']);
        self::assertSame(['locale' => 'en', 'market' => 'uk', 'market_ids' => [2]], $result['resolved_context']);
    }

    public function test_queued_clarification_is_finished_and_visible(): void
    {
        $task = new AiGenerationTask(['id' => 'test-task', 'status' => AiGenerationTask::STATUS_NEEDS_INPUT, 'action' => 'draft', 'result' => ['status' => 'needs_input', 'question' => 'Which market?', 'message' => 'Which market?']]);
        $result = (new AiTaskPresentation)->payload($task);
        self::assertTrue($result['finished']);
        self::assertSame('needs_input', $result['status']);
        self::assertSame('Which market?', $result['question']);
        self::assertSame('Which market?', $result['data']['question']);
        self::assertNull($result['error']);
    }

    /** @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    private function runAction(AiActionRunner $runner, array $params): array
    {
        $previous = Container::getInstance();
        $container = new Container;
        $container->instance(ExecutionContext::class, new ExecutionContext);
        Container::setInstance($container);
        try {
            return $runner->run('draft', $params);
        } finally {
            Container::setInstance($previous);
        }
    }
}
