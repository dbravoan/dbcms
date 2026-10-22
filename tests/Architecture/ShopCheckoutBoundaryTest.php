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
    foreach ($finder->findInstanceOf($nodes, ClassMethod::class) as $method) {
        if (! $method->isPublic() || $method->name->toString() === '__construct') {
            continue;
        }
        expect(count($method->stmts ?? []))->toBeLessThanOrEqual(3);
    }
});
