<?php

declare(strict_types=1);

namespace App\Core\Updates;

use Illuminate\Support\Str;

final class UpdateRunStore
{
    public function root(): string
    {
        $root = (string) config('updates.storage_path', storage_path('app/private/core-updates'));
        $this->rejectSymlinkAncestors($root);
        if (! is_dir($root) && ! mkdir($root, 0700, true) && ! is_dir($root)) {
            throw new \RuntimeException('No se puede crear el almacenamiento privado de actualización.');
        }

        return $root;
    }

    public function directory(string $id): string
    {
        if (! Str::isUuid($id)) {
            throw new \DomainException('Identificador de actualización no válido.');
        }

        $path = $this->root().'/'.$id;
        $this->rejectSymlinkAncestors($path);

        return $path;
    }

    /**
     * @param array<string,mixed> $run
     * @return array<string,mixed> */
    public function create(array $run): array
    {
        $id = (string) Str::uuid();
        if (! mkdir($this->directory($id), 0700)) {
            throw new \RuntimeException('No se puede crear la ejecución.');
        }

        return $this->save($id, [...$run, 'id' => $id, 'created_at' => gmdate(DATE_ATOM)]);
    }

    /**
     * @return array<string,mixed> */
    public function read(string $id): array
    {
        $json = @file_get_contents($this->directory($id).'/run.json');
        if ($json === false) {
            throw new \DomainException('Ejecución inexistente.');
        }
        $run = json_decode($json, true, 128, JSON_THROW_ON_ERROR);
        if (! is_array($run) || ($run['id'] ?? null) !== $id) {
            throw new \DomainException('Registro de ejecución no válido.');
        }

        return $run;
    }

    /**
     * @param array<string,mixed> $run
     * @return array<string,mixed> */
    public function save(string $id, array $run): array
    {
        $run['id'] = $id;
        $run['updated_at'] = gmdate(DATE_ATOM);
        $this->atomic($this->directory($id).'/run.json', json_encode($run, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $summary = array_intersect_key($run, array_flip(['id', 'actor_id', 'status', 'mode', 'version', 'created_at', 'updated_at', 'completed_at']));
        $this->atomic($this->directory($id).'/summary.json', json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return $run;
    }

    public function withLock(string $id, callable $callback): mixed
    {
        $handle = fopen($this->directory($id).'/run.lock', 'c');
        if ($handle === false || ! flock($handle, LOCK_EX | LOCK_NB)) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            throw new \DomainException('La actualización ya está siendo procesada.');
        }
        try {
            return $callback($this->read($id));
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * @return array<string,mixed>|null */
    public function identity(): ?array
    {
        $json = @file_get_contents($this->root().'/identity.json');
        if ($json === false) {
            return null;
        }
        $identity = json_decode($json, true, 128, JSON_THROW_ON_ERROR);

        return is_array($identity) ? $identity : null;
    }

    public function atomic(string $path, string $contents): void
    {
        $this->rejectSymlinkAncestors($path);
        $temp = tempnam(dirname($path), '.update-');
        if ($temp === false) {
            throw new \RuntimeException('No se puede escribir el registro de actualización.');
        }
        $handle = false;
        try {
            $handle = fopen($temp, 'wb');
            if ($handle === false || ! chmod($temp, 0600) || fwrite($handle, $contents) !== strlen($contents) || ! fflush($handle) || ! fsync($handle)) {
                throw new \RuntimeException('No se puede persistir el registro de actualización.');
            }
            fclose($handle);
            $handle = false;
            if (! rename($temp, $path)) {
                throw new \RuntimeException('No se puede persistir el registro de actualización.');
            }
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
            if (is_file($temp)) {
                unlink($temp);
            }
        }
    }

    private function rejectSymlinkAncestors(string $path): void
    {
        for ($current = $path; $current !== dirname($current); $current = dirname($current)) {
            if (is_link($current)) {
                throw new \DomainException('El almacenamiento de actualización no puede atravesar enlaces.');
            }
        }
    }
}
