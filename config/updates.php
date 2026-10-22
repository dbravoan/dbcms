<?php

declare(strict_types=1);

$trustedKeys = [];
foreach (glob(resource_path('updates/trusted-keys/*.pub')) ?: [] as $keyFile) {
    $pem = file_get_contents($keyFile);
    if (is_string($pem)) {
        $trustedKeys[pathinfo($keyFile, PATHINFO_FILENAME)] = $pem;
    }
}

return [
    'repository' => 'dbravoan/dbcms',
    'root_path' => base_path(),
    'storage_path' => storage_path('app/private/core-updates'),
    'check_interval' => 43200,
    'trusted_keys' => $trustedKeys,
    'max_archive_bytes' => 536870912,
    'max_expanded_bytes' => 2147483648,
    'max_files' => 50000,
    'web_apply_enabled' => env('CMS_WEB_UPDATES_ENABLED', true),
    'execution_model' => env('CMS_UPDATE_EXECUTION_MODEL', 'single_host_http'),
];
