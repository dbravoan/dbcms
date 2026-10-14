<?php

declare(strict_types=1);

namespace App\Core\Updates;

final class UpdatePathPolicy
{
    public function allows(string $path): bool
    {
        if ($path === '' || str_starts_with($path, '/') || str_contains($path, '\\') || str_contains($path, ':') || preg_match('/[\x00-\x1f\x7f]/', $path)) {
            return false;
        }
        foreach (explode('/', $path) as $component) {
            if ($component === '' || $component === '.' || $component === '..' || rtrim($component, '. ') !== $component) {
                return false;
            }
        }
        $lower = strtolower($path);
        if (preg_match('~^(?:\.git(?:/|$)|storage(?:/|$)|public/(?:storage|uploads)(?:/|$)|bootstrap/cache(?:/|$)|dist(?:/|$)|node_modules(?:/|$)|resources/updates/installed-manifest\.(?:json|sig)$|\.mcp\.json$|mcp/mcp_config\.json$|\.phpunit\.cache(?:/|$))~', $lower)) {
            return false;
        }

        return ! preg_match('~(^|/)(?:\.env(?!\.(?:installation|production)\.example$|\.example$)[^/]*|[^/]*\.(?:key|pem)|installed|install-finalize\.lock)$~', $lower);
    }

    public function requireAllowed(string $path): void
    {
        if (! $this->allows($path)) {
            throw new \DomainException('Ruta no admitida en la actualización: '.$path);
        }
    }
}
