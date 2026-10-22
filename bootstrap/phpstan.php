<?php

declare(strict_types=1);

// Analysis covers every source package, including packages not installed in
// the running product. Expose their Blade namespaces without booting providers
// or changing the installation's composition.
foreach (glob(dirname(__DIR__).'/modules/*/module.json') ?: [] as $path) {
    $manifest = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    $views = dirname($path).'/Resources/views';
    if (is_array($manifest) && is_string($manifest['identifier'] ?? null) && is_dir($views)) {
        app('view')->addNamespace($manifest['identifier'], $views);
    }
}
