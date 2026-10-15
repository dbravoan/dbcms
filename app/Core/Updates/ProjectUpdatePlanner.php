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
        if (in_array($path, ['cms-project.json', 'resources/updates/project-update.json'], true) || ! (new ProjectDeploymentPolicy)->includes($path, $profile['deployment'] ?? null)) {
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
}
