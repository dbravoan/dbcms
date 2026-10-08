<?php

declare(strict_types=1);

namespace Tests\Regression;

use App\Contracts\Services\ModuleSettingsServiceInterface;
use App\Core\Capabilities\ActorContext;
use App\Core\Capabilities\ExecutionContext;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Modules\AiAssistant\Contracts\AiModelCatalogInterface;
use Modules\AiAssistant\Contracts\AiProviderInterface;
use Modules\AiAssistant\Contracts\AiProviderRegistryInterface;
use Modules\AiAssistant\DTOs\ChatRequest;
use Modules\AiAssistant\DTOs\CompletionResult;
use Modules\AiAssistant\DTOs\ProviderDefinition;
use Modules\AiAssistant\Services\AiConfigBridge;
use Modules\AiAssistant\Services\AiProviderSettings;
use PHPUnit\Framework\TestCase;

final class AiSelectedProviderConsistencyTest extends TestCase
{
    public function test_context_and_action_share_the_explicit_provider_and_model(): void
    {
        $previous = Container::getInstance();
        $app = new Application;
        $app->instance('env', 'production');
        $context = new ExecutionContext;
        $context->actor = new ActorContext(1);
        $context->capabilityName = 'cms_ai_generate_block';
        $context->arguments = ['provider' => 'selected', 'model' => 'selected-model'];
        $app->instance(ExecutionContext::class, $context);
        Container::setInstance($app);
        try {
            $adapter = $this->createMock(AiProviderInterface::class);
            $adapter->method('definition')->willReturn(new ProviderDefinition('selected', 'Selected provider', 'Fixture'));
            $adapter->expects(self::exactly(2))->method('chat')->with(self::isInstanceOf(ChatRequest::class), 'fixture-key', 'selected-model', null)
                ->willReturn(new CompletionResult('{"status":"ok"}', 'selected', 'selected-model'));
            $registry = $this->createStub(AiProviderRegistryInterface::class);
            $registry->method('resolve')->willReturnCallback(static function (string $provider) use ($adapter): AiProviderInterface {
                self::assertSame('selected', $provider);

                return $adapter;
            });
            $settings = $this->createStub(ModuleSettingsServiceInterface::class);
            $settings->method('get')->willReturnCallback(static fn (string $module, string $key, mixed $default = null): mixed => match ($key) {
                'default_text_provider', 'default_provider' => 'other-provider',
                'selected_api_key' => 'fixture-key',
                default => $default,
            });
            $catalog = $this->createStub(AiModelCatalogInterface::class);
            $bridge = new AiConfigBridge($registry, $catalog, new AiProviderSettings($settings, $registry), $settings);
            $resolved = $bridge->generateCompletion('Context system', 'Instruction', 'selected', ['model' => 'selected-model', 'temperature' => 0.0]);
            // Legacy domain calls omit explicit provider arguments; the common
            // execution context still applies the exact requested selection.
            $generated = $bridge->generateCompletion('Block system', 'Content');
            self::assertSame($resolved['provider'], $generated['provider']);
            self::assertSame($resolved['model'], $generated['model']);
        } finally {
            Container::setInstance($previous);
        }
    }
}
