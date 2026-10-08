<?php

declare(strict_types=1);

use App\Core\Search\SearchEngine;
use App\Core\Search\SearchIndexer;
use Modules\Gallery\Http\Controllers\GalleryFrontController;
use Modules\Gallery\Models\Gallery;
use Modules\Gallery\Services\GallerySeoSchemaService;
use Modules\Search\Http\Controllers\SearchFrontController;
use Modules\Search\Services\SearchAnalyticsService;

test('gallery and search public transports delegate queries and domain presentation', function (): void {
    expect([GalleryFrontController::class, SearchFrontController::class])
        ->not->toUse([Gallery::class, GallerySeoSchemaService::class, SearchEngine::class, SearchIndexer::class, SearchAnalyticsService::class]);
    foreach ([GalleryFrontController::class => ['index', 'show'], SearchFrontController::class => ['hub', 'results']] as $controller => $actions) {
        $class = new ReflectionClass($controller);
        $source = file_get_contents($class->getFileName());
        expect($source)->not->toMatch('/(?:Gallery::|->(?:where|query|paginate|search|record)\()/');
        foreach ([Gallery::class, GallerySeoSchemaService::class, SearchEngine::class, SearchIndexer::class, SearchAnalyticsService::class] as $forbidden) {
            expect($source)->not->toContain('use '.$forbidden.';');
        }
        foreach ($actions as $action) {
            $method = new ReflectionMethod($controller, $action);
            expect($method->getEndLine() - $method->getStartLine() - 1)->toBeLessThanOrEqual(10);
        }
    }
});
