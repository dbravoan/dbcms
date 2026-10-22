<?php

declare(strict_types=1);

namespace App\Core\Updates;

final readonly class ReleaseManifest
{
    /** @param array<string,mixed> $data */
    public function __construct(public array $data, public string $hash)
    {
        if (($data['schema'] ?? null) !== 1 || ! is_string($data['version'] ?? null) || ! preg_match('/^\d+\.\d+\.\d+$/D', $data['version']) || ! is_string($data['source_sha'] ?? null) || ! preg_match('/^[a-f0-9]{40}$/D', $data['source_sha'])) {
            throw new \DomainException('Identidad del manifiesto no válida.');
        }
        if (! is_string($data['min_php'] ?? null) || ! is_array($data['extensions'] ?? null) || ! is_array($data['supported_from'] ?? null) || ! is_bool($data['rollback_compatible'] ?? null) || ! is_array($data['migrations'] ?? null) || ! is_array($data['distributions'] ?? null)) {
            throw new \DomainException('Manifiesto incompleto.');
        }
        foreach (['extensions', 'supported_from'] as $field) {
            foreach ($data[$field] as $value) {
                if (! is_string($value)) {
                    throw new \DomainException('Lista del manifiesto no válida.');
                }
            }
        }
        if (! preg_match('/^\d+\.\d+(?:\.\d+)?$/D', $data['min_php'])) {
            throw new \DomainException('Versión PHP mínima no válida.');
        }
        foreach ($data['extensions'] as $extension) {
            if (! preg_match('/^[a-zA-Z0-9_]+$/D', $extension)) {
                throw new \DomainException('Extensión PHP no válida.');
            }
        }
        foreach ($data['supported_from'] as $version) {
            if (! preg_match('/^\d+\.\d+\.\d+$/D', $version)) {
                throw new \DomainException('Versión de origen no válida.');
            }
        }
        if (isset($data['next_keys']) && ! is_array($data['next_keys'])) {
            throw new \DomainException('Rotación de clave no válida.');
        }
        foreach (($data['next_keys'] ?? []) as $id => $pem) {
            if (! is_string($id) || ! preg_match('/^[a-zA-Z0-9_-]+$/D', $id) || ! is_string($pem)) {
                throw new \DomainException('Rotación de clave no válida.');
            }
            $key = openssl_pkey_get_public($pem);
            $details = $key === false ? false : openssl_pkey_get_details($key);
            if (! is_array($details) || $details['type'] !== OPENSSL_KEYTYPE_RSA || $details['bits'] < 3072) {
                throw new \DomainException('Clave de rotación insuficiente.');
            }
        }
        if (! is_array($data['migrations']['core'] ?? null) || ! is_array($data['migrations']['modules'] ?? null)) {
            throw new \DomainException('Inventario de migraciones no válido.');
        }
        foreach ($data['migrations']['core'] as $path) {
            if (! is_string($path) || ! preg_match('~^database/migrations/[a-zA-Z0-9_]+\.php$~D', $path)) {
                throw new \DomainException('Migración de núcleo no válida.');
            }
        }
        foreach ($data['migrations']['modules'] as $module => $paths) {
            if (! is_string($module) || ! preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/D', $module) || ! is_array($paths)) {
                throw new \DomainException('Migraciones de módulo no válidas.');
            }
            foreach ($paths as $path) {
                if (! is_string($path) || ! preg_match('~^modules/[a-zA-Z][a-zA-Z0-9_-]*/Database/Migrations/[a-zA-Z0-9_]+\.php$~D', $path)) {
                    throw new \DomainException('Ruta de migración de módulo no válida.');
                }
            }
        }
        if (! isset($data['distributions']['source'])) {
            throw new \DomainException('Falta la distribución fuente.');
        }
        foreach (array_keys($data['distributions']) as $name) {
            if (! in_array($name, ['source', 'runtime'], true)) {
                throw new \DomainException('Distribución no admitida.');
            }
            $distribution = $data['distributions'][$name] ?? null;
            if (! is_array($distribution) || ! is_string($distribution['asset'] ?? null) || basename($distribution['asset']) !== $distribution['asset'] || ! is_string($distribution['sha256'] ?? null) || ! preg_match('/^[a-f0-9]{64}$/D', $distribution['sha256']) || ! is_int($distribution['size'] ?? null) || $distribution['size'] < 1 || ! is_array($distribution['files'] ?? null) || $distribution['files'] === []) {
                throw new \DomainException('Distribución del manifiesto no válida.');
            }
            $seen = [];
            foreach ($distribution['files'] as $path => $digest) {
                if (! is_string($path) || ! is_string($digest) || ! preg_match('/^[a-f0-9]{64}$/D', $digest)) {
                    throw new \DomainException('Inventario del manifiesto no válido.');
                }
                (new UpdatePathPolicy)->requireAllowed($path);
                $key = strtolower($path);
                if (isset($seen[$key])) {
                    throw new \DomainException('Inventario con rutas ambiguas.');
                }
                $seen[$key] = true;
            }
            foreach (array_keys($seen) as $key) {
                $parent = dirname($key);
                while ($parent !== '.') {
                    if (isset($seen[$parent])) {
                        throw new \DomainException('Colisión entre archivo y directorio.');
                    }
                    $parent = dirname($parent);
                }
            }
        }
    }

    /** @return array{asset:string,sha256:string,size:int,files:array<string,string>} */
    public function distribution(string $name): array
    {
        if (! in_array($name, ['source', 'runtime'], true) || ! isset($this->data['distributions'][$name])) {
            throw new \DomainException('Distribución desconocida.');
        }

        return $this->data['distributions'][$name];
    }
}
