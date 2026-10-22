<?php

declare(strict_types=1);
use App\Core\Capabilities\CapabilityRegistry;
use App\Core\Capabilities\Resources\CapabilityResource;
use App\Core\Capabilities\Resources\CapabilityResourceRegistry;
use App\Models\Market;
use App\Providers\CmsCapabilitiesServiceProvider;
use App\Services\Capabilities\PlatformResourceCatalog;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;

test('capability engine and shared handlers do not depend on MCP implementation', function (): void {
    expect('App\Core\Capabilities')->not->toUse('Modules\Mcp');
    expect('App\Services\Capabilities')->not->toUse('Modules\Mcp');
    expect('App\Services\Orchestration')->not->toUse(['Modules\Mcp', 'Illuminate\Http\Request', 'App\Http']);
});

test('native domain execution implements a contract and keeps immutable core DTOs', function (): void {
    expect('App\Services\Orchestration\ExecutionService')->toImplement('App\Contracts\Services\ExecutionServiceInterface');
    expect('App\Core\Capabilities\DTOs')->toBeReadonly();
});

test('module models cannot perform remote network calls', function (): void {
    foreach (glob(base_path('modules/*/Models')) ?: [] as $directory) {
        $module = basename(dirname($directory));
        expect('Modules\\'.$module.'\\Models')->not->toUse(['Illuminate\Support\Facades\Http', 'GuzzleHttp']);
    }
});

test('orchestration controllers delegate without persistence or business branches', function (): void {
    foreach (glob(app_path('Http/Controllers/Admin/Orchestration/*.php')) ?: [] as $file) {
        $source = file_get_contents($file);
        expect($source)->not->toMatch('/\b(?:DB::|::query\(|::create\(|->save\(|->update\(|if\s*\(|switch\s*\(|foreach\s*\()/');
        $class = 'App\Http\Controllers\Admin\Orchestration\\'.basename($file, '.php');
        $method = new ReflectionMethod($class, '__invoke');
        expect($method->getEndLine() - $method->getStartLine() - 1)->toBeLessThanOrEqual(10);
    }
});

test('shared content mutation controllers remain thin across their owning modules', function (): void {
    foreach (['Blog' => ['Post', 'Category'], 'Pages' => ['Page'], 'Shop' => ['Product', 'ShopCategory'], 'Gallery' => ['Gallery']] as $module => $entities) {
        foreach ($entities as $entity) {
            foreach (['Post', 'Put', 'Delete'] as $verb) {
                $namespace = $module === 'Pages' ? 'App\\Content\\Pages' : 'Modules\\'.$module;
                $class = $namespace.'\\Http\\Controllers\\Admin\\'.$entity.$verb.'Controller';
                $method = new ReflectionMethod($class, '__invoke');
                $source = file_get_contents($method->getFileName());
                expect($source)->not->toMatch('/\b(?:DB::|::query\(|::create\(|->save\(|if\s*\(|switch\s*\(|foreach\s*\()/');
                foreach ($method->getParameters() as $parameter) {
                    $type = $parameter->getType();
                    if ($type instanceof ReflectionNamedType && ! $type->isBuiltin() && is_subclass_of($type->getName(), Model::class)) {
                        expect($source)->not->toMatch('/\$'.preg_quote($parameter->getName(), '/').'->(?:update|delete|fill|forceFill|increment|decrement)\s*\(/');
                    }
                }
                expect($method->getEndLine() - $method->getStartLine() - 1)->toBeLessThanOrEqual(10);
            }
        }
    }
});

test('core capability provider owns its services without loading MCP adapters', function (): void {
    expect(CmsCapabilitiesServiceProvider::class)->not->toUse('Modules\Mcp');
    $container = new Container;
    (new CmsCapabilitiesServiceProvider($container))->register();
    expect($container->bound(CapabilityRegistry::class))->toBeTrue();
    $core = $container->make(CapabilityResourceRegistry::class);
    expect($core::class)->toBe(CapabilityResourceRegistry::class);
    $core->registerMany(PlatformResourceCatalog::all());
    $additional = new CapabilityResource('test_shared_registry', 'Test shared registry', Market::class, 'manage:markets');
    $core->register($additional);
    expect($core->get('test_shared_registry'))->toBe($additional);
});
