<?php

declare(strict_types=1);

use Modules\Blog\Http\Controllers\BlogFrontController;
use Modules\Pages\Http\Controllers\FrontPageController;

it('keeps public content actions thin and delegates persistence and filtering', function (string $controller): void {
    $class = new ReflectionClass($controller);
    $source = file_get_contents($class->getFileName());
    expect($source)->not->toMatch('/(?:Post|Category|Comment|Market)::(?:query|create|where|with|published|resolveBySlugPath)/');
    foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        if ($method->getDeclaringClass()->getName() === $controller && ! $method->isConstructor()) {
            expect($method->getEndLine() - $method->getStartLine() - 1)->toBeLessThanOrEqual(10);
        }
    }
})->with([BlogFrontController::class, FrontPageController::class]);
