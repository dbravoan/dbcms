<?php

declare(strict_types=1);

namespace App\Services\Projects;

use App\DTOs\ProjectLocalEnvironmentPlan;
use App\DTOs\ProjectProfile;
use App\Services\Install\EnvValueSerializer;
use Dotenv\Dotenv;
use RuntimeException;

final class ProjectLocalEnvironmentService
{
    private const PORTS = ['APP_PORT' => 80, 'VITE_PORT' => 5173, 'FORWARD_DB_PORT' => 3306, 'FORWARD_REDIS_PORT' => 6379, 'FORWARD_MAILPIT_PORT' => 1025, 'FORWARD_MAILPIT_DASHBOARD_PORT' => 8025, 'FORWARD_ADMINER_PORT' => 8088];

    public function plan(string $root, ProjectProfile $profile): ProjectLocalEnvironmentPlan
    {
        $root = realpath($root) ?: throw new RuntimeException('Project directory does not exist.');
        $path = $root.'/.env';
        $this->rejectEnvironmentLink($path);
        $contents = is_file($path) ? $this->read($path) : null;
        $values = $contents === null ? [] : Dotenv::parse($contents);
        // The launcher forwards the host directory: every mount is /var/www/html.
        $directory = getenv('DBCMS_PROJECT_DIRECTORY') ?: $root;
        $id = 'dbcms-'.substr(hash('sha256', $directory), 0, 12);
        $ports = [];
        $candidate = 18000 + (int) (hexdec(substr(hash('sha256', $directory), 0, 4)) % 30000);
        foreach (self::PORTS as $key => $default) {
            if ($contents !== null) {
                $value = $values[$key] ?? (string) $default;
                if (! ctype_digit($value) || (int) $value < 1 || (int) $value > 65535) {
                    throw new RuntimeException('Invalid local port: '.$key);
                }
                $ports[$key] = (int) $value;
            } else {
                while ($candidate < 65535 && ! $this->portAvailable($candidate)) {
                    $candidate++;
                }
                if ($candidate >= 65535) {
                    throw new RuntimeException('No available local ports. Retry after freeing host ports.');
                }
                $ports[$key] = $candidate++;
            }
        }
        $baseUrl = 'http://localhost:'.$ports['APP_PORT'];

        return new ProjectLocalEnvironmentPlan($root, $values['COMPOSE_PROJECT_NAME'] ?? $id, $ports, [
            'Application' => $baseUrl,
            'Installer' => $baseUrl.'/install',
            'Mailpit' => 'http://localhost:'.$ports['FORWARD_MAILPIT_DASHBOARD_PORT'],
            'Adminer' => 'http://localhost:'.$ports['FORWARD_ADMINER_PORT'],
        ], $contents === null, $contents === null ? null : hash('sha256', $contents));
    }

    public function apply(ProjectLocalEnvironmentPlan $plan, ProjectProfile $profile): void
    {
        $path = $plan->root.'/.env';
        $this->rejectEnvironmentLink($path);
        $contents = is_file($path) ? $this->read($path) : null;
        if (($contents === null ? null : hash('sha256', $contents)) !== $plan->environmentHash) {
            throw new RuntimeException('Environment changed after preview; restart initialization to review it again.');
        }
        if ($plan->createsEnvironment) {
            foreach ($plan->ports as $port) {
                if (! $this->portAvailable($port)) {
                    throw new RuntimeException('A planned host port is now occupied; retry initialization.');
                }
            }
            $contents = $this->read($plan->root.'/.env.example');
            $settings = [
                'APP_NAME' => $profile->name, 'APP_ENV' => 'local', 'APP_URL' => $plan->urls['Application'],
                'CMS_BRAND' => $profile->brand, 'CMS_THEME' => $profile->defaultTheme,
                'COMPOSE_PROJECT_NAME' => $plan->projectId, 'SESSION_COOKIE' => $plan->projectId.'_session',
                'CACHE_PREFIX' => $plan->projectId.'_cache_', 'REDIS_PREFIX' => $plan->projectId.'_redis_',
                'CMS_SEED_DEMO' => 'false', 'CMS_QUICK_LOGIN' => 'false',
                ...array_map(strval(...), $plan->ports),
            ];
            foreach ($settings as $key => $value) {
                $contents = $this->replace($contents, $key, $value);
            }
        }
        if ($contents === null) {
            throw new RuntimeException('Existing environment disappeared; retry initialization.');
        }
        $values = Dotenv::parse($contents);
        if (($values['APP_KEY'] ?? '') === '') {
            $contents = $this->replace($contents, 'APP_KEY', 'base64:'.base64_encode(random_bytes(32)));
        }
        if (! $plan->createsEnvironment && hash('sha256', $contents) === $plan->environmentHash) {
            return;
        }
        // Exclusive creation prevents replacing an environment created since preview.
        $handle = fopen($path, $plan->createsEnvironment ? 'x' : 'c+');
        if ($handle === false) {
            throw new RuntimeException('Cannot write the project environment.');
        }
        try {
            if (! flock($handle, LOCK_EX)) {
                throw new RuntimeException('Cannot lock the project environment.');
            }
            if (! $plan->createsEnvironment) {
                $current = stream_get_contents($handle);
                if ($current === false || hash('sha256', $current) !== $plan->environmentHash) {
                    throw new RuntimeException('Environment changed after preview; retry initialization.');
                }
            }
            rewind($handle);
            if (fwrite($handle, $contents) !== strlen($contents) || ! ftruncate($handle, strlen($contents))) {
                throw new RuntimeException('Cannot finish writing the project environment.');
            }
            fflush($handle);
        } finally {
            fclose($handle);
        }
        if ($plan->createsEnvironment) {
            chmod($path, 0600);
        }
    }

    private function rejectEnvironmentLink(string $path): void
    {
        clearstatcache(true, $path);
        if (is_link($path)) {
            throw new RuntimeException('Project .env must be a regular file, not a symbolic link.');
        }
    }

    private function replace(string $contents, string $key, string $value): string
    {
        $line = $key.'='.EnvValueSerializer::encode($value);
        $result = preg_replace_callback('/^'.preg_quote($key, '/').'=[^\r\n]*$/m', static fn (): string => $line, $contents, -1, $count);
        if ($result === null) {
            throw new RuntimeException('Cannot serialize the project environment.');
        }

        return $count === 0 ? rtrim($result)."\n".$line."\n" : $result;
    }

    private function portAvailable(int $port): bool
    {
        // Bootstrap uses Docker --network host on Linux. Container bridge probes
        // would incorrectly report occupied host ports as free.
        set_error_handler(static fn (): bool => true, E_WARNING);
        try {
            $socket = stream_socket_server('tcp://0.0.0.0:'.$port, $errorCode, $errorMessage);
        } finally {
            restore_error_handler();
        }
        if ($socket === false) {
            return false;
        }
        fclose($socket);

        return true;
    }

    private function read(string $path): string
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('Cannot read '.$path);
        }

        return $contents;
    }
}
