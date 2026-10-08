<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Facades\Facade;
use Modules\AiAssistant\AiAssistantModuleServiceProvider;
use Modules\Mcp\McpModuleServiceProvider;
use PHPUnit\Framework\TestCase;

/** Exercises the actual route files and module registration without booting a DB. */
final class AdminModulePathTest extends TestCase
{
    private Container $previousContainer;

    private ?Container $previousFacade;

    private Application $isolated;

    private Router $router;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousContainer = Container::getInstance();
        $this->previousFacade = Facade::getFacadeApplication();
        $this->isolated = new Application(dirname(__DIR__, 2));
        $this->isolated->instance('config', new Repository(['cms' => ['admin_path' => 'control']]));
        $this->router = new Router(new Dispatcher($this->isolated), $this->isolated);
        $this->isolated->instance('router', $this->router);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->isolated);
        require dirname(__DIR__, 2).'/routes/web.php';
        foreach ([AiAssistantModuleServiceProvider::class, McpModuleServiceProvider::class] as $class) {
            $provider = new $class($this->isolated);
            (new ReflectionMethod($provider, 'registerRoutes'))->invoke($provider);
        }
        $this->router->getRoutes()->refreshNameLookups();
        $this->isolated->instance('url', new UrlGenerator($this->router->getRoutes(), Request::create('http://localhost')));
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->previousFacade);
        Container::setInstance($this->previousContainer);
        parent::tearDown();
    }

    public function test_actual_module_routes_and_navigation_use_the_custom_admin_prefix(): void
    {
        self::assertSame('control/ai-assistant', $this->router->getRoutes()->getByName('admin.ai_assistant.workbench')->uri());
        self::assertSame('control/ai-assistant/generate/tasks/{task}', $this->router->getRoutes()->getByName('admin.ai_assistant.generate_status')->uri());
        self::assertSame('control/modules/mcp/generate-token', $this->router->getRoutes()->getByName('admin.modules.mcp.generate-token')->uri());
        self::assertSame('admin.ai_assistant.workbench', $this->router->getRoutes()->match(Request::create('/control/ai-assistant'))->getName());
        self::assertSame('http://localhost/control/ai-assistant', (new AiAssistantModuleServiceProvider($this->isolated))->getAdminNavigation()[0]['url']);
        self::assertSame('http://localhost/control/modules/mcp/settings', (new McpModuleServiceProvider($this->isolated))->getAdminNavigation()[0]['url']);
        self::assertSame('api/mcp/tools', $this->router->getRoutes()->getByName('api.mcp.tools')->uri());
        self::assertSame('api/ai-assistant/generate-block', $this->router->getRoutes()->match(Request::create('/api/ai-assistant/generate-block', 'POST'))->uri());
    }

    public function test_named_routes_generate_custom_prefix_urls_for_async_and_navigation_actions(): void
    {
        foreach ([
            ['admin.ai_assistant.generate_status', ['task' => 12], '/control/ai-assistant/generate/tasks/12'],
            ['admin.modules.mcp.generate-token', [], '/control/modules/mcp/generate-token'],
            ['admin.notifications.read', ['id' => 3], '/control/notifications/3/read'],
            ['admin.modules.impact', ['identifier' => 'mcp'], '/control/modules/mcp/impact'],
            ['admin.media.update', ['id' => 4], '/control/media/4'],
            ['admin.media.destroy', ['filename' => 'photo.webp'], '/control/media/photo.webp'],
        ] as [$name, $args, $path]) {
            self::assertSame('http://localhost'.$path, route($name, $args));
        }
    }
}
