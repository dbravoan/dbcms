<?php

declare(strict_types=1);

define('LARAVEL_START', microtime(true));

$root = dirname(__DIR__);
$activeFile = $root.'/storage/app/private/core-updates/active.json';
$runtime = $root.'/bootstrap/UpdateRuntime.php';
if (is_file($activeFile)) {
    $active = json_decode((string) file_get_contents($activeFile), true);
    $id = is_array($active) ? ($active['run_id'] ?? '') : '';
    if (is_string($id) && preg_match('/^[a-f0-9-]{36}$/D', $id)) {
        $snapshot = $root.'/storage/app/private/core-updates/'.$id.'/runtime/UpdateRuntime.php';
        if (is_file($snapshot)) {
            $runtime = $snapshot;
        }
    }
}
require_once $runtime;
UpdateRuntime::serve($root);
