<?php

declare(strict_types=1);

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

if (is_file(__DIR__.'/../storage/app/private/core-updates/active.json')) {
    http_response_code(503);
    header('Cache-Control: no-store, private');
    header('Retry-After: 60');
    header('Content-Type: text/html; charset=UTF-8');
    exit('<!doctype html><html lang="es"><meta charset="utf-8"><meta name="robots" content="noindex,nofollow"><title>Actualización en curso</title><h1>Estamos actualizando el sitio</h1><p>Vuelve a intentarlo en unos instantes.</p></html>');
}

require_once __DIR__.'/../bootstrap/InstallerBootstrap.php';

$installerFailure = (new InstallerBootstrap(dirname(__DIR__), $_SERVER))->prepare();
if ($installerFailure !== null) {
    http_response_code(503);
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store, private');
    echo InstallerBootstrap::render($installerFailure);
    exit;
}

require_once __DIR__.'/../bootstrap/UpdateRuntime.php';
if (! UpdateRuntime::admit(dirname(__DIR__))) {
    UpdateRuntime::unavailable();
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
(require_once __DIR__.'/../bootstrap/app.php')
    ->handleRequest(Request::capture());
