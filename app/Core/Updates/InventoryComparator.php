<?php

declare(strict_types=1);

namespace App\Core\Updates;

final class InventoryComparator
{
    /**
     * @param  array<string,string>  $base
     * @param  array<string,string>  $local
     * @param  array<string,string>  $target
     * @return array{plan:list<array{path:string,old_hash:?string,new_hash:?string,action:string}>,conflicts:list<array{path:string,reason:string}>,preserved:list<string>}
     */
    public function compare(array $base, array $local, array $target): array
    {
        $result = ['plan' => [], 'conflicts' => [], 'preserved' => []];
        $paths = array_unique([...array_keys($base), ...array_keys($target)]);
        sort($paths);
        foreach ($paths as $path) {
            (new UpdatePathPolicy)->requireAllowed($path);
            $before = $base[$path] ?? null;
            $current = $local[$path] ?? null;
            $after = $target[$path] ?? null;
            if ($after === $before) {
                if ($current !== $before) {
                    $result['preserved'][] = $path;
                }

                continue;
            }
            if ($current !== $before) {
                $result['conflicts'][] = ['path' => $path, 'reason' => $before === null ? 'local_collision' : 'locally_modified'];

                continue;
            }
            $result['plan'][] = ['path' => $path, 'old_hash' => $current, 'new_hash' => $after, 'action' => $after === null ? 'delete' : ($current === null ? 'create' : 'replace')];
        }

        return $result;
    }
}
