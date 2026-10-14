<?php

declare(strict_types=1);

namespace App\Services\Updates;

use App\Contracts\Services\UpdateDeploymentServiceInterface;
use App\Contracts\Services\UpdatePreflightServiceInterface;

final class UpdatePreflightService implements UpdatePreflightServiceInterface
{
    public function __construct(private readonly UpdateDeploymentServiceInterface $deployment) {}

    /** @param array<string, mixed> $run
     * @param  array<string, mixed>  $hosting
     * @return array{mode:string,ready:bool,issues:list<string>}
     */
    public function inspect(array $run, array $hosting = []): array
    {
        $root = (string) config('updates.root_path');
        $issues = [];
        $managed = ($run['mode'] ?? '') === 'managed' || $this->deployment->isManaged($root);
        if ($managed) {
            return ['mode' => 'managed', 'ready' => false, 'issues' => ['Este proyecto utiliza un despliegue gestionado. Descarga la propuesta y aplícala mediante su CI.']];
        }
        if (! config('updates.web_apply_enabled', true)) {
            $issues[] = 'La actualización web está deshabilitada por el alojamiento.';
        }
        if (($hosting['single_host'] ?? false) !== true || ($hosting['no_unmanaged_workers'] ?? false) !== true) {
            $issues[] = 'Confirma que esta instalación usa un solo servidor y no tiene procesos externos sin controlar.';
        }
        if (! in_array(config('queue.default'), ['sync', 'database'], true)) {
            $issues[] = 'Los workers deben detenerse mediante el despliegue gestionado antes de actualizar.';
        }
        if (filter_var(ini_get('opcache.enable'), FILTER_VALIDATE_BOOLEAN) && (! filter_var(ini_get('opcache.validate_timestamps'), FILTER_VALIDATE_BOOLEAN) || (int) ini_get('opcache.revalidate_freq') !== 0)) {
            $issues[] = 'El alojamiento debe permitir refrescar OPcache en todas las peticiones.';
        }
        if (! in_array(config('session.driver'), ['file', 'database'], true)) {
            $issues[] = 'La recuperación web requiere sesiones en archivos o base de datos.';
        }
        if (config('session.driver') === 'database' && config('session.connection') !== null && config('session.connection') !== config('database.default')) {
            $issues[] = 'Las sesiones en otra conexión requieren despliegue gestionado.';
        }
        $database = config('database.connections.'.config('database.default'), []);
        $allowedOptions = [\PDO::ATTR_TIMEOUT, \PDO::ATTR_EMULATE_PREPARES, \PDO::ATTR_STRINGIFY_FETCHES, \PDO::ATTR_ERRMODE];
        foreach (['MYSQL_ATTR_SSL_KEY', 'MYSQL_ATTR_SSL_CERT', 'MYSQL_ATTR_SSL_CA', 'MYSQL_ATTR_SSL_CAPATH', 'MYSQL_ATTR_SSL_CIPHER', 'MYSQL_ATTR_SSL_VERIFY_SERVER_CERT'] as $constant) {
            if (defined('PDO::'.$constant)) {
                $allowedOptions[] = constant('PDO::'.$constant);
            }
        }
        if (is_array($database['host'] ?? null) || isset($database['read']) || isset($database['write']) || array_diff(array_keys($database['options'] ?? []), $allowedOptions) !== []) {
            $issues[] = 'La conexión avanzada de base de datos requiere actualización mediante despliegue gestionado.';
        }
        if (! function_exists('flock') || ! function_exists('fsync')) {
            $issues[] = 'El alojamiento no ofrece bloqueo y escritura durable.';
        }
        if (! is_writable($root) || ! is_writable((string) config('updates.storage_path'))) {
            $issues[] = 'Marca como escribibles la carpeta del CMS y storage desde el panel del alojamiento.';
        }
        $needed = 0;
        foreach (($run['plan'] ?? []) as $entry) {
            $path = $root.'/'.$entry['path'];
            $parent = dirname($path);
            while (! is_dir($parent) && $parent !== dirname($parent)) {
                $parent = dirname($parent);
            }
            if (! is_writable($parent) || (is_file($path) && ! is_readable($path))) {
                $issues[] = 'Permisos insuficientes en '.$entry['path'];
            }
            $needed += is_file($path) ? (int) filesize($path) : 0;
        }
        $free = disk_free_space($root);
        if ($free === false || $free < max(67108864, $needed * 3)) {
            $issues[] = 'No hay espacio libre suficiente para la copia y el reemplazo seguro.';
        }

        return ['mode' => 'runtime', 'ready' => $issues === [], 'issues' => array_values(array_unique($issues))];
    }
}
