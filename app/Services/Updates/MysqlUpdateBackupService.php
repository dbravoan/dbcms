<?php

declare(strict_types=1);

namespace App\Services\Updates;

use App\Contracts\Services\UpdateBackupServiceInterface;
use App\Core\Updates\FileUpdateEngine;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;
use Throwable;

final class MysqlUpdateBackupService implements UpdateBackupServiceInterface
{
    /** @param array<string, mixed> $evidence
     * @return array<string, mixed>
     */
    public function create(string $directory, array $evidence = []): array
    {
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('La carpeta privada de respaldo no es escribible.');
        }
        if (isset($evidence['verification'])) {
            $database = $this->external($directory, $evidence);
        } else {
            try {
                $database = $this->automatic($directory);
            } catch (Throwable $exception) {
                throw new RuntimeException('No se pudo verificar una restauración automática. Aporta el informe de una copia externa restaurada y comprobada. '.$exception->getMessage(), 0, $exception);
            }
        }
        $environment = (string) config('updates.root_path').'/.env';
        if (! is_file($environment) || is_link($environment)) {
            throw new RuntimeException('No se puede preservar la configuración privada.');
        }
        $target = $directory.'/environment.backup';
        if (! copy($environment, $target) || ! chmod($target, 0600) || hash_file('sha256', $environment) !== hash_file('sha256', $target)) {
            throw new RuntimeException('No se pudo verificar la copia de configuración.');
        }
        $secrets = [];
        foreach (['oauth-private.key', 'oauth-public.key'] as $key) {
            $source = (string) config('updates.root_path').'/storage/'.$key;
            if (is_file($source) && ! is_link($source)) {
                $targetKey = $directory.'/'.$key;
                if (! copy($source, $targetKey) || ! chmod($targetKey, 0600) || hash_file('sha256', $source) !== hash_file('sha256', $targetKey)) {
                    throw new RuntimeException('No se pudo preservar una clave de Passport.');
                }
                $secrets[$key] = hash_file('sha256', $targetKey);
            }
        }
        $result = ['passport' => $secrets, 'file_inventory_sha256' => is_file($directory.'/file-inventory.json') ? hash_file('sha256', $directory.'/file-inventory.json') : null, 'database' => $database, 'environment_sha256' => hash_file('sha256', $target), 'verified_at' => gmdate(DATE_ATOM)];
        FileUpdateEngine::writeJson($directory.'/backup.json', $result);
        $result['hash'] = hash('sha256', json_encode($result, JSON_THROW_ON_ERROR));

