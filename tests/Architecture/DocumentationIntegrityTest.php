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

/** @return list<string> */
function documentedArtisanCommands(string $content): array
{
    preg_match_all(
        '~(?<![\w/.\-])(?:php\h+artisan|(?:(?:\./)?vendor/bin/sail|sail)\h+(?:php\h+)?artisan)\h+([a-z][a-z0-9:._-]*)~i',
        $content,
        $matches,
    );

    return array_values(array_unique($matches[1]));
}

test('artisan documentation extraction recognizes command entry points and ignores unrelated text', function (string $content, array $expected): void {
    expect(documentedArtisanCommands($content))->toBe($expected);
})->with([
    'official Sail' => ['./vendor/bin/sail artisan migrate --force', ['migrate']],
    'Sail without dot prefix' => ['vendor/bin/sail artisan cms:project:init example', ['cms:project:init']],
    'Sail php passthrough' => ['./vendor/bin/sail php artisan config:cache', ['config:cache']],
    'Sail alias' => ['sail artisan list', ['list']],
    'container or production PHP' => ['php artisan queue:restart', ['queue:restart']],
    'unknown official command remains detectable' => ['./vendor/bin/sail artisan cms:missing-doc-command', ['cms:missing-doc-command']],
    'flags and other Sail commands' => ['./vendor/bin/sail test; ./vendor/bin/sail artisan --help', []],
    'prose and filenames' => ['Artisan::call("migrate"); docs/vendor/bin/sail; artisan command reference', []],
    'repeated command' => ["./vendor/bin/sail artisan migrate\nphp artisan migrate", ['migrate']],
]);

test('every artisan command referenced in the docs exists', function (): void {
    $available = array_keys(Artisan::all());
    $missing = [];

    foreach (documentedFiles() as $file) {
        foreach (documentedArtisanCommands((string) file_get_contents($file)) as $command) {
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
