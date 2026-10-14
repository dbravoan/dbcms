<?php

declare(strict_types=1);

namespace App\Core\Updates;

use RuntimeException;

/** Framework-free: the recovery snapshot continues working without Composer/vendor. */
final class FileUpdateEngine
{
    public function __construct(private readonly string $root, private readonly string $stage, private readonly string $private) {}

    /** @param list<array{path:string,action:string,old_hash:?string,new_hash:?string}> $plan
     * @return array<string,?string>
     */
    public function backupPlan(array $plan): array
    {
        $inventory = [];
        foreach ($plan as $entry) {
            $destination = $this->path($this->root, $entry['path']);
            if ($this->hash($destination) !== $entry['old_hash']) {
                throw new RuntimeException('El archivo cambió antes del respaldo: '.$entry['path']);
            }
            $this->backup($entry['path'], $destination, $entry['old_hash']);
            $inventory[$entry['path']] = $entry['old_hash'];
        }
        self::writeJson($this->private.'/file-inventory.json', $inventory);

        return $inventory;
    }

    /** @param list<array{path:string,action:string,old_hash:?string,new_hash:?string}> $plan
     * @param  array<string,mixed>  $metadata
     */
    public function verifyBackup(array $plan, array $metadata): void
    {
        $metadataHash = hash('sha256', json_encode($metadata, JSON_THROW_ON_ERROR));
        $verified = $this->private.'/backup-verified.json';
        if (is_file($verified) && ! is_link($verified)) {
            $prior = json_decode((string) file_get_contents($verified), true, 32, JSON_THROW_ON_ERROR);
            if (($prior['metadata_hash'] ?? null) === $metadataHash) {
                return;
            }
        }
        foreach ($plan as $entry) {
            if ($entry['old_hash'] !== null && $this->hash($this->path($this->private.'/files', $entry['path'])) !== $entry['old_hash']) {
                throw new RuntimeException('La copia de un archivo no coincide con el plan.');
            }
        }
        if ($this->hash($this->private.'/environment.backup') !== ($metadata['environment_sha256'] ?? null)) {
            throw new RuntimeException('La copia de configuración está alterada.');
        }
        if (($metadata['database']['kind'] ?? '') === 'automatic' && $this->hash($this->private.'/database.ndjson') !== ($metadata['database']['sha256'] ?? null)) {
            throw new RuntimeException('La copia de datos está alterada.');
        }
        foreach (($metadata['passport'] ?? []) as $key => $hash) {
            if (! in_array($key, ['oauth-private.key', 'oauth-public.key'], true) || $this->hash($this->private.'/'.$key) !== $hash) {
                throw new RuntimeException('La copia de Passport está alterada.');
            }
        }
        self::writeJson($verified, ['metadata_hash' => $metadataHash, 'verified_at' => gmdate(DATE_ATOM)]);
    }

    /** @param list<array{path:string,action:string,old_hash:?string,new_hash:?string}> $plan */
    public function apply(array $plan, int $limit = 40): int
    {
        if ($limit < 1 || $limit > 500) {
            throw new RuntimeException('Tamaño de lote no válido.');
        }
        $journal = $this->journal();
        $processed = 0;
        foreach ($plan as $index => $entry) {
            if (($journal[(string) $index] ?? null) === 'complete') {
                continue;
            }
            $destination = $this->path($this->root, $entry['path']);
            $current = $this->hash($destination);
            if ($processed >= $limit) {
                break;
            }
            if ($current !== $entry['old_hash'] && $current !== $entry['new_hash']) {
                throw new RuntimeException('Conflicto posterior a la aprobación: '.$entry['path']);
            }
            if ($current === $entry['old_hash']) {
                $this->backup($entry['path'], $destination, $entry['old_hash']);
                $journal[(string) $index] = 'started';
                $this->record($index, 'started');
                if ($entry['new_hash'] === null) {
                    if (is_file($destination) && ! unlink($destination)) {
                        throw new RuntimeException('No se puede retirar '.$entry['path']);
                    }
                } else {
                    $source = $this->path($this->stage, $entry['path']);
                    if ($this->hash($source) !== $entry['new_hash']) {
                        throw new RuntimeException('Archivo preparado alterado: '.$entry['path']);
                    }
                    $this->replace($source, $destination, $entry['new_hash']);
                }
            } elseif (! isset($journal[(string) $index])) {
                // A matching new file without our durable intent may be a concurrent local modification.
                throw new RuntimeException('Archivo modificado fuera de esta ejecución: '.$entry['path']);
            }
            $journal[(string) $index] = 'complete';
            $this->record($index, 'complete');
            $processed++;
        }

        $completed = count(array_filter($journal, fn ($state): bool => $state === 'complete'));
        if ($completed === count($plan)) {
            foreach ($plan as $entry) {
                if ($this->hash($this->path($this->root, $entry['path'])) !== $entry['new_hash']) {
                    throw new RuntimeException('Un archivo completado cambió antes de las migraciones.');
                }
            }
        }

        return $completed;
    }

