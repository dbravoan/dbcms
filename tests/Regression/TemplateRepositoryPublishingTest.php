<?php

declare(strict_types=1);

test('a project created from the template publishes images to its own repository', function (): void {
    $workflow = (string) file_get_contents(__DIR__.'/../../.github/workflows/release.yml');
    preg_match('/^\s+images:\s*(.+)$/m', $workflow, $matches);
    $target = trim($matches[1] ?? '');
    // Simulate the same workflow being copied to the first independent project.
    $resolved = str_replace('${{ github.repository }}', 'dbravoan/regulase', $target);
    expect($resolved)->toBe('ghcr.io/dbravoan/regulase');
});