        return $result;
    }

    /** @return array<string, mixed> */
    private function automatic(string $directory): array
    {
        $connection = DB::connection();
        if ($connection->getDriverName() !== 'mysql') {
            throw new RuntimeException('La copia integrada requiere MySQL/MariaDB.');
        }
        $pdo = $connection->getPdo();
        $database = (string) $connection->getDatabaseName();
        $statement = $pdo->prepare('SELECT TABLE_NAME, TABLE_TYPE, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME');
        $statement->execute([$database]);
        $tables = $statement->fetchAll(PDO::FETCH_ASSOC);
        if ($tables === []) {
            throw new RuntimeException('No se puede verificar una base vacía.');
        }
        foreach ($tables as $table) {
            if ($table['TABLE_TYPE'] !== 'BASE TABLE' || $table['ENGINE'] !== 'InnoDB') {
                throw new RuntimeException('Vistas o tablas sin InnoDB requieren respaldo externo.');
            }
        }
        foreach (['TRIGGERS' => 'TRIGGER_SCHEMA', 'ROUTINES' => 'ROUTINE_SCHEMA', 'EVENTS' => 'EVENT_SCHEMA'] as $table => $column) {
            $check = $pdo->prepare('SELECT COUNT(*) FROM information_schema.'.$table.' WHERE '.$column.' = ?');
            $check->execute([$database]);
            if ((int) $check->fetchColumn() > 0) {
                throw new RuntimeException('Triggers, rutinas o eventos requieren respaldo externo.');
            }
        }
        $generated = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND GENERATION_EXPRESSION <> ''");
        $generated->execute([$database]);
        if ((int) $generated->fetchColumn() > 0) {
            throw new RuntimeException('Columnas generadas requieren respaldo externo.');
        }
        $path = $directory.'/database.ndjson';
        $file = fopen($path, 'xb');
        if ($file === false) {
            throw new RuntimeException('No se pudo crear la copia de base de datos.');
        }
        chmod($path, 0600);
        $digests = [];
        try {
            $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
            foreach ($tables as $table) {
                $name = (string) $table['TABLE_NAME'];
                $ddl = $pdo->query('SHOW CREATE TABLE '.$this->identifier($name));
                if ($ddl === false) {
                    throw new RuntimeException('No se pudo leer el esquema.');
                }
                $schema = $ddl->fetch(PDO::FETCH_NUM);
                $columnsStatement = $pdo->query('SHOW COLUMNS FROM '.$this->identifier($name));
                if ($columnsStatement === false || ! is_array($schema)) {
                    throw new RuntimeException('No se pudo leer las columnas.');
                }
                $columns = array_column($columnsStatement->fetchAll(PDO::FETCH_ASSOC), 'Field');
                $this->line($file, ['table' => $name, 'schema' => $schema[1], 'columns' => $columns]);
                $hash = hash_init('sha256');
                $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
                $rows = $pdo->query($this->select($name, $columns));
                if ($rows === false) {
                    throw new RuntimeException('No se pudo leer los datos.');
                }
                while (($row = $rows->fetch(PDO::FETCH_NUM)) !== false) {
                    $values = array_map(fn ($value): ?string => $value === null ? null : base64_encode((string) $value), $row);
                    $encoded = json_encode($values, JSON_THROW_ON_ERROR);
                    hash_update($hash, $encoded."\n");
                    $this->line($file, ['row' => $values]);
                }
                $rows->closeCursor();
                $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
                $digests[$name] = hash_final($hash);
            }
            $pdo->commit();
            fflush($file);
            fsync($file);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        } finally {
            $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
            fclose($file);
        }
        $this->verifyRestore($pdo, $path, $digests);

        return ['kind' => 'automatic', 'file' => 'database.ndjson', 'sha256' => hash_file('sha256', $path), 'restoration_verified' => true, 'tables' => count($tables)];
    }

    /** @param array<string, string> $expected */
    private function verifyRestore(PDO $pdo, string $path, array $expected): void
    {
        $scratch = 'dbcms_restore_'.bin2hex(random_bytes(8));
        $original = (string) DB::connection()->getDatabaseName();
        $created = false;
        $file = null;
        try {
            $pdo->exec('CREATE DATABASE '.$this->identifier($scratch));
            $created = true;
            $pdo->exec('USE '.$this->identifier($scratch));
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            $file = fopen($path, 'rb');
            if ($file === false) {
                throw new RuntimeException('No se pudo verificar la copia.');
            }
            $insert = null;
            $schemas = [];
            while (($line = fgets($file)) !== false) {
                $entry = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                if (isset($entry['schema'])) {
                    $pdo->exec($entry['schema']);
                    $columns = $entry['columns'];
                    $schemas[$entry['table']] = $columns;
                    $insert = $pdo->prepare('INSERT INTO '.$this->identifier($entry['table']).' ('.implode(',', array_map($this->identifier(...), $columns)).') VALUES ('.implode(',', array_fill(0, count($columns), '?')).')');
                    if ($insert === false) {
                        throw new RuntimeException('No se pudo preparar la restauración.');
                    }
                } elseif (isset($entry['row']) && $insert !== null) {
                    $insert->execute(array_map(function ($value): ?string {
                        if ($value === null) {
                            return null;
                        }
                        $decoded = base64_decode($value, true);
                        if ($decoded === false) {
                            throw new RuntimeException('Datos de respaldo no válidos.');
                        }

                        return $decoded;
                    }, $entry['row']));
                }
            }
            foreach ($schemas as $name => $columns) {
                $query = $pdo->query($this->select($name, $columns));
                if ($query === false) {
                    throw new RuntimeException('No se pudo comprobar la restauración.');
                }
                $hash = hash_init('sha256');
                while (($row = $query->fetch(PDO::FETCH_NUM)) !== false) {
                    hash_update($hash, json_encode(array_map(fn ($value): ?string => $value === null ? null : base64_encode((string) $value), $row), JSON_THROW_ON_ERROR)."\n");
                }
                if (! hash_equals($expected[$name], hash_final($hash))) {
                    throw new RuntimeException('La restauración no coincide con la copia.');
                }
            }
        } finally {
            if (is_resource($file)) {
                fclose($file);
            }
            $pdo->exec('USE '.$this->identifier($original));
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            if ($created) {
                $pdo->exec('DROP DATABASE '.$this->identifier($scratch));
            }
        }
    }

    /** @param array<string, mixed> $evidence
     * @return array<string, mixed>
     */
    private function external(string $directory, array $evidence): array
    {
        foreach (['provider', 'reference', 'created_at', 'restored_at'] as $field) {
            if (! is_string($evidence[$field] ?? null) || trim($evidence[$field]) === '') {
                throw new RuntimeException('El respaldo externo requiere proveedor, referencia y fechas de copia y restauración.');
            }
        }
        $created = new DateTimeImmutable($evidence['created_at']);
        $restored = new DateTimeImmutable($evidence['restored_at']);
        if ($created > $restored || $restored->getTimestamp() > time() || $created->getTimestamp() < time() - 86400) {
            throw new RuntimeException('La copia externa debe ser reciente y su restauración posterior verificable.');
        }
        $verification = $evidence['verification'];
        $artifact = realpath((string) ($verification['artifact_path'] ?? ''));
        $runRoot = realpath(dirname($directory));
        $evidenceRoot = realpath(dirname($directory).'/evidence');
        if ($artifact === false || $evidenceRoot === false || $runRoot === false || ! str_starts_with($evidenceRoot, $runRoot.'/') || is_link(dirname($directory).'/evidence') || ! str_starts_with($artifact, $evidenceRoot.'/') || ! is_file($artifact) || is_link((string) $verification['artifact_path']) || filesize($artifact) > 1048576 || ! hash_equals((string) ($verification['sha256'] ?? ''), (string) hash_file('sha256', $artifact))) {
            throw new RuntimeException('El informe privado de restauración no es válido.');
        }

        return ['kind' => 'external', 'provider' => $evidence['provider'], 'reference' => $evidence['reference'], 'created_at' => $evidence['created_at'], 'restored_at' => $evidence['restored_at'], 'report_sha256' => $verification['sha256'], 'verification' => 'human_attested_restore_report'];
    }

    private function identifier(string $identifier): string
    {
        return '`'.str_replace('`', '``', $identifier).'`';
    }

    /** @param list<string> $columns */
    private function select(string $table, array $columns): string
    {
        return 'SELECT * FROM '.$this->identifier($table).' ORDER BY '.implode(',', array_map($this->identifier(...), $columns));
    }

    /** @param resource $file
     * @param  array<string, mixed>  $value
     */
    private function line($file, array $value): void
    {
        $line = json_encode($value, JSON_THROW_ON_ERROR)."\n";
        if (fwrite($file, $line) !== strlen($line)) {
            throw new RuntimeException('La copia de base de datos está incompleta.');
        }
    }
}
