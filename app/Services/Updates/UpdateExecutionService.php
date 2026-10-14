<?php

declare(strict_types=1);

namespace App\Services\Updates;

use App\Contracts\Services\UpdateBackupServiceInterface;
use App\Contracts\Services\UpdateExecutionServiceInterface;
use App\Contracts\Services\UpdateManagedReconcilerServiceInterface;
use App\Contracts\Services\UpdateMigrationServiceInterface;
use App\Contracts\Services\UpdatePreflightServiceInterface;
use App\Core\Updates\FileUpdateEngine;
use App\Core\Updates\SignedManifestVerifier;
use App\Core\Updates\UpdateBarrier;
use App\Core\Updates\UpdatePathPolicy;
use App\Core\Updates\UpdateRunStore;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class UpdateExecutionService implements UpdateExecutionServiceInterface
{
    public function __construct(private readonly UpdateRunStore $store, private readonly UpdatePreflightServiceInterface $preflight, private readonly UpdateBackupServiceInterface $backups, private readonly UpdateMigrationServiceInterface $migrations, private readonly UpdateManagedReconcilerServiceInterface $managedReconciler) {}

    public function status(string $runId, int $actorId): array
    {
        $run = $this->store->read($runId);
        $this->authorize($run, $actorId);
        $run = $this->managedReconciler->reconcile($runId);
        $run['preflight'] = $this->preflight->inspect($run, $run['hosting'] ?? []);

        return $this->publicRun($run);
    }

    public function approve(string $runId, int $actorId, string $planHash, array $backupEvidence = []): array
    {
        $activation = fopen($this->store->root().'/activation.lock', 'c+');
        if ($activation === false || ! flock($activation, LOCK_EX | LOCK_NB)) {
            throw new DomainException('Otra actualización se está activando.');
        }
        try {
            return $this->store->withLock($runId, function (array $run) use ($runId, $actorId, $planHash, $backupEvidence): array {
                $user = $this->authorize($run, $actorId);
                $this->recentPassword();
                if (is_file($this->store->root().'/active.json')) {
                    throw new DomainException('Ya hay una actualización activa.');
                }
                if ($run['status'] !== 'prepared' || ($run['conflicts'] ?? []) !== [] || ! hash_equals($run['plan_hash'], $planHash)) {
                    throw new DomainException('La actualización no coincide con el plan preparado o tiene conflictos.');
                }
                $hosting = $backupEvidence['hosting'] ?? [];
                $preflight = $this->preflight->inspect($run, $hosting);
                if (! $preflight['ready']) {
                    throw new DomainException(implode(' ', $preflight['issues']));
                }
                if ($this->store->root() !== storage_path('app/private/core-updates')) {
                    throw new DomainException('El motor web exige el almacenamiento privado de actualización estándar.');
                }
                $this->verifyPlan($run);
                $directory = $this->store->directory($runId);
                $this->snapshot($directory);
                $token = bin2hex(random_bytes(32));
                $csrf = bin2hex(random_bytes(32));
                $expires = time() + 900;
                $request = request();
                $cli = app()->runningInConsole() && request()->attributes->get('_cms_update_cli_approval') === true && request()->route() === null;
                $profile = $cli ? [] : $this->recoveryProfile($token, $actorId);
                $run['recovery'] = ['profile' => $profile, 'cookie_expires_at' => time() + 86400, 'token_hash' => hash('sha256', $token), 'csrf_hash' => hash('sha256', $csrf), 'expires_at' => $expires, 'session_hash' => hash('sha256', $request->hasSession() ? $request->session()->getId() : ''), 'user_hash' => hash('sha256', $user->getAuthPassword())];
                $run['approval'] = ['transport' => $cli ? 'cli' : 'web', 'actor_id' => $actorId, 'plan_hash' => $planHash, 'manifest_hash' => $run['manifest_hash'], 'at' => gmdate(DATE_ATOM)];
                $run['hosting'] = $hosting;
                $run['backup_evidence'] = $backupEvidence;
                $run['status'] = 'draining';
                unset($run['backup_required'], $run['error']);
                $run = $this->store->save($runId, $run);
                $barrier = new UpdateBarrier($this->store->root());
                $barrier->close(['run_id' => $runId, 'created_at' => gmdate(DATE_ATOM)]);
                if (class_exists('UpdateRuntime', false)) {
                    \UpdateRuntime::release();
                }
                $urlPath = rtrim((string) parse_url((string) config('app.url'), PHP_URL_PATH), '/').'/update.php';
                if (! $cli) {
                    setcookie('dbcms_update_recovery', $token, ['expires' => time() + 86400, 'path' => $urlPath, 'secure' => $request->isSecure(), 'httponly' => true, 'samesite' => 'Strict']);
                }
                $result = $this->publicRun($run);
                if (! $cli) {
                    $result['recovery'] = ['url' => $urlPath, 'csrf' => $csrf, 'expires_at' => $expires];
                }

                return $result;
            });
        } finally {
            flock($activation, LOCK_UN);
            fclose($activation);
        }
    }

    public function advance(string $runId, int $actorId): array
    {
        return $this->store->withLock($runId, function (array $run) use ($runId, $actorId): array {
            $user = $this->authorize($run, $actorId);
            if (! isset($run['approval']) || $run['approval']['actor_id'] !== $actorId || ! hash_equals($run['plan_hash'], $run['approval']['plan_hash']) || ! hash_equals($run['manifest_hash'], $run['approval']['manifest_hash'])) {
                throw new DomainException('Falta una aprobación vigente para este plan.');
            }
            if (! hash_equals((string) ($run['recovery']['user_hash'] ?? ''), hash('sha256', $user->getAuthPassword()))) {
                throw new AuthorizationException('Las credenciales han cambiado; se requiere recuperación autenticada.');
            }
            if (! in_array($run['status'], ['completed', 'rolled_back'], true)) {
                $active = json_decode((string) @file_get_contents($this->store->root().'/active.json'), true);
                if (! is_array($active) || ($active['run_id'] ?? '') !== $runId) {
                    throw new DomainException('Esta ejecución no posee el cierre de actualización.');
                }
            }
            $directory = $this->store->directory($runId);
            $trusted = json_decode((string) file_get_contents($directory.'/runtime/trusted-keys.json'), true, 32, JSON_THROW_ON_ERROR);
            $verified = (new SignedManifestVerifier)->verify($run['manifest_raw'], $run['signature_raw'], $trusted);
            if ($verified->hash !== $run['manifest_hash'] || $verified->data !== $run['manifest']) {
                throw new DomainException('El manifiesto ya no coincide con la aprobación.');
            }
            $barrier = new UpdateBarrier($this->store->root());
            if (class_exists('UpdateRuntime', false)) {
                \UpdateRuntime::release();
            }
            try {
                switch ($run['status']) {
                    case 'draining':
                        if (! $barrier->drain()) {
                            $run['message'] = 'Esperando a que terminen las operaciones activas.';
                            break;
                        }
                        $this->verifyPlan($run);
                        $run['status'] = 'backing_up';
                        $this->store->save($runId, $run);
                        (new FileUpdateEngine((string) config('updates.root_path'), $run['stage_path'], $directory.'/backup'))->backupPlan($run['plan']);
                        $run['backup'] = $this->backups->create($directory.'/backup', $run['backup_evidence']);
                        $run['approval']['backup_hash'] = $run['backup']['hash'];
                        $run['status'] = 'files';
                        break;
                    case 'files':
                        $engine = new FileUpdateEngine((string) config('updates.root_path'), $run['stage_path'], $directory.'/backup');
                        $backup = json_decode((string) file_get_contents($directory.'/backup/backup.json'), true, 128, JSON_THROW_ON_ERROR);
                        if (! hash_equals((string) $run['approval']['backup_hash'], hash('sha256', json_encode($backup, JSON_THROW_ON_ERROR)))) {
                            throw new RuntimeException('La copia vinculada a la aprobación está alterada.');
                        }
                        $engine->verifyBackup($run['plan'], $backup);
                        $run['completed_files'] = $engine->apply($run['plan']);
                        if ($run['completed_files'] === count($run['plan'])) {
                            if (class_exists('UpdateRuntime', false)) {
                                \UpdateRuntime::clearBootstrapCaches((string) config('updates.root_path'));
                            }
                            $run['status'] = 'files_applied';
                            $run['files_applied_request'] = defined('LARAVEL_START') ? LARAVEL_START : ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));
                        }
                        FileUpdateEngine::writeJson($directory.'/progress.json', ['completed_files' => $run['completed_files']]);
                        if ($run['status'] === 'files') {
                            return $this->publicRun($run);
                        }
                        break;
                    case 'files_applied':
                        $migrationPaths = $this->migrations->pending($run['manifest']);
                        $run['migration_paths'] = $migrationPaths;
                        $run['migration_started'] = true;
                        $run['status'] = 'migrations_running';
                        $this->store->save($runId, $run);
                        $this->migrations->apply($migrationPaths);
                        $run['status'] = 'cache_pending';
                        break;
                    case 'cache_pending':
                        if (Artisan::call('optimize:clear') !== 0 || Artisan::call('queue:restart') !== 0) {
                            throw new RuntimeException('No se pudo invalidar la caché o avisar a los workers.');
                        }
                        $run['status'] = 'health_pending';
                        break;
                    case 'health_pending':
                        // This request was booted after replacement and migration. Health is
                        // handled through the real HTTP kernel in this NEW process, privately.
                        $run['health'] = $this->freshHealth($run);
                        $run['status'] = 'health_verified';
                        break;
                    case 'health_verified':
                        if (($run['health']['source_sha'] ?? '') !== $run['target_identity']['source_sha']) {
                            throw new RuntimeException('La comprobación corresponde a otro código.');
                        }
                        $identity = $run['target_identity'];
                        $this->store->atomic($this->store->root().'/identity.json', json_encode($identity, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
                        $run['status'] = 'completed';
                        $run['completed_at'] = gmdate(DATE_ATOM);
                        $run = $this->store->save($runId, $run);
                        $barrier->open();
                        break;
                    case 'code_restored':
                        $run['health'] = $this->freshHealth($run, true);
                        $run['status'] = 'rolled_back';
                        $run = $this->store->save($runId, $run);
                        $barrier->open();
                        break;
                    case 'rolled_back':
                    case 'completed':
                        break;
                    case 'backing_up':
                        $run['status'] = 'prepared';
                        $run['backup_required'] = true;
                        $run['error'] = 'La copia anterior no terminó de verificarse. Aporta un respaldo externo comprobado antes de volver a aprobar.';
                        unset($run['approval'], $run['recovery']);
                        $run = $this->store->save($runId, $run);
                        $barrier->open();
                        break;
                    case 'migrations_running':
                        $run['status'] = 'recovery_required';
                        $run['error'] = 'La fase anterior terminó sin confirmación. Revisa su resultado antes de continuar; no se repetirá automáticamente.';
                        break;
                    default:
                        throw new DomainException('Este estado requiere revisar el procedimiento de recuperación.');
                }
            } catch (Throwable $exception) {
                $failedPhase = $run['status'];
                if (in_array($failedPhase, ['draining', 'backing_up'], true)) {
                    $run['status'] = 'prepared';
                    $run['backup_required'] = true;
                    $run['error'] = 'No se ha modificado el código. Aporta una copia externa con informe de restauración y vuelve a aprobar la actualización.';
                    $run['private_error'] = $exception->getMessage();
                    unset($run['approval'], $run['recovery']);
                    $run = $this->store->save($runId, $run);
                    $barrier->open();

                    return $this->publicRun($run);
                }
                if (in_array($failedPhase, ['files', 'files_applied'], true) && ! ($run['migration_started'] ?? false) && ($run['manifest']['rollback_compatible'] ?? false) === true) {
                    try {
                        (new FileUpdateEngine((string) config('updates.root_path'), $run['stage_path'], $directory.'/backup'))->restore($run['plan']);
                        if (class_exists('UpdateRuntime', false)) {
                            \UpdateRuntime::clearBootstrapCaches((string) config('updates.root_path'));
                        }
                        $run['status'] = 'code_restored';
                        $run['files_applied_request'] = defined('LARAVEL_START') ? LARAVEL_START : ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));
                        $run['error'] = 'Se restauró el código anterior. El sitio seguirá cerrado hasta comprobarlo desde una petición nueva.';

                        return $this->publicRun($this->store->save($runId, $run));
                    } catch (Throwable $restoreFailure) {
                        $run['restore_error'] = $restoreFailure->getMessage();
                    }
                }
                $run['status'] = 'recovery_required';
                $run['error'] = 'La actualización requiere revisión. No se han revertido los datos.';
                $run['private_error'] = $exception->getMessage();
                $this->store->save($runId, $run);
                throw new DomainException($run['error'], 0, $exception);
            }

            return $this->publicRun($this->store->save($runId, $run));
        });
    }

    public function cancel(string $runId, int $actorId): array
    {
        return $this->store->withLock($runId, function (array $run) use ($runId, $actorId): array {
            $this->authorize($run, $actorId);
            if (! in_array($run['status'], ['prepared', 'conflict', 'managed', 'draining'], true)) {
                throw new DomainException('La actualización ya ha comenzado y requiere recuperación controlada.');
            }
            $run['status'] = 'cancelled';
            $run['recovery'] = [];
            if (is_file($this->store->root().'/active.json')) {
                $active = json_decode((string) file_get_contents($this->store->root().'/active.json'), true);
                if (($active['run_id'] ?? '') === $runId) {
                    (new UpdateBarrier($this->store->root()))->open();
                }
            }

            return $this->publicRun($this->store->save($runId, $run));
        });
    }

    /** @param array<string,mixed> $run */
    private function authorize(array $run, int $actorId): User
    {
        $user = User::find($actorId);
        if ($user === null || (int) $run['actor_id'] !== $actorId || ! $user->hasPermission('manage:updates') || ! $user->hasPermission('access:admin') || (bool) $user->getAttribute('is_service_account')) {
            throw new AuthorizationException('No puedes acceder a esta actualización.');
        }
        if (! app()->runningInConsole() && Auth::id() !== $actorId) {
            throw new AuthorizationException('La sesión no corresponde al administrador de esta actualización.');
        }

        return $user;
    }

    /** @return array{ciphertext:string,iv:string,tag:string} */
    private function recoveryProfile(string $token, int $actorId): array
    {
        $connection = DB::connection();
        if ($connection->getDriverName() !== 'mysql') {
            throw new DomainException('La recuperación web requiere MySQL/MariaDB.');
        }
        $configuration = $connection->getConfig();
        $socket = (string) ($configuration['unix_socket'] ?? '');
        $dsn = $socket !== '' ? 'mysql:unix_socket='.$socket : 'mysql:host='.$configuration['host'].';port='.($configuration['port'] ?? 3306);
        $dsn .= ';dbname='.$configuration['database'].';charset='.($configuration['charset'] ?? 'utf8mb4');
        $request = request();
        $profile = ['options' => $configuration['options'] ?? [], 'dsn' => $dsn, 'username' => $configuration['username'], 'password' => $configuration['password'], 'prefix' => $configuration['prefix'] ?? '', 'actor_id' => $actorId,
            'session_driver' => config('session.driver'), 'session_id' => $request->hasSession() ? $request->session()->getId() : '', 'session_table' => config('session.table', 'sessions'), 'session_files' => config('session.files'), 'session_lifetime' => (int) config('session.lifetime', 120) * 60];
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt(json_encode($profile, JSON_THROW_ON_ERROR), 'aes-256-gcm', hash('sha256', $token, true), OPENSSL_RAW_DATA, $iv, $tag);
        if ($ciphertext === false) {
            throw new RuntimeException('No se pudo proteger la autorización de recuperación.');
        }

        return ['ciphertext' => base64_encode($ciphertext), 'iv' => base64_encode($iv), 'tag' => base64_encode($tag)];
    }

    private function recentPassword(): void
    {
        $request = request();
        if (app()->runningInConsole() && $request->route() === null && $request->attributes->get('_cms_update_cli_approval') === true) {
            return;
        }
        if (! $request->hasSession() || (int) $request->session()->get('auth.password_confirmed_at', 0) > time() || time() - (int) $request->session()->get('auth.password_confirmed_at', 0) > 900) {
            throw new AuthorizationException('Confirma de nuevo tu contraseña antes de aplicar la actualización.');
        }
    }

    /** @param array<string,mixed> $run */
    private function verifyPlan(array $run): void
    {
        $verified = (new SignedManifestVerifier)->verify($run['manifest_raw'], $run['signature_raw'], config('updates.trusted_keys', []));
        if ($verified->hash !== $run['manifest_hash'] || $verified->data !== $run['manifest']) {
            throw new DomainException('El manifiesto no corresponde a una publicación firmada válida.');
        }
        $comparison = ['plan' => $run['plan'], 'conflicts' => $run['conflicts'], 'preserved' => $run['preserved']];
        if (! hash_equals($run['manifest_hash'], hash('sha256', $run['manifest_raw'])) || json_decode($run['manifest_raw'], true, 128, JSON_THROW_ON_ERROR) !== $run['manifest'] || ! hash_equals($run['plan_hash'], hash('sha256', json_encode($comparison, JSON_THROW_ON_ERROR)))) {
            throw new DomainException('El plan de archivos ha cambiado.');
        }
        foreach ($run['plan'] as $entry) {
            (new UpdatePathPolicy)->requireAllowed($entry['path']);
            if (($run['manifest']['distributions'][$run['distribution']]['files'][$entry['path']] ?? null) !== $entry['new_hash']) {
                throw new DomainException('El archivo objetivo no corresponde al manifiesto firmado.');
            }
            $target = (string) config('updates.root_path').'/'.$entry['path'];
            $current = is_file($target) ? hash_file('sha256', $target) : null;
            if ($current !== $entry['old_hash']) {
                throw new DomainException('Un archivo cambió después de preparar: '.$entry['path']);
            }
        }
    }

    private function snapshot(string $directory): void
    {
        $paths = ['UpdateRuntime.php' => base_path('bootstrap/UpdateRuntime.php'), 'FileUpdateEngine.php' => base_path('app/Core/Updates/FileUpdateEngine.php'), 'UpdateBarrier.php' => base_path('app/Core/Updates/UpdateBarrier.php'), 'UpdatePathPolicy.php' => base_path('app/Core/Updates/UpdatePathPolicy.php'), 'SignedManifestVerifier.php' => base_path('app/Core/Updates/SignedManifestVerifier.php'), 'ReleaseManifest.php' => base_path('app/Core/Updates/ReleaseManifest.php')];
        if (! is_dir($directory.'/runtime') && ! mkdir($directory.'/runtime', 0700, true) && ! is_dir($directory.'/runtime')) {
            throw new RuntimeException('No se puede preparar el motor de recuperación.');
        }
        FileUpdateEngine::writeJson($directory.'/runtime/trusted-keys.json', config('updates.trusted_keys', []));
        foreach ($paths as $filename => $source) {
            $destination = $directory.'/runtime/'.$filename;
            if (! copy($source, $destination) || ! chmod($destination, 0600) || hash_file('sha256', $source) !== hash_file('sha256', $destination)) {
                throw new RuntimeException('No se pudo verificar el motor de recuperación.');
            }
        }
    }

    /** @param array<string,mixed> $run
     * @return array<string,mixed>
     */
    private function freshHealth(array $run, bool $restored = false): array
    {
        if ((float) ($run['files_applied_request'] ?? PHP_FLOAT_MAX) >= (float) (defined('LARAVEL_START') ? LARAVEL_START : ($_SERVER['REQUEST_TIME_FLOAT'] ?? 0))) {
            throw new RuntimeException('La comprobación debe realizarse desde una petición nueva.');
        }
        DB::connection()->getPdo();
        $root = (string) config('updates.root_path');
        $distribution = $run['manifest']['distributions'][$run['distribution']];
        foreach ($run['plan'] as $entry) {
            $actual = is_file($root.'/'.$entry['path']) ? hash_file('sha256', $root.'/'.$entry['path']) : null;
            if ($actual !== $entry[$restored ? 'old_hash' : 'new_hash']) {
                throw new RuntimeException('Un archivo aplicado no coincide con el objetivo verificado.');
            }
        }
        foreach (['VERSION', 'public/index.php', 'vendor/autoload.php', 'bootstrap/app.php'] as $path) {
            if (! $restored && isset($distribution['files'][$path]) && (! is_file($root.'/'.$path) || hash_file('sha256', $root.'/'.$path) !== $distribution['files'][$path])) {
                throw new RuntimeException('El código servido no coincide con la distribución.');
            }
        }
        $expectedIdentity = $restored ? $this->store->identity() : $run['target_identity'];
        if ($expectedIdentity === null || trim((string) file_get_contents($root.'/VERSION')) !== $expectedIdentity['version'] || (! $restored && $this->migrations->pending($run['manifest']) !== [])) {
            throw new RuntimeException('La versión o las migraciones todavía no son correctas.');
        }
        $kernel = app(Kernel::class);
        $checks = [];
        foreach (['/up', '/'.trim((string) config('cms.admin_path', 'control'), '/').'/login', '/'] as $path) {
            $request = Request::create(rtrim((string) config('app.url'), '/').$path, 'GET');
            $response = $kernel->handle($request);
            $kernel->terminate($request, $response);
            if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 400) {
                throw new RuntimeException('No pasó la comprobación privada de '.$path);
            }
            $checks[$path] = $response->getStatusCode();
        }

        return ['verified_at' => gmdate(DATE_ATOM), 'source_sha' => $expectedIdentity['source_sha'], 'checks' => $checks];
    }

    /** @param array<string,mixed> $run
     * @return array<string,mixed>
     */
    private function publicRun(array $run): array
    {
        return array_intersect_key($run, array_flip(['id', 'actor_id', 'status', 'mode', 'version', 'plan_hash', 'conflicts', 'preserved', 'created_at', 'updated_at', 'completed_at', 'completed_files', 'backup_required', 'preflight', 'health', 'message', 'error']));
    }
}
