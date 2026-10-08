<?php

declare(strict_types=1);
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

test('cart API delegates catalogue checks and pricing to its application service', function (): void {
    $source = (string) file_get_contents(base_path('modules/Shop/Http/Controllers/CartApiController.php'));
    expect($source)->not->toContain('use Modules\\Shop\\Models\\');
    $nodes = (new ParserFactory)->createForNewestSupportedVersion()->parse($source);
    $finder = new NodeFinder;
    $actions = 0;
    foreach ($finder->findInstanceOf($nodes, ClassMethod::class) as $method) {
        if (! $method->isPublic() || $method->name->toString() === '__construct') {
            continue;
        }
        $actions++;
        expect(count($method->stmts ?? []))->toBeLessThanOrEqual(3);
    }
    expect($actions)->toBe(5);
});

test('shop purchase transports delegate stock payment and order access decisions', function (): void {
    $nodes = (new ParserFactory)->createForNewestSupportedVersion()
        ->parse((string) file_get_contents(base_path('modules/Shop/Http/Controllers/ShopFrontController.php')));
    $finder = new NodeFinder;
    $adapters = 0;
    foreach ($finder->findInstanceOf($nodes, ClassMethod::class) as $method) {
        if (! in_array($method->name->toString(), ['cart', 'checkout', 'processCheckout', 'orderSuccess', 'submitInquiry'], true)) {
            continue;
        }
        $adapters++;
        expect(count($method->stmts ?? []))->toBeLessThanOrEqual(3, $method->name->toString().' must be a compatibility adapter.');
    }
    expect($adapters)->toBe(5);
});
