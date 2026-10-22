<?php

declare(strict_types=1);

/**
 * Guardrail for animated canvases: they must honour `prefers-reduced-motion`
 * and release the animation frame when the tab is hidden, instead of spinning
 * requestAnimationFrame forever.
 */
test('animated canvases respect reduced motion and pause when the tab is hidden', function (string $view): void {
    $contents = (string) file_get_contents(base_path($view));

    expect($contents)
        ->toContain('prefers-reduced-motion')
        ->toContain('visibilitychange')
        ->toContain('cancelAnimationFrame');
})->with([
    'front canvas (db-cyber)' => 'themes/db-cyber/layouts/app.blade.php',
    'backoffice maintenance canvas' => 'modules/Maintenance/Resources/views/index.blade.php',
]);
