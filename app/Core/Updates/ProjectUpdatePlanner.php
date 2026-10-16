<?php

declare(strict_types=1);

namespace App\Core\Updates;

use App\DTOs\ProjectUpdatePlan;

final class ProjectUpdatePlanner
{
    /**
     * @param  array<string,string>  $base
     * @param  array<string,string>  $local
     * @param  array<string,string>  $target
     * @param  array<string,mixed>  $profile
     */
    public function compare(array $base, array $local, array $target, array $profile): ProjectUpdatePlan
    {
        $this->validateSourceSelection($profile);
        $excluded = [];
        foreach (array_unique([...array_keys($base), ...array_keys($target)]) as $path) {
            (new UpdatePathPolicy)->requireAllowed($path);
            if (! $this->includes($path, $profile)) {
                $excluded[] = $path;
                unset($base[$path], $local[$path], $target[$path]);
            } elseif (($local[$path] ?? null) === ($target[$path] ?? null)) {
                // A previous manual integration already has the destination bytes.
                unset($base[$path], $local[$path], $target[$path]);
            }
        }
        $comparison = (new InventoryComparator)->compare($base, $local, $target);

        return new ProjectUpdatePlan($comparison['plan'], $comparison['conflicts'], $comparison['preserved'], $excluded);
    }

    /** @param array<string,mixed> $profile */
    public function includes(string $path, array $profile): bool
    {
        $this->validateSourceSelection($profile);
        $selection = $profile['source_selection'] ?? null;
        if ($selection !== null) {
            foreach (['modules' => 'module_directories', 'block-packages' => 'block_package_directories'] as $directory => $key) {
                if (preg_match('~^'.$directory.'/([^/]+)(?:/|$)~D', $path, $match)) {
                    return in_array($match[1], $selection[$key], true);
                }
            }
            if ($path === 'database/seeders/PlatformShowcaseSeeder.php' && ! $selection['include_showcase']) {
                return false;
            }
        }
        if (in_array($path, ['cms-project.json', 'resources/updates/project-update.json'], true) || ! (new ProjectDeploymentPolicy)->includes($path, $profile['deployment'])) {
            return false;
        }
        if (preg_match('~^themes/([^/]+)/~D', $path, $match)) {
            return in_array($match[1], $profile['themes'], true);
        }
        if (preg_match('~^resources/views/themes/admin/([^/]+)/~D', $path, $match)) {
            return $match[1] === $profile['brand'];
        }

        return true;
    }

    /** @param array<string,mixed> $profile */
    public function validateSourceSelection(array $profile): void
    {
        if (! array_key_exists('source_selection', $profile)) {
            return;
        }
        $selection = $profile['source_selection'];
        $keys = ['module_directories', 'block_package_directories', 'include_showcase'];
        if (! is_array($selection) || count($selection) !== count($keys) || array_diff(array_keys($selection), $keys) !== [] || ! is_bool($selection['include_showcase'] ?? null)) {
            throw new \DomainException('Invalid project source selection.');
        }
        foreach (['module_directories', 'block_package_directories'] as $key) {
            $directories = $selection[$key] ?? null;
            if (! is_array($directories) || ! array_is_list($directories)) {
                throw new \DomainException('Project source directories must be lists.');
            }
            $seen = [];
            foreach ($directories as $directory) {
                if (! is_string($directory) || ! preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/D', $directory) || in_array($directory, $seen, true)) {
                    throw new \DomainException('Invalid or duplicate project source directory.');
                }
                $seen[] = $directory;
            }
        }
    }
}
