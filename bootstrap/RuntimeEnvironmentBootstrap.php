<?php

declare(strict_types=1);

/** Provision the Docker volume before Artisan or HTTP can use its .env link. */
final class RuntimeEnvironmentBootstrap
{
    public static function ensure(string $path, string $key, string $url): void
    {
        $bytes = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;
        if (! is_string($bytes) || strlen($bytes) !== 32 || ! filter_var($url, FILTER_VALIDATE_URL) || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true) || is_link($path)) {
            throw new RuntimeException('Invalid persistent runtime configuration.');
        }
        if (is_file($path)) {
            $values = Dotenv\Dotenv::parse((string) file_get_contents($path));
            if (! hash_equals($key, $values['APP_KEY'] ?? '')) {
                throw new RuntimeException('Restore the original runtime APP_KEY before starting the application.');
            }

            return;
        }
        $quote = static fn (string $value): string => '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"';
        $content = 'APP_ENV=production'."\nAPP_KEY=".$quote($key)."\nAPP_URL=".$quote($url)."\n";
        $temporary = tempnam(dirname($path), '.runtime-env-');
        if ($temporary === false) {
            throw new RuntimeException('Cannot create the runtime environment.');
        }
        try {
            if (! chmod($temporary, 0600) || file_put_contents($temporary, $content) !== strlen($content)) {
                throw new RuntimeException('Cannot protect the runtime environment.');
            }
            // Another replica may have provisioned the same persistent key.
            if (! @link($temporary, $path) && ! self::matches($path, $key)) {
                throw new RuntimeException('Concurrent runtime configuration differs from the supplied key.');
            }
        } finally {
            unlink($temporary);
        }
    }

    private static function matches(string $path, string $key): bool
    {
        clearstatcache(true, $path);

        return ! is_link($path) && is_file($path) && hash_equals($key, Dotenv\Dotenv::parse((string) file_get_contents($path))['APP_KEY'] ?? '');
    }
}
