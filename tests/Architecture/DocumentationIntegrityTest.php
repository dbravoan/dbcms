<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

/** @return list<string> */
function documentedFiles(): array
{
    $files = array_map(base_path(...), ['README.md', 'AGENTS.md', 'CONTRIBUTING.md', 'SECURITY.md', 'CHANGELOG.md']);

    foreach (['docs', 'specs', '.agents', '.gemini', '.opencode'] as $directory) {
        // Agent instructions are intentionally excluded from runtime artifacts.
        if (! is_dir(base_path($directory))) {
            continue;
        }
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($directory), FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && in_array($file->getExtension(), ['md', 'json'], true) && ! str_contains($file->getPathname(), '/archive/')) {
                $files[] = $file->getPathname();
            }
        }
    }

    sort($files);

    return $files;
}

function documentationIsCurrent(string $content): bool
{
    return preg_match('~^\h*(?:>\h*)?(?:\*\*)?Status(?:\*\*)?:\h*(?:`|\*\*)?superseded\b~mi', $content) !== 1;
}

/** @return list<string> */
function documentedArtisanCommands(string $content): array
{
    preg_match_all(
        '~(?<![\w/.\-])(?:php\h+artisan|(?:(?:\./)?vendor/bin/sail|sail)\h+(?:php\h+)?artisan)\h+([a-z][a-z0-9:._-]*)~i',
        $content,
        $matches,
    );

    // Explicit inline command declarations also cover prose without a shell
    // prefix. Arbitrary a:b values can be permissions, cache keys or examples.
    preg_match_all(
        '~(?:\b(?:command|comando)\h+`([a-z][a-z0-9_-]*(?::[a-z][a-z0-9_-]*)+)`|`([a-z][a-z0-9_-]*(?::[a-z][a-z0-9_-]*)+)`\h+(?:command|comando)\b)~',
        $content,
        $inline,
        PREG_SET_ORDER,
    );
    foreach ($inline as $declaration) {
        $matches[1][] = $declaration[1] !== '' ? $declaration[1] : $declaration[2];
    }

    return array_values(array_unique($matches[1]));
}

/** @return list<string> */
function documentedBinScripts(string $content): array
{
    preg_match_all('~(?<![\w/])(?:\./)?bin/([a-z0-9_-]+(?:\.(?:sh|mjs|py))?)(?=[`\s),;]|$)~i', $content, $matches);

    return array_values(array_unique($matches[1]));
}

/** @return list<string> */
function missingDocumentedLocalLinks(string $content, string $directory): array
{
    preg_match_all('~\]\(([^)\s]+)(?:\h+"[^"]*")?\)~', $content, $matches);
    $missing = [];
    foreach ($matches[1] as $target) {
        $path = rawurldecode(explode('#', trim($target, '<>'), 2)[0]);
        if ($path === '' || preg_match('~^[a-z][a-z0-9+.-]*:~i', $path) === 1) {
            continue;
        }
        if (! file_exists($directory.'/'.$path)) {
            $missing[] = $target;
        }
    }

    return array_values(array_unique($missing));
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
    'retired inline command remains detectable' => ['The `sections:normalize` command is safe.', ['sections:normalize']],
    'Spanish inline command declaration' => ['Ejecutar el comando `cms:install`.', ['cms:install']],
    'scopes and cache keys are not command declarations' => ['`manage:modules`, `confirm:true`, `cms:page-cache:version`', []],
    'flags and other Sail commands' => ['./vendor/bin/sail test; ./vendor/bin/sail artisan --help', []],
    'prose and filenames' => ['Artisan::call("migrate"); docs/vendor/bin/sail; artisan command reference', []],
    'repeated command' => ["./vendor/bin/sail artisan migrate\nphp artisan migrate", ['migrate']],
]);

test('documentation status distinguishes current contracts from historical specifications', function (): void {
    expect(documentationIsCurrent("# Old plan\nStatus: superseded\nphp artisan sections:normalize"))->toBeFalse()
        ->and(documentationIsCurrent("# Current plan\nStatus: approved\nRetired aliases are superseded."))->toBeTrue();
});

test('local documentation links detect a missing target without fetching external sources', function (): void {
    $content = '[valid](README.md#architecture) [broken](docs/does-not-exist.md) [remote](https://example.com/reference) [anchor](#current)';

    expect(missingDocumentedLocalLinks($content, base_path()))->toBe(['docs/does-not-exist.md']);
});

test('script extraction includes extensionless project entry points without matching vendor paths', function (): void {
    expect(documentedBinScripts('`bin/ci-check` ./bin/test-frontend.mjs vendor/bin/sail `bin/missing-command`'))->toBe(['ci-check', 'test-frontend.mjs', 'missing-command']);
});

test('every artisan command referenced in the docs exists', function (): void {
    $available = array_keys(Artisan::all());
    $missing = [];

    foreach (documentedFiles() as $file) {
        $content = (string) file_get_contents($file);
        if (! documentationIsCurrent($content)) {
            continue;
        }
        foreach (documentedArtisanCommands($content) as $command) {
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
        $content = (string) file_get_contents($file);
        if (! documentationIsCurrent($content)) {
            continue;
        }
        foreach (documentedBinScripts($content) as $script) {
            if (! is_file(base_path('bin/'.$script))) {
                $missing[] = "bin/{$script} (".basename($file).')';
            }
        }
    }

    expect($missing)->toBeEmpty('Unknown bin scripts: '.implode(', ', $missing));
});

test('maintained guides specifications and agent instructions link to existing local targets', function (): void {
    $missing = [];
    foreach (documentedFiles() as $file) {
        foreach (missingDocumentedLocalLinks((string) file_get_contents($file), dirname($file)) as $target) {
            $missing[] = $file.': '.$target;
        }
    }

    expect($missing)->toBeEmpty('Missing local documentation targets: '.implode(', ', $missing));
});

test('the directory index lists every document on disk', function (): void {
    $onDisk = collect(glob(base_path('docs/*.md')) ?: [])
        ->map(static fn (string $path): string => basename($path))
        ->sort()
        ->values();

    $index = (string) file_get_contents(base_path('docs/09_DIRECTORY_STRUCTURE_AND_FILE_INDEX.md'));

    $missing = $onDisk->reject(static fn (string $file): bool => $file === '09_DIRECTORY_STRUCTURE_AND_FILE_INDEX.md' || str_contains($index, $file))->values();

    expect($missing)->toBeEmpty('docs/09 must list: '.$missing->implode(', '));
});

test('agent instruction recipes resolve their canonical rule files', function (): void {
    foreach (glob(base_path('.opencode/agents/*.json')) ?: [] as $path) {
        $recipe = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        foreach ($recipe['rules'] ?? [] as $reference) {
            expect(base_path($reference))->toBeFile('Unresolved instruction in '.$path.': '.$reference);
        }
    }
});
