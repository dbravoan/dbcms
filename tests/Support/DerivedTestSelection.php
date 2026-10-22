<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Updates\ProjectDeploymentPolicy;
use App\Services\Projects\ProjectSourceSelection;
use DomainException;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Filesystem\Filesystem;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/** Reads an artifact's declared source selection without booting its providers. */
final class DerivedTestSelection
{
    /** @param array<string, array{modules?: list<string>, any_modules?: list<string>, block_packages?: list<string>, themes?: list<string>, brands?: list<string>, deployments?: list<string>, showcase?: bool, source_catalogue?: bool}> $requirements */
    public function __construct(private readonly string $root, private readonly array $requirements) {}

    /** @return array{profile: array<string, mixed>, files: list<string>} */
    public function plan(string $lane = 'integration'): array
    {
        $path = $this->root.'/cms-project.json';
        if (! is_file($path) || is_link($path)) {
            throw new DomainException('Derived quality requires an ordinary cms-project.json.');
        }
        $profile = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($profile) || ($profile['schema'] ?? null) !== 1 || ! is_string($profile['brand'] ?? null)
            || ! is_array($profile['themes'] ?? null) || ! array_is_list($profile['themes']) || $profile['themes'] === []
            || ! in_array($profile['default_theme'] ?? null, $profile['themes'], true)) {
            throw new DomainException('Invalid derived quality selection.');
        }
        $coreProfile = $profile['core'] ?? null;
        if (! is_string($profile['name'] ?? null) || ! preg_match('/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/D', $profile['name'])
            || ! in_array($profile['brand'], ['dbcms', 'termocms'], true)
            || ! in_array($profile['deployment'] ?? null, ProjectDeploymentPolicy::DRIVERS, true)
            || ! is_array($coreProfile) || ($coreProfile['repository'] ?? null) !== 'dbravoan/dbcms'
            || ! is_string($coreProfile['version'] ?? null) || ! preg_match('/^\d+\.\d+\.\d+$/D', $coreProfile['version'])
            || ! is_string($coreProfile['sha'] ?? null) || ! preg_match('/^[a-f0-9]{40}$/D', $coreProfile['sha'])) {
            throw new DomainException('Invalid canonical project profile.');
        }
        $this->checkThemes('themes', $profile['themes']);
        $this->checkThemes('resources/views/themes/admin', [$profile['brand']]);
        $selection = $profile['source_selection'] ?? null;
        $descriptor = $this->root.'/resources/updates/core-version.json';
        if (! is_file($descriptor) || is_link($descriptor)) {
            throw new DomainException('Missing ordinary core version descriptor.');
        }
        $core = json_decode((string) file_get_contents($descriptor), true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($core) || ! is_string($core['version'] ?? null)) {
            throw new DomainException('Invalid core version descriptor.');
        }
        $sources = new ProjectSourceSelection(new Filesystem, $this->root, $core['version']);
        if (array_key_exists('source_selection', $profile)) {
            if (! is_array($selection)) {
                throw new DomainException('Invalid project source selection.');
            }
            $sources->assertPhysicalSelection($selection);
        } else {
            // Absence retains the complete-source contract: validate installed
            // manifests and run every test, rather than inferring exclusions.
            $sources->resolve();
        }

        $files = [];
        foreach ($this->candidates($lane) as $relative) {
            if (! $this->available($relative, $selection, $profile)) {
                continue;
            }
            // Unknown test files always run. Their absence from the explicit
            // requirement map cannot silently remove coverage.
            $files[] = $relative;
        }
        sort($files);
        if ($files === []) {
            throw new DomainException('No derived quality tests selected.');
        }

