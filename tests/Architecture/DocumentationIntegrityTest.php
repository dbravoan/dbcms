<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

/**
 * Documentation guardrails: the docs must describe the app that actually
 * exists. These tests fail when a documented artisan command or bin script is
 * missing, or when a document is not listed in the directory index.
 */
function documentedFiles(): array
{
    $files = [base_path('README.md'), base_path('AGENTS.md')];

    foreach (glob(base_path('docs/*.md')) ?: [] as $doc) {
        $files[] = $doc;
    }

    return $files;
}

test('every artisan command referenced in the docs exists', function (): void {
    $available = array_keys(Artisan::all());
    $missing = [];

    foreach (documentedFiles() as $file) {
        preg_match_all(
            '/(?:php\s+artisan|sail-run\s+php\s+artisan)\s+([a-z][a-z0-9:._-]+)/i',
            (string) file_get_contents($file),
            $matches,
        );

        foreach (array_unique($matches[1]) as $command) {
            if (! in_array($command, $available, true)) {
                $missing[] = "{$command} (".basename($file).')';
            }
        }
    }

    expect($missing)->toBeEmpty('Unknown artisan commands: '.implode(', ', $missing));
});

test('every bin script referenced in the docs exists', function (): void {
    $missing = [];

    foreach (documentedFiles() as $file) {
        preg_match_all('#(?:\./)?bin/([a-z0-9_-]+\.(?:sh|mjs|py))#i', (string) file_get_contents($file), $matches);

        foreach (array_unique($matches[1]) as $script) {
            if (! is_file(base_path('bin/'.$script))) {
                $missing[] = "bin/{$script} (".basename($file).')';
            }
        }
    }

    expect($missing)->toBeEmpty('Unknown bin scripts: '.implode(', ', $missing));
});

test('the directory index lists every document on disk', function (): void {
    $onDisk = collect(glob(base_path('docs/*.md')) ?: [])
        ->map(static fn (string $path): string => basename($path))
        ->sort()
        ->values();

    $index = (string) file_get_contents(base_path('docs/09_DIRECTORY_STRUCTURE_AND_FILE_INDEX.md'));

    $missing = $onDisk->reject(static fn (string $file): bool => str_contains($index, $file))->values();

    expect($missing)->toBeEmpty('docs/09 must list: '.$missing->implode(', '));
});
