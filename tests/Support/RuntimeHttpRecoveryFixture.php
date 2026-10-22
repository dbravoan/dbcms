<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Updates\FileUpdateEngine;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PDO;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Boundary fixture only: an already verified/approved private run is supplied
 * directly. Manifest authenticity is exercised by DistributionVerificationTest;
 * this harness tests the production standalone HTTP runtime without vendor.
 */
final class RuntimeHttpRecoveryFixture
{
    public string $root;

    public string $runDirectory;

    public string $prefix;

    public string $url;

    public string $token;

    public string $csrf;

    public PDO $pdo;

    public Process $server;

    /** @var array<string,mixed> */
    public array $run;

    /** @var list<string> */
    private array $tables = ['sessions', 'permission_role', 'permissions', 'role_user', 'roles', 'users'];

    public function __construct(int $fileCount = 1)
    {
        $this->root = sys_get_temp_dir().'/update-http-'.bin2hex(random_bytes(8));
        $this->prefix = 'upd_http_'.bin2hex(random_bytes(6)).'_';
        $config = config('database.connections.mysql');
        $dsn = 'mysql:host='.$config['host'].';port='.($config['port'] ?? 3306).';dbname='.$config['database'].';charset=utf8mb4';
        $this->pdo = new PDO($dsn, $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->pdo->exec('CREATE TABLE `'.$this->prefix.'users` (id BIGINT PRIMARY KEY,password VARCHAR(255) NOT NULL,is_service_account BOOLEAN NOT NULL DEFAULT 0) ENGINE=InnoDB');
        $this->pdo->exec('CREATE TABLE `'.$this->prefix.'roles` (id BIGINT PRIMARY KEY,slug VARCHAR(64) NOT NULL) ENGINE=InnoDB');
        $this->pdo->exec('CREATE TABLE `'.$this->prefix.'role_user` (user_id BIGINT,role_id BIGINT) ENGINE=InnoDB');
        $this->pdo->exec('CREATE TABLE `'.$this->prefix.'permissions` (id BIGINT PRIMARY KEY,slug VARCHAR(64) NOT NULL) ENGINE=InnoDB');
        $this->pdo->exec('CREATE TABLE `'.$this->prefix.'permission_role` (permission_id BIGINT,role_id BIGINT) ENGINE=InnoDB');
        $this->pdo->exec('CREATE TABLE `'.$this->prefix.'sessions` (id VARCHAR(40) PRIMARY KEY,user_id BIGINT,last_activity BIGINT) ENGINE=InnoDB');
        $password = password_hash('fixture-admin-password', PASSWORD_BCRYPT, ['cost' => 4]);
        $statement = $this->pdo->prepare('INSERT INTO `'.$this->prefix.'users` VALUES (1,?,0)');
        $statement->execute([$password]);
        $this->pdo->exec('INSERT INTO `'.$this->prefix.'roles` VALUES (1,"updater-manager")');
        $this->pdo->exec('INSERT INTO `'.$this->prefix.'role_user` VALUES (1,1)');
        $this->pdo->exec('INSERT INTO `'.$this->prefix.'permissions` VALUES (1,"access:admin"),(2,"manage:updates")');
        $this->pdo->exec('INSERT INTO `'.$this->prefix.'permission_role` VALUES (1,1),(2,1)');
        $sessionId = str_repeat('a', 40);
        $statement = $this->pdo->prepare('INSERT INTO `'.$this->prefix.'sessions` VALUES (?,1,?)');
        $statement->execute([$sessionId, time()]);
        $id = (string) Str::uuid();
        $storage = $this->root.'/storage/app/private/core-updates';
        $this->runDirectory = $storage.'/'.$id;
        foreach (['public', 'bootstrap/cache', 'vendor', 'app', 'stage/app', 'storage/app/private/core-updates/'.$id.'/runtime'] as $directory) {
            mkdir($this->root.'/'.$directory, 0700, true);
        }
        copy(base_path('public/update.php'), $this->root.'/public/update.php');
        copy(base_path('bootstrap/UpdateRuntime.php'), $this->runDirectory.'/runtime/UpdateRuntime.php');
        foreach (['FileUpdateEngine', 'UpdateBarrier', 'UpdatePathPolicy', 'ReleaseManifest', 'SignedManifestVerifier'] as $class) {
            copy(base_path('app/Core/Updates/'.$class.'.php'), $this->runDirectory.'/runtime/'.$class.'.php');
        }
        // None of these damaged framework files may be loaded while resuming files.
        file_put_contents($this->root.'/vendor/autoload.php', '<?php throw new RuntimeException("vendor is intentionally unavailable");');
        file_put_contents($this->root.'/bootstrap/UpdateRuntime.php', '<?php throw new RuntimeException("use the protected runtime snapshot");');
        file_put_contents($this->root.'/.env', 'APP_KEY=preserved-fixture-key');
        $plan = [];
        $inventory = [];
        for ($index = 0; $index < $fileCount; $index++) {
            $path = 'app/file-'.$index.'.php';
            file_put_contents($this->root.'/'.$path, 'old-'.$index);
            file_put_contents($this->root.'/stage/'.$path, 'new-'.$index);
            $inventory[$path] = hash('sha256', 'new-'.$index);
            $plan[] = ['path' => $path, 'old_hash' => hash('sha256', 'old-'.$index), 'new_hash' => $inventory[$path], 'action' => 'replace'];
        }
        $comparison = ['plan' => $plan, 'conflicts' => [], 'preserved' => []];
        $distribution = ['asset' => 'fixture.zip', 'sha256' => str_repeat('c', 64), 'size' => 1, 'files' => $inventory];
        $manifest = ['schema' => 1, 'version' => '1.1.0', 'source_sha' => str_repeat('b', 40), 'min_php' => '8.3.0', 'extensions' => [], 'supported_from' => ['1.0.1'], 'rollback_compatible' => false, 'migrations' => ['core' => [], 'modules' => []], 'distributions' => ['source' => $distribution, 'runtime' => $distribution]];
        $raw = json_encode($manifest, JSON_THROW_ON_ERROR);
        static $key;
        $key ??= openssl_pkey_new(['private_key_bits' => 3072, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_sign($raw, $signed, $key, OPENSSL_ALGO_SHA256);
        $signature = json_encode(['key_id' => 'boundary-fixture', 'signature' => base64_encode($signed)], JSON_THROW_ON_ERROR);
        FileUpdateEngine::writeJson($this->runDirectory.'/runtime/trusted-keys.json', ['boundary-fixture' => openssl_pkey_get_details($key)['key']]);
        $backup = ['fixture_boundary' => 'already verified backup', 'verified' => true, 'environment_sha256' => hash('sha256', 'APP_KEY=preserved-fixture-key'), 'database' => ['kind' => 'external', 'reference' => 'boundary-fixture'], 'passport' => []];
        (new FileUpdateEngine($this->root, $this->root.'/stage', $this->runDirectory.'/backup'))->backupPlan($plan);
        copy($this->root.'/.env', $this->runDirectory.'/backup/environment.backup');
        FileUpdateEngine::writeJson($this->runDirectory.'/backup/backup.json', $backup);
        $this->token = bin2hex(random_bytes(32));
        $this->csrf = bin2hex(random_bytes(32));
        $profile = ['dsn' => $dsn, 'username' => $config['username'], 'password' => $config['password'], 'prefix' => $this->prefix, 'session_driver' => 'database', 'session_id' => $sessionId, 'session_table' => 'sessions', 'session_lifetime' => 7200];
        $iv = random_bytes(12);
        $ciphertext = openssl_encrypt(json_encode($profile, JSON_THROW_ON_ERROR), 'aes-256-gcm', hash('sha256', $this->token, true), OPENSSL_RAW_DATA, $iv, $tag);
        $this->run = ['id' => $id, 'actor_id' => 1, 'status' => 'files', 'mode' => 'runtime', 'version' => '1.1.0', 'distribution' => 'runtime', ...$comparison, 'stage_path' => $this->root.'/stage', 'manifest' => $manifest, 'manifest_raw' => $raw, 'signature_raw' => $signature, 'manifest_hash' => hash('sha256', $raw), 'plan_hash' => hash('sha256', json_encode($comparison, JSON_THROW_ON_ERROR))];
        $this->run['approval'] = ['actor_id' => 1, 'manifest_hash' => $this->run['manifest_hash'], 'plan_hash' => $this->run['plan_hash'], 'backup_hash' => hash('sha256', json_encode($backup, JSON_THROW_ON_ERROR))];
        $this->run['recovery'] = ['profile' => ['ciphertext' => base64_encode($ciphertext), 'iv' => base64_encode($iv), 'tag' => base64_encode($tag)], 'token_hash' => hash('sha256', $this->token), 'csrf_hash' => hash('sha256', $this->csrf), 'expires_at' => time() + 900, 'cookie_expires_at' => time() + 86400, 'user_hash' => hash('sha256', $password), 'session_hash' => hash('sha256', $sessionId)];
        $this->save();
        FileUpdateEngine::writeJson($storage.'/active.json', ['run_id' => $id]);
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $this->url = 'http://'.$address.'/update.php';
        $this->server = new Process([PHP_BINARY, '-d', 'display_errors=0', '-S', $address, '-t', $this->root.'/public'], $this->root);
        $this->server->start();
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $probe = @stream_socket_client('tcp://'.$address, $errno, $error, 0.02);
            if (is_resource($probe)) {
                fclose($probe);

                return;
            }
            usleep(10000);
        }
        throw new RuntimeException('Isolated recovery HTTP server did not start.');
    }

    public function save(): void
    {
        FileUpdateEngine::writeJson($this->runDirectory.'/run.json', $this->run);
    }

    /** @return array{status:int,body:array<string,mixed>} */
    public function request(string $method = 'GET', bool $authenticated = true, string $form = '', ?string $csrf = null): array
    {
        $headers = ['Accept: application/json'];
        if ($authenticated) {
            $headers[] = 'Cookie: dbcms_update_recovery='.$this->token;
            $headers[] = 'X-Update-CSRF: '.($csrf ?? $this->csrf);
        }
        if ($method === 'POST') {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        }
        $context = stream_context_create(['http' => ['method' => $method, 'header' => implode("\r\n", $headers), 'content' => $form, 'ignore_errors' => true, 'timeout' => 10]]);
        $body = file_get_contents($this->url, false, $context);
        preg_match('~HTTP/\S+ (\d+)~', $http_response_header[0], $match);

        return ['status' => (int) $match[1], 'body' => json_decode($body, true, 128, JSON_THROW_ON_ERROR)];
    }

    public function close(): void
    {
        if (isset($this->server)) {
            $this->server->stop();
        }
        foreach ($this->tables as $table) {
            $this->pdo->exec('DROP TABLE IF EXISTS `'.$this->prefix.$table.'`');
        }
        File::deleteDirectory($this->root);
    }

    public function recoveryHtml(): string
    {
        $context = stream_context_create(['http' => ['header' => "Accept: text/html\r\nCookie: dbcms_update_recovery=".$this->token, 'ignore_errors' => true, 'timeout' => 10]]);

        return file_get_contents($this->url, false, $context);
    }
}