        return ['profile' => $profile, 'files' => $files];
    }

    /** @return list<string> */
    private function candidates(string $lane): array
    {
        if (! in_array($lane, ['fast', 'integration', 'release'], true)) {
            throw new DomainException('Unknown quality lane.');
        }
        $document = new DOMDocument;
        $document->load($this->root.($lane === 'fast' ? '/phpunit.fast.xml' : '/phpunit.xml'));
        $xpath = new DOMXPath($document);
        $files = [];
        $entries = $xpath->query('/phpunit/testsuites/testsuite/*');
        if ($entries === false) {
            throw new DomainException('Invalid quality suite configuration.');
        }
        foreach ($entries as $entry) {
            if (! $entry instanceof DOMElement || ! $entry->parentNode instanceof DOMElement) {
                throw new DomainException('Invalid quality suite entry.');
            }
            if ($lane === 'integration' && ! in_array($entry->parentNode->getAttribute('name'), ['Projects', 'Architecture', 'Unit', 'Smoke', 'Feature', 'Regression'], true)) {
                continue;
            }
            $relative = str_starts_with(trim($entry->textContent), './') ? substr(trim($entry->textContent), 2) : trim($entry->textContent);
            $path = $this->root.'/'.$relative;
            if ($entry->nodeName === 'file') {
                if (! is_file($path) || is_link($path)) {
                    throw new DomainException('A configured quality test file is missing: '.$relative);
                }
                $files[] = $relative;
            } elseif ($entry->nodeName === 'directory' && is_dir($path)) {
                $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS));
                foreach ($iterator as $file) {
                    /** @var SplFileInfo $file */
                    if ($file->isFile() && str_ends_with($file->getFilename(), 'Test.php')) {
                        $files[] = substr($file->getPathname(), strlen($this->root) + 1);
                    }
                }
            }
        }

        return array_values(array_unique($files));
    }

    /** @param list<mixed> $selected */
    private function checkThemes(string $directory, array $selected): void
    {
        $resourceRoot = $this->root.'/'.$directory;
        if (is_link($resourceRoot) || ! is_dir($resourceRoot)) {
            throw new DomainException('Theme source roots must be ordinary directories.');
        }
        if (count(array_unique($selected, SORT_REGULAR)) !== count($selected)) {
            throw new DomainException('Duplicate selected theme.');
        }
        $physical = [];
        foreach (glob($this->root.'/'.$directory.'/*') ?: [] as $path) {
            if (! is_dir($path)) {
                continue;
            }
            $manifest = $path.'/theme.json';
            if (is_link($path) || ! is_file($manifest) || is_link($manifest)) {
                throw new DomainException('Theme sources require ordinary directories and manifests.');
            }
            $data = json_decode((string) file_get_contents($manifest), true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($data) || ($data['identifier'] ?? null) !== basename($path)) {
                throw new DomainException('Theme manifest identity does not match its directory.');
            }
            $physical[] = basename($path);
        }
        ProjectSourceSelection::assertOrdinaryTree($resourceRoot);
        foreach ($selected as $identifier) {
            if (! is_string($identifier) || ! preg_match('/^[a-z][a-z0-9-]*$/D', $identifier)) {
                throw new DomainException('Invalid selected theme identifier.');
            }
        }
        sort($physical);
        sort($selected);
        if ($physical !== $selected) {
            throw new DomainException('Physical themes do not match the declared project selection.');
        }
    }

    /**
     * @param  array<string, mixed>|null  $selection
     * @param  array<string, mixed>  $profile
     */
    private function available(string $path, ?array $selection, array $profile): bool
    {
        $requirements = $this->requirements[$path] ?? [];
        if (array_diff([$profile['deployment']], $requirements['deployments'] ?? [$profile['deployment']]) !== []) {
            return false;
        }
        if ($requirements['source_catalogue'] ?? false) {
            return false;
        }
        if (array_diff($requirements['themes'] ?? [], $profile['themes']) !== [] || array_diff($requirements['brands'] ?? [], [$profile['brand']]) !== []) {
            return false;
        }
        if ($selection === null) {
            return true;
        }
        if (isset($requirements['any_modules']) && array_intersect($requirements['any_modules'], $selection['module_directories']) === []) {
            return false;
        }
        foreach (['modules' => 'module_directories', 'block_packages' => 'block_package_directories'] as $kind => $key) {
            if (array_diff($requirements[$kind] ?? [], $selection[$key]) !== []) {
                return false;
            }
        }

        return ! ($requirements['showcase'] ?? false) || $selection['include_showcase'];
    }
}
