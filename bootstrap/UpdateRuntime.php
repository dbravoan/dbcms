<?php

declare(strict_types=1);
use App\Contracts\Services\UpdateExecutionServiceInterface;
use App\Core\Updates\FileUpdateEngine;
use App\Core\Updates\SignedManifestVerifier;
use App\Core\Updates\UpdateBarrier;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;

final class UpdateRuntimeAccessDenied extends RuntimeException {}

/** This file is also copied to private storage before replacing framework files. */
final class UpdateRuntime
{
    /** @var resource|null */
    private static $lease = null;

    public static function admit(string $root): bool
    {
        $directory = $root.'/storage/app/private/core-updates';
        require_once $root.'/app/Core/Updates/UpdatePathPolicy.php';
        require_once $root.'/app/Core/Updates/FileUpdateEngine.php';
        require_once $root.'/app/Core/Updates/UpdateBarrier.php';
        self::$lease = (new UpdateBarrier($directory))->admit();
        if (self::$lease === null) {
            return false;
        }
        register_shutdown_function(self::release(...));

        return true;
    }

    public static function release(): void
    {
        if (is_resource(self::$lease)) {
            flock(self::$lease, LOCK_UN);
            fclose(self::$lease);
            self::$lease = null;
        }
    }

    public static function unavailable(): never
    {
        http_response_code(503);
        header('Cache-Control: no-store, private');
        header('Retry-After: 60');
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="robots" content="noindex,nofollow"><title>Actualización en curso</title><h1>Estamos actualizando el sitio</h1><p>Vuelve a intentarlo en unos instantes.</p></html>';
        exit;
    }