    /** @param list<array{path:string,action:string,old_hash:?string,new_hash:?string}> $plan */
    public function restore(array $plan): int
    {
        $restored = 0;
        $journal = $this->journal();
        foreach (array_reverse(array_keys($plan)) as $index) {
            if (! isset($journal[(string) $index])) {
                continue;
            }
            $entry = $plan[$index];
            $destination = $this->path($this->root, $entry['path']);
            $hash = $this->hash($destination);
            if ($hash !== $entry['new_hash'] && $hash !== $entry['old_hash']) {
                throw new RuntimeException('No se puede restaurar un archivo modificado: '.$entry['path']);
            }
            if ($hash !== $entry['old_hash']) {
                if ($entry['old_hash'] === null) {
                    if (! unlink($destination)) {
                        throw new RuntimeException('No se pudo retirar un archivo nuevo.');
                    }
                } else {
                    $source = $this->path($this->private.'/files', $entry['path']);
                    $this->replace($source, $destination, $entry['old_hash']);
                }
            }
            unset($journal[(string) $index]);
            $this->record($index, 'restored');
            $restored++;
        }

        return $restored;
    }

    /** @param array<mixed> $data */
    public static function writeJson(string $path, array $data): void
    {
        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Almacenamiento privado no escribible.');
        }
        if (is_link($directory) || is_link($path)) {
            throw new RuntimeException('No se admiten enlaces en el estado de actualización.');
        }
        $temporary = $path.'.'.bin2hex(random_bytes(8)).'.tmp';
        $handle = fopen($temporary, 'xb');
        if ($handle === false) {
            throw new RuntimeException('No se puede escribir el estado de actualización.');
        }
        chmod($temporary, 0600);
        try {
            $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            if (fwrite($handle, $json) !== strlen($json) || ! fflush($handle) || ! fsync($handle)) {
                throw new RuntimeException('No se pudo persistir el estado.');
            }
        } finally {
            fclose($handle);
        }
        if (! rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('No se pudo guardar el estado de actualización.');
        }
    }

    private function path(string $base, string $relative): string
    {
        if (! (new UpdatePathPolicy)->allows($relative) || preg_match('~[\\x00-\\x1f:]~', $relative)) {
            throw new RuntimeException('Ruta de actualización no permitida.');
        }
        $ancestor = rtrim($base, '/');
        while ($ancestor !== dirname($ancestor)) {
            if (is_link($ancestor)) {
                throw new RuntimeException('Directorio enlazado no permitido.');
            }
            $ancestor = dirname($ancestor);
        }
        $current = rtrim($base, '/');
        foreach (explode('/', $relative) as $segment) {
            if ($segment === '') {
                throw new RuntimeException('Ruta vacía de actualización.');
            }
            $current .= '/'.$segment;
            if (is_link($current)) {
                throw new RuntimeException('Enlace simbólico no permitido.');
            }
        }

        return $current;
    }

    private function hash(string $path): ?string
    {
        if (! file_exists($path)) {
            return null;
        }
        if (! is_file($path) || is_link($path)) {
            throw new RuntimeException('El destino no es un archivo regular.');
        }
        $hash = hash_file('sha256', $path);
        if ($hash === false) {
            throw new RuntimeException('No se puede comprobar el archivo.');
        }

        return $hash;
    }

    private function backup(string $relative, string $source, ?string $expected): void
    {
        if ($expected === null) {
            return;
        }
        $destination = $this->path($this->private.'/files', $relative);
        if (is_file($destination)) {
            if ($this->hash($destination) !== $expected) {
                throw new RuntimeException('La copia de archivos está alterada.');
            }

            return;
        }
        $this->replace($source, $destination, $expected, 0600);
    }

    private function replace(string $source, string $destination, string $expected, ?int $mode = null): void
    {
        if ($this->hash($source) !== $expected) {
            throw new RuntimeException('La copia no coincide con el hash verificado.');
        }
        $directory = dirname($destination);
        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException('Directorio de destino no escribible.');
        }
        $temporary = $destination.'.update-'.bin2hex(random_bytes(8));
        $input = fopen($source, 'rb');
        $output = fopen($temporary, 'xb');
        if ($input === false || $output === false) {
            throw new RuntimeException('No se puede preparar el reemplazo.');
        }
        try {
            if (stream_copy_to_stream($input, $output) === false || ! fflush($output) || ! fsync($output)) {
                throw new RuntimeException('No se pudo persistir el reemplazo.');
            }
        } finally {
            fclose($input);
            fclose($output);
        }
        chmod($temporary, $mode ?? (is_file($destination) ? (fileperms($destination) & 0777) : 0644));
        if ($this->hash($temporary) !== $expected || ! rename($temporary, $destination)) {
            @unlink($temporary);
            throw new RuntimeException('No se pudo aplicar el reemplazo.');
        }
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($destination, true);
        }
    }

    private function record(int $index, string $state): void
    {
        $path = $this->private.'/journal.ndjson';
        if (is_link($path)) {
            throw new RuntimeException('Registro de recuperación enlazado.');
        }
        $handle = fopen($path, 'ab');
        if ($handle === false) {
            throw new RuntimeException('Registro de recuperación no escribible.');
        }
        chmod($path, 0600);
        try {
            $line = json_encode(['index' => $index, 'state' => $state], JSON_THROW_ON_ERROR)."\n";
            if (fwrite($handle, $line) !== strlen($line) || ! fflush($handle) || ! fsync($handle)) {
                throw new RuntimeException('No se pudo persistir la intención de reemplazo.');
            }
        } finally {
            fclose($handle);
        }
    }

    /** @return array<int|string, string> */
    private function journal(): array
    {
        // Read the original snapshot format as well, so interrupted pre-upgrade
        // recovery snapshots retain their durable intent.
        $legacy = $this->private.'/journal.json';
        $data = is_file($legacy) ? json_decode((string) file_get_contents($legacy), true, 512, JSON_THROW_ON_ERROR) : [];
        if (! is_array($data)) {
            throw new RuntimeException('Registro de recuperación inválido.');
        }
        $path = $this->private.'/journal.ndjson';
        if (! is_file($path)) {
            return $data;
        }
        if (is_link($path)) {
            throw new RuntimeException('Registro de recuperación enlazado.');
        }
        $handle = fopen($path, 'r+b');
        if ($handle === false) {
            throw new RuntimeException('Registro de recuperación no legible.');
        }
        try {
            $lastGoodOffset = 0;
            while (($line = fgets($handle, 1024)) !== false) {
                if (! str_ends_with($line, "\n")) {
                    if (! feof($handle) || ! ftruncate($handle, max(0, $lastGoodOffset)) || ! fflush($handle) || ! fsync($handle)) {
                        throw new RuntimeException('Registro de recuperación incompleto.');
                    }
                    // A torn trailing record is discarded. A previous fsynced
                    // started record still permits recognizing a completed rename.
                    break;
                }
                $entry = json_decode($line, true, 8, JSON_THROW_ON_ERROR);
                if (! is_array($entry) || ! is_int($entry['index'] ?? null) || $entry['index'] < 0 || ! in_array($entry['state'] ?? null, ['started', 'complete', 'restored'], true)) {
                    throw new RuntimeException('Registro de recuperación inválido.');
                }
                if ($entry['state'] === 'restored') {
                    unset($data[$entry['index']]);
                } else {
                    $data[$entry['index']] = $entry['state'];
                }
                $lastGoodOffset = ftell($handle);
                if ($lastGoodOffset === false) {
                    throw new RuntimeException('No se pudo leer el progreso.');
                }
            }
        } finally {
            fclose($handle);
        }

        return $data;
    }
}
