<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Contracts\Services\LocalizationServiceInterface;
use App\Core\Capabilities\ActorContext;
use App\Core\Capabilities\CapabilityAuthorizer;
use App\Core\Capabilities\ExecutionContext;
use App\Core\Content\Contracts\EntitySchemaRegistryInterface;
use App\Core\Content\EntitySchema;
use App\Core\Modules\ModuleManager;
use App\Models\Language;
use App\Models\Market;
use App\Services\Capabilities\EntityCrudService;
use Illuminate\Container\Container;
use Illuminate\Contracts\Foundation\Application as ApplicationContract;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Application;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\ValidationException;
use Modules\AiAssistant\Contracts\AiConfigBridgeInterface;
use Modules\AiAssistant\DTOs\EntityTranslationRequest;
use Modules\AiAssistant\Services\AiEntityTranslationService;
use Modules\AiAssistant\Services\BlockTranslationRegistry;
use Modules\AiAssistant\Services\StructuredTranslationPayload;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class AiEntityTranslationServiceTest extends TestCase
{
    private Container $previousContainer;

    private ?ApplicationContract $previousFacadeApplication;

    protected function setUp(): void
    {
        $this->previousContainer = Container::getInstance();
        $this->previousFacadeApplication = Facade::getFacadeApplication();
        $container = new Application(dirname(__DIR__, 3));
        $container->instance('validator', new Factory(new Translator(new ArrayLoader, 'en')));
        Container::setInstance($container);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($container);
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->previousFacadeApplication);
        Container::setInstance($this->previousContainer);
    }

    public function test_translates_declared_schema_roles_and_preserves_preview_structure_without_resource_mutations(): void
    {
        $bridge = $this->createMock(AiConfigBridgeInterface::class);
        $bridge->method('getActiveProvider')->willReturn('fake');
        $bridge->method('getModelForProvider')->willReturn('fake-text');
        $bridge->method('isConfigured')->willReturn(true);
        $bridge->expects(self::once())->method('generateCompletion')->willReturnCallback(static function (string $system, string $input, string $provider, array $options): array {
            $decoded = json_decode($input, true, flags: JSON_THROW_ON_ERROR);
            self::assertSame('en', $decoded['target_locale']);
            self::assertStringNotContainsString('/cover.jpg', $input);
            self::assertSame('fake', $provider);
            self::assertSame('fake-text', $options['model']);

            return ['content' => json_encode(['segments' => array_map(static fn (array $segment): array => ['id' => $segment['id'], 'text' => 'EN:'.$segment['text']], $decoded['segments'])], JSON_THROW_ON_ERROR), 'provider' => 'fake', 'model' => 'fake-text', 'prompt_tokens' => 1, 'completion_tokens' => 1, 'latency_ms' => 1];
        });
        $sections = [['id' => 'hero1', 'type' => 'hero', 'title' => 'Portada', 'image_url' => '/cover.jpg', 'cta_url' => '/comprar']];
        $result = $this->service($bridge)->translate(new EntityTranslationRequest('post', 'es', 'en', [1], ['title' => 'Hola', 'body' => '<p>Texto</p>'], $sections));
        self::assertSame('EN:Hola', $result['fields']['title']);
        self::assertSame('<p>EN:Texto</p>', $result['fields']['body']);
        self::assertSame('/cover.jpg', $result['sections'][0]['image_url']);
        self::assertSame('hero1', $result['sections'][0]['id']);
        self::assertSame(64, strlen($result['source_fingerprint']));
        self::assertSame(['locale' => 'en', 'market_ids' => [1]], $result['resolved_context']);
    }

    public function test_invalid_context_and_undeclared_fields_are_rejected_before_http(): void
    {
        $bridge = $this->createMock(AiConfigBridgeInterface::class);
        $bridge->expects(self::never())->method('generateCompletion');
        foreach ([new EntityTranslationRequest('post', 'es', 'es', [1], ['title' => 'Hola'], []), new EntityTranslationRequest('post', 'es', 'fr', [1], ['title' => 'Hola'], []), new EntityTranslationRequest('post', 'es', 'en', [999], ['title' => 'Hola'], []), new EntityTranslationRequest('post', 'es', 'en', [1], ['price' => '99'], []), new EntityTranslationRequest('post', 'es', 'en', [1], ['title' => ''], []), new EntityTranslationRequest('post', 'es', 'en', [1], ['title' => str_repeat('a', 200001)], [])] as $request) {
            try {
                $this->service($bridge)->translate($request);
                self::fail('Invalid translation was accepted.');
            } catch (ValidationException) {
                self::assertTrue(true);
            }
        }
    }

    public function test_entity_permission_is_required_before_http(): void
    {
        $bridge = $this->createMock(AiConfigBridgeInterface::class);
        $bridge->expects(self::never())->method('generateCompletion');
        $this->expectException(HttpException::class);
        $this->service($bridge, false)->translate(new EntityTranslationRequest('post', 'es', 'en', [1], ['title' => 'Hola'], []));
    }

    public function test_target_must_be_allowed_by_selected_markets(): void
    {
        $bridge = $this->createMock(AiConfigBridgeInterface::class);
        $bridge->expects(self::never())->method('generateCompletion');
        $this->expectException(ValidationException::class);
        $this->service($bridge, true, ['es'])->translate(new EntityTranslationRequest('post', 'es', 'en', [1], ['title' => 'Hola'], []));
    }

    /** @param list<string> $marketLocales */
    private function service(AiConfigBridgeInterface $bridge, bool $allowed = true, array $marketLocales = ['es', 'en']): AiEntityTranslationService
    {
        $registry = $this->createStub(EntitySchemaRegistryInterface::class);
        $registry->method('get')->willReturn(new EntitySchema('post', 'Post', 'FakeModel', true, fields: ['title' => ['input' => 'translations[{locale}][title]', 'label' => 'Title'], 'body' => ['input' => 'translations[{locale}][content]', 'label' => 'Content']], permission: 'manage:blog'));
        $authorizer = $this->createStub(CapabilityAuthorizer::class);
        $authorizer->method('allowed')->willReturn($allowed);
        $execution = new ExecutionContext;
        $execution->actor = new ActorContext(1);
        $localization = $this->createStub(LocalizationServiceInterface::class);
        $localization->method('activeLocales')->willReturn(['es', 'en']);
        $market = new Market;
        $market->setRawAttributes(['id' => 1, 'code' => 'es', 'is_active' => true]);
        $market->setRelation('languages', new EloquentCollection(array_map(static function (string $locale): Language {
            $language = new Language;
            $language->setRawAttributes(['code' => $locale, 'is_active' => true]);

            return $language;
        }, $marketLocales)));
        $localization->method('activeMarkets')->willReturn(new Collection([$market]));
        $modules = $this->createStub(ModuleManager::class);
        $resources = $this->createMock(EntityCrudService::class);
        $resources->expects(self::never())->method('create');
        $resources->expects(self::never())->method('update');
        $resources->expects(self::never())->method('resolveEntity');

        return new AiEntityTranslationService($registry, $authorizer, $execution, $localization, $modules, $resources, $bridge, new StructuredTranslationPayload(new BlockTranslationRegistry));
    }
}