    public static function serve(string $root): never
    {
        header('Cache-Control: no-store, private');
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: application/json; charset=UTF-8');
        header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
        $storage = $root.'/storage/app/private/core-updates';
        try {
            $active = self::read($storage.'/active.json');
            $id = (string) ($active['run_id'] ?? '');
            if (! preg_match('/^[a-f0-9-]{36}$/D', $id)) {
                throw new RuntimeException('No hay una actualización activa.');
            }
            $directory = $storage.'/'.$id;
            require_once $directory.'/runtime/UpdatePathPolicy.php';
            require_once $directory.'/runtime/ReleaseManifest.php';
            require_once $directory.'/runtime/SignedManifestVerifier.php';
            require_once $directory.'/runtime/FileUpdateEngine.php';
            require_once $directory.'/runtime/UpdateBarrier.php';
            $run = self::read($directory.'/run.json');
            if (is_file($directory.'/progress.json')) {
                $progress = self::read($directory.'/progress.json');
                $run['completed_files'] = $progress['completed_files'] ?? 0;
            }
            $token = $_COOKIE['dbcms_update_recovery'] ?? '';
            if (! is_string($token) || ! hash_equals((string) ($run['recovery']['token_hash'] ?? ''), hash('sha256', $token)) || (int) ($run['recovery']['cookie_expires_at'] ?? 0) < time()) {
                http_response_code(403);
                echo json_encode(['error' => 'La autorización de recuperación ha caducado. Accede con tu administrador mediante el procedimiento de recuperación.']);
                exit;
            }
            self::currentActor($run, $token);
            $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            if ($method === 'GET') {
                if (str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'text/html')) {
                    self::recoveryPage($run, $directory);
                }
                echo json_encode(self::publicState($run));
                exit;
            }
            if ($method !== 'POST' || ! hash_equals((string) ($run['recovery']['csrf_hash'] ?? ''), hash('sha256', (string) ($_SERVER['HTTP_X_UPDATE_CSRF'] ?? '')))) {
                http_response_code(403);
                echo json_encode(['error' => 'La confirmación de recuperación no es válida.']);
                exit;
            }
            if (isset($_POST['password'])) {
                $lock = fopen($directory.'/run.lock', 'c+');
                if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
                    throw new RuntimeException('La actualización ya está en curso.');
                }
                try {
                    $run = self::read($directory.'/run.json');
                    $attempts = (array) ($run['recovery']['attempts'] ?? []);
                    $attempts = array_values(array_filter($attempts, fn ($at): bool => is_int($at) && $at > time() - 900));
                    if (count($attempts) >= 5) {
                        throw new RuntimeException('Demasiados intentos. Espera quince minutos.');
                    }
                    $attempts[] = time();
                    $run['recovery']['attempts'] = $attempts;
                    FileUpdateEngine::writeJson($directory.'/run.json', $run);
                    self::currentActor($run, $token, (string) $_POST['password']);
                    $run['recovery']['expires_at'] = time() + 900;
                    $run['recovery']['attempts'] = [];
                    FileUpdateEngine::writeJson($directory.'/run.json', $run);
                } finally {
                    flock($lock, LOCK_UN);
                    fclose($lock);
                }
                echo json_encode(self::publicState($run));
                exit;
            }
            if ((int) ($run['recovery']['expires_at'] ?? 0) < time()) {
                http_response_code(403);
                echo json_encode(['error' => 'Confirma de nuevo tu contraseña en esta pantalla de recuperación.', 'reauthentication_required' => true]);
                exit;
            }
            if (($run['status'] ?? '') === 'files') {
                $lock = fopen($directory.'/run.lock', 'c+');
                if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
                    throw new RuntimeException('La actualización ya está en curso.');
                }
                try {
                    $run = self::read($directory.'/run.json');
                    $verified = (new SignedManifestVerifier)->verify($run['manifest_raw'], $run['signature_raw'], self::read($directory.'/runtime/trusted-keys.json'));
                    if ($verified->hash !== $run['manifest_hash'] || $verified->data !== $run['manifest']) {
                        throw new RuntimeException('El manifiesto no corresponde a una publicación firmada válida.');
                    }
                    if (json_decode($run['manifest_raw'], true, 128, JSON_THROW_ON_ERROR) !== $run['manifest'] || ! hash_equals($run['plan_hash'], hash('sha256', json_encode(['plan' => $run['plan'], 'conflicts' => $run['conflicts'], 'preserved' => $run['preserved']], JSON_THROW_ON_ERROR))) || ! hash_equals($run['manifest_hash'], hash('sha256', $run['manifest_raw'])) || ! hash_equals($run['approval']['plan_hash'], $run['plan_hash']) || ! hash_equals($run['approval']['manifest_hash'], $run['manifest_hash'])) {
                        throw new RuntimeException('El plan aprobado ha cambiado.');
                    }
                    $backup = self::read($directory.'/backup/backup.json');
                    if (! hash_equals((string) ($run['approval']['backup_hash'] ?? ''), hash('sha256', json_encode($backup, JSON_THROW_ON_ERROR)))) {
                        throw new RuntimeException('La copia vinculada a la aprobación está alterada.');
                    }
                    foreach ($run['plan'] as $entry) {
                        if (($run['manifest']['distributions'][$run['distribution']]['files'][$entry['path']] ?? null) !== $entry['new_hash']) {
                            throw new RuntimeException('El plan no corresponde al manifiesto firmado.');
                        }
                    }
                    $engine = new FileUpdateEngine($root, $run['stage_path'], $directory.'/backup');
                    $engine->verifyBackup($run['plan'], $backup);
                    $completed = $engine->apply($run['plan']);
                    $run['completed_files'] = $completed;
                    if ($completed === count($run['plan'])) {
                        self::clearBootstrapCaches($root);
                        $run['status'] = 'files_applied';
                        $run['files_applied_request'] = defined('LARAVEL_START') ? LARAVEL_START : ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));
                    }
                    FileUpdateEngine::writeJson($directory.'/progress.json', ['completed_files' => $completed]);
                    if ($run['status'] !== 'files') {
                        FileUpdateEngine::writeJson($directory.'/run.json', $run);
                    }
                } catch (Throwable $failure) {
                    if (! ($run['migration_started'] ?? false) && ($run['manifest']['rollback_compatible'] ?? false) === true) {
                        try {
                            (new FileUpdateEngine($root, $run['stage_path'], $directory.'/backup'))->restore($run['plan']);
                            self::clearBootstrapCaches($root);
                            $run['status'] = 'code_restored';
                            $run['files_applied_request'] = defined('LARAVEL_START') ? LARAVEL_START : ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));
                            $run['error'] = 'Se restauró el código anterior. Es necesaria una comprobación nueva antes de reabrir.';
                        } catch (Throwable $restorationFailure) {
                            $run['status'] = 'recovery_required';
                            $run['private_error'] = $restorationFailure->getMessage();
                        }
                        FileUpdateEngine::writeJson($directory.'/run.json', $run);
                    } else {
                        throw $failure;
                    }
                } finally {
                    flock($lock, LOCK_UN);
                    fclose($lock);
                }
                echo json_encode(self::publicState($run));
                exit;
            }
            self::frameworkAdvance($root, $run);
        } catch (Throwable $exception) {
            http_response_code($exception instanceof UpdateRuntimeAccessDenied ? 403 : 409);
            echo json_encode(['error' => 'No se puede continuar de forma segura. El sitio permanece cerrado. Consulta el registro privado y el procedimiento de recuperación.']);
            error_log('dbCMS updater: '.$exception->getMessage());
            exit;
        }
    }

    public static function clearBootstrapCaches(string $root): void
    {
        foreach (glob($root.'/bootstrap/cache/*.php') ?: [] as $path) {
            if (is_link($path) || ! is_file($path) || ! unlink($path)) {
                throw new RuntimeException('No se pudo invalidar la caché del framework.');
            }
            if (function_exists('opcache_invalidate')) {
                opcache_invalidate($path, true);
            }
        }
    }

    /** @param array<string,mixed> $run */
    private static function currentActor(array $run, string $token, ?string $password = null): void
    {
        $sealed = $run['recovery']['profile'] ?? [];
        $ciphertext = base64_decode((string) ($sealed['ciphertext'] ?? ''), true);
        $iv = base64_decode((string) ($sealed['iv'] ?? ''), true);
        $tag = base64_decode((string) ($sealed['tag'] ?? ''), true);
        if ($ciphertext === false || $iv === false || $tag === false) {
            throw new UpdateRuntimeAccessDenied('Perfil de recuperación inválido.');
        }
        $plain = openssl_decrypt($ciphertext, 'aes-256-gcm', hash('sha256', $token, true), OPENSSL_RAW_DATA, $iv, $tag);
        if ($plain === false) {
            throw new UpdateRuntimeAccessDenied('Perfil de recuperación no autenticado.');
        }
        $profile = json_decode($plain, true, 32, JSON_THROW_ON_ERROR);
        $prefix = (string) ($profile['prefix'] ?? '');
        if (! preg_match('/^[a-zA-Z0-9_]*$/D', $prefix)) {
            throw new UpdateRuntimeAccessDenied('Prefijo de base de datos no admitido.');
        }
        $pdo = new PDO($profile['dsn'], $profile['username'], $profile['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION] + ($profile['options'] ?? []));
        $userQuery = $pdo->prepare('SELECT id,password,is_service_account FROM `'.$prefix.'users` WHERE id = ?');
        $userQuery->execute([$run['actor_id']]);
        $user = $userQuery->fetch(PDO::FETCH_ASSOC);
        if (! is_array($user) || (bool) $user['is_service_account'] || ! hash_equals((string) $run['recovery']['user_hash'], hash('sha256', $user['password'])) || ($password !== null && ! password_verify($password, $user['password']))) {
            throw new UpdateRuntimeAccessDenied('La cuenta o sus credenciales ya no están autorizadas.');
        }
        $roles = $pdo->prepare('SELECT r.slug FROM `'.$prefix.'roles` r INNER JOIN `'.$prefix.'role_user` ur ON ur.role_id=r.id WHERE ur.user_id=?');
        $roles->execute([$run['actor_id']]);
        $admin = array_intersect(['admin', 'super-admin'], $roles->fetchAll(PDO::FETCH_COLUMN)) !== [];
        if (! $admin) {
            $permissions = $pdo->prepare('SELECT DISTINCT p.slug FROM `'.$prefix.'permissions` p INNER JOIN `'.$prefix.'permission_role` pr ON pr.permission_id=p.id INNER JOIN `'.$prefix.'role_user` ur ON ur.role_id=pr.role_id WHERE ur.user_id=?');
            $permissions->execute([$run['actor_id']]);
            if (array_diff(['manage:updates', 'access:admin'], $permissions->fetchAll(PDO::FETCH_COLUMN)) !== []) {
                throw new UpdateRuntimeAccessDenied('Los permisos del administrador han sido retirados.');
            }
        }
        $id = (string) $profile['session_id'];
        if (! preg_match('/^[a-zA-Z0-9]{40}$/D', $id)) {
            throw new UpdateRuntimeAccessDenied('Sesión de recuperación no válida.');
        }
        if ($profile['session_driver'] === 'database') {
            $table = (string) $profile['session_table'];
            if (! preg_match('/^[a-zA-Z0-9_]+$/D', $table)) {
                throw new UpdateRuntimeAccessDenied('Tabla de sesiones no válida.');
            }
            $session = $pdo->prepare('SELECT user_id,last_activity FROM `'.$prefix.$table.'` WHERE id=?');
            $session->execute([$id]);
            $row = $session->fetch(PDO::FETCH_ASSOC);
            if (! is_array($row) || (int) $row['user_id'] !== (int) $run['actor_id'] || (int) $row['last_activity'] < time() - (int) $profile['session_lifetime']) {
                throw new UpdateRuntimeAccessDenied('La sesión original ha caducado o ha sido revocada.');
            }
        } elseif ($profile['session_driver'] === 'file') {
            $session = rtrim($profile['session_files'], '/').'/'.$id;
            if (! is_file($session) || is_link($session) || filemtime($session) < time() - (int) $profile['session_lifetime']) {
                throw new UpdateRuntimeAccessDenied('La sesión original ha caducado o ha sido revocada.');
            }
        } else {
            throw new UpdateRuntimeAccessDenied('El alojamiento no permite revalidar la sesión original.');
        }
    }

    /** @param array<string,mixed> $run */
    private static function recoveryPage(array $run, string $directory): never
    {
        $lock = fopen($directory.'/run.lock', 'c+');
        if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException('La actualización está procesando otra petición.');
        }
        try {
            $run = self::read($directory.'/run.json');
            $csrf = bin2hex(random_bytes(32));
            $run['recovery']['csrf_hash'] = hash('sha256', $csrf);
            $run['recovery']['expires_at'] = 0;
            FileUpdateEngine::writeJson($directory.'/run.json', $run);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        header('Referrer-Policy: no-referrer');
        $nonce = base64_encode(random_bytes(18));
        header('Content-Type: text/html; charset=UTF-8');
        header("Content-Security-Policy: default-src 'none'; script-src 'nonce-".$nonce."'; connect-src 'self'; style-src 'nonce-".$nonce."'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'");
        $id = json_encode($run['id'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
        echo '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><meta name="robots" content="noindex,nofollow"><title>Continuar actualización de dbCMS</title><style nonce="'.$nonce.'">body{font:17px system-ui;max-width:44rem;margin:10vh auto;padding:1.5rem;line-height:1.6}button,input{font:inherit;padding:.6rem}pre{white-space:pre-wrap}label{display:block;margin:1rem 0}</style><h1>Actualización de dbCMS</h1><p>Esta pantalla permite continuar la operación sin abrir el sitio a visitantes.</p><pre id="state"></pre><label>Contraseña del administrador (si se solicita)<input id="password" type="password" autocomplete="current-password"></label><button id="continue">Continuar de forma segura</button><p id="error" role="status"></p><script nonce="'.$nonce.'">const run='.$id.';const recoveryCsrf='.json_encode($csrf, JSON_THROW_ON_ERROR).'; const box=document.getElementById("state"),err=document.getElementById("error");function csrf(){if(recoveryCsrf)return recoveryCsrf;let v=sessionStorage.getItem("cms-update-run-"+run)||sessionStorage.getItem("cms-update-run");try{let j=JSON.parse(v);return j.csrf||j.recovery?.csrf||v}catch{return v}}async function state(){let r=await fetch(location.pathname,{headers:{Accept:"application/json"}});let j=await r.json();box.textContent=JSON.stringify(j,null,2)}document.getElementById("continue").onclick=async()=>{err.textContent="";const token=csrf();if(!token){err.textContent="Abre esta pantalla desde el navegador con el que confirmaste la actualización; falta la autorización de esta pestaña.";return}let body=new URLSearchParams();let password=document.getElementById("password").value;if(password)body.set("password",password);let r=await fetch(location.pathname,{method:"POST",headers:{Accept:"application/json","X-Update-CSRF":token},body});let j=await r.json();document.getElementById("password").value="";box.textContent=JSON.stringify(j,null,2);if(!r.ok)err.textContent=j.error||"Revisa la operación antes de continuar."};state();</script></html>';
        exit;
    }

    /** @param array<string, mixed> $run */
    private static function frameworkAdvance(string $root, array $run): never
    {
        require $root.'/vendor/autoload.php';
        $application = require $root.'/bootstrap/app.php';
        $application->make(Kernel::class)->bootstrap();
        $request = Request::capture();
        $application->instance('request', $request);
        $encrypt = new EncryptCookies($application['encrypter']);
        $start = $application->make(StartSession::class);
        $response = $encrypt->handle($request, fn ($request) => $start->handle($request, function ($request) use ($application, $run) {
            $application['auth']->forgetGuards();
            $user = $application['auth']->guard('web')->user();
            if ($user === null || (int) $user->getAuthIdentifier() !== (int) $run['actor_id'] || ! hash_equals((string) $run['recovery']['session_hash'], hash('sha256', $request->session()->getId()))) {
                return response()->json(['error' => 'La sesión del administrador ya no está vigente.'], 403);
            }
            $application->instance('request', $request);
            $executor = $application->make(UpdateExecutionServiceInterface::class);
            $result = $request->input('action') === 'cancel' ? $executor->cancel($run['id'], $run['actor_id']) : $executor->advance($run['id'], $run['actor_id']);

            return response()->json($result);
        }));
        $response->send();
        exit;
    }

    /** @return array<string, mixed> */
    private static function read(string $path): array
    {
        $content = @file_get_contents($path);
        if ($content === false || is_link($path)) {
            throw new RuntimeException('Registro privado de actualización inaccesible.');
        }
        $value = json_decode($content, true, 128, JSON_THROW_ON_ERROR);
        if (! is_array($value)) {
            throw new RuntimeException('Registro privado inválido.');
        }

        return $value;
    }

    /** @param array<string, mixed> $run
     * @return array<string, mixed>
     */
    private static function publicState(array $run): array
    {
        return [...array_intersect_key($run, array_flip(['id', 'status', 'version', 'completed_files', 'mode', 'health', 'error'])), 'reauthentication_required' => (int) ($run['recovery']['expires_at'] ?? 0) < time()];
    }
}
