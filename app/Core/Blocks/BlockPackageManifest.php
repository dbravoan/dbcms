<?php

declare(strict_types=1);

namespace App\Core\Blocks;

use Composer\Semver\Semver;
use DomainException;

/** Shared manifest validation; reads no availability or installation state. */
final class BlockPackageManifest
{
    /** @param array<string,mixed> $manifest */
    public static function validate(array $manifest, string $folder): void
    {
        if (! is_string($manifest['identifier'] ?? null) || ! preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $manifest['identifier']) || ! is_string($manifest['name'] ?? null) || trim($manifest['name']) === '' || ! is_string($manifest['version'] ?? null) || ! preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/D', $manifest['version']) || ! is_array($manifest['blocks'] ?? null) || $manifest['blocks'] === [] || ! array_is_list($manifest['blocks'])) {
            throw new DomainException('Invalid block package identity or definitions.');
        }
        foreach ($manifest['blocks'] as $class) {
            if (! is_string($class) || ! str_starts_with($class, 'BlockPackages\\'.$folder.'\\') || ! preg_match('/^BlockPackages\\\\[A-Za-z][A-Za-z0-9]*(?:\\\\[A-Za-z][A-Za-z0-9]*)+$/D', $class)) {
                throw new DomainException('Block definitions must belong to the package namespace.');
            }
        }
        if (isset($manifest['requires']) && ! is_array($manifest['requires'])) {
            throw new DomainException('Invalid package requirements.');
        }
        foreach ($manifest['requires'] ?? [] as $id => $constraint) {
            if (! is_string($id) || ! preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $id) || ! is_string($constraint) || trim($constraint) === '') {
                throw new DomainException('Invalid package requirements.');
            }
            try {
                Semver::satisfies('1.0.0', $constraint);
            } catch (\UnexpectedValueException) {
                throw new DomainException('Invalid package version constraint.');
            }
        }
    }
}
