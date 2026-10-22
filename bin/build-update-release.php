<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/app/Core/Updates/UpdatePathPolicy.php';

use App\Core\Updates\UpdatePathPolicy;

$options = getopt('', ['source-root:', 'runtime-root:', 'version:', 'sha:', 'output:', 'key-file:', 'key-id:', 'prefix:']);
foreach (['source-root', 'version', 'sha', 'output', 'key-file', 'key-id'] as $required) {
    if (! isset($options[$required]) || ! is_string($options[$required]) || $options[$required] === '') {
        throw new RuntimeException('Missing signing/build option: '.$required);
    }
}
if (! preg_match('/^\d+\.\d+\.\d+$/D', $options['version']) || ! preg_match('/^[a-f0-9]{40}$/D', $options['sha']) || ! preg_match('/^[a-zA-Z0-9_-]+$/D', $options['key-id'])) {
    throw new RuntimeException('Invalid immutable release identity.');
}
$private = openssl_pkey_get_private(file_get_contents($options['key-file']) ?: '');
$details = $private === false ? false : openssl_pkey_get_details($private);
if ($private === false || ! is_array($details) || $details['type'] !== OPENSSL_KEYTYPE_RSA || $details['bits'] < 3072) {
    throw new RuntimeException('A real RSA release signing key of at least 3072 bits is required.');
}
$trustedFile = $options['source-root'].'/resources/updates/trusted-keys/'.$options['key-id'].'.pub';
if (! is_file($trustedFile)) {
    $trustedFile = dirname(__DIR__).'/resources/updates/trusted-keys/'.$options['key-id'].'.pub';
}
$trusted = is_file($trustedFile) ? openssl_pkey_get_public(file_get_contents($trustedFile) ?: '') : false;
$trustedDetails = $trusted === false ? false : openssl_pkey_get_details($trusted);
if (! is_array($trustedDetails) || $trustedDetails['key'] !== $details['key']) {
    throw new RuntimeException('Release signing key does not match the pinned public key.');
}
$output = rtrim($options['output'], '/');
if (! is_dir($output) && ! mkdir($output, 0700, true)) {
    throw new RuntimeException('Cannot create artifact directory.');
}
$policy = json_decode(file_get_contents(dirname(__DIR__).'/resources/updates/release-policy.json') ?: '{}', true, 16, JSON_THROW_ON_ERROR);
$matching = ($policy['version'] ?? null) === $options['version'];
$manifest = ['schema' => 1, 'version' => $options['version'], 'source_sha' => $options['sha'], 'min_php' => $matching ? $policy['min_php'] : '8.3.0', 'extensions' => $matching ? $policy['extensions'] : ['openssl', 'pdo_mysql', 'mbstring', 'zip'], 'supported_from' => $matching ? $policy['supported_from'] : [], 'rollback_compatible' => $matching && ($policy['rollback_compatible'] ?? false) === true, 'migrations' => ['core' => [], 'modules' => []], 'distributions' => []];
$manifest['next_keys'] = [];
foreach (glob($options['source-root'].'/resources/updates/trusted-keys/*.pub') ?: [] as $keyFile) {
    $manifest['next_keys'][basename($keyFile, '.pub')] = file_get_contents($keyFile);
}
foreach (['source', 'runtime'] as $distribution) {
    $root = $options[$distribution.'-root'] ?? null;
    if (! is_string($root)) {
        continue;
    }
    $root = realpath($root);
    if ($root === false) {
        throw new RuntimeException('Missing distribution tree.');
    }
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        $path = substr($file->getPathname(), strlen($root) + 1);
        if (! (new UpdatePathPolicy)->allows($path) || ! $file->isFile() || $file->isLink()) {
            continue;
        }
        if ($distribution === 'source' && (str_starts_with($path, 'vendor/') || str_starts_with($path, 'public/build/'))) {
            continue;
        }
        $hash = hash_file('sha256', $file->getPathname());
        if ($hash === false) {
            throw new RuntimeException('Cannot hash release file.');
        }
        $files[$path] = $hash;
        if ($distribution === 'source' && preg_match('~^database/migrations/[^/]+\.php$~', $path)) {
            $manifest['migrations']['core'][] = $path;
        }
        if ($distribution === 'source' && preg_match('~^modules/([^/]+)/Database/Migrations/[^/]+\.php$~', $path, $match)) {
            $module = json_decode(file_get_contents($root.'/modules/'.$match[1].'/module.json') ?: '{}', true, 16, JSON_THROW_ON_ERROR);
            if (! is_string($module['identifier'] ?? null)) {
                throw new RuntimeException('Module migration missing owner identifier.');
            }
            $manifest['migrations']['modules'][$module['identifier']][] = $path;
        }
    }
    ksort($files);
    $asset = isset($options['prefix']) ? $options['prefix'].'-'.$distribution.'.zip' : 'dbcms-update-'.$distribution.'-v'.$options['version'].'.zip';
    $archive = $output.'/'.$asset;
    $zip = new ZipArchive;
    if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Cannot build update archive.');
    }
    foreach ($files as $path => $hash) {
        if (! $zip->addFile($root.'/'.$path, $path)) {
            throw new RuntimeException('Cannot add update file.');
        }
    }
    $zip->close();
    $manifest['distributions'][$distribution] = ['asset' => $asset, 'sha256' => hash_file('sha256', $archive), 'size' => filesize($archive), 'files' => $files];
}
$prefix = isset($options['prefix']) ? $options['prefix'].'-manifest' : 'manifest';
if (! is_string($prefix) || ! preg_match('/^[a-zA-Z0-9._-]+$/D', $prefix)) {
    throw new RuntimeException('Invalid manifest asset prefix.');
}
$bytes = json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
if (! openssl_sign($bytes, $signature, $private, OPENSSL_ALGO_SHA256)) {
    throw new RuntimeException('Cannot sign release manifest.');
}
file_put_contents($output.'/'.$prefix.'.json', $bytes);
file_put_contents($output.'/'.$prefix.'.sig', json_encode(['key_id' => $options['key-id'], 'signature' => base64_encode($signature)], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n");
