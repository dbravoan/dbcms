<?php

declare(strict_types=1);

namespace App\Services\Updates;

use App\Contracts\Services\UpdateCatalogServiceInterface;
use App\Core\Updates\UpdateRunStore;
use App\DTOs\UpdateReleaseCandidate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final class UpdateCatalogService implements UpdateCatalogServiceInterface
{
    private const CACHE_KEY = 'cms.updates.catalog.v1';

    public function __construct(private readonly GithubReleaseGateway $releases, private readonly UpdateRunStore $runs, private readonly UpdateNotificationService $notifications) {}

    public function status(): array
    {
        $state = Cache::get(self::CACHE_KEY, []);
        $identity = $this->runs->identity();
        $installed = $identity === null ? $this->declaredIdentity() : [...array_intersect_key($identity, array_flip(['version', 'source_sha', 'distribution', 'adopted_at'])), 'verified' => true];
        $state = array_merge(['available' => null, 'checked_at' => null, 'error' => null], is_array($state) ? $state : [], ['installed' => $installed]);
        if (is_string($installed['version'] ?? null) && is_string($state['available']['version'] ?? null) && version_compare($state['available']['version'], $installed['version'], '<=')) {
            $state['available'] = null;
        }

        return $state;
    }

    /** @return array{version: ?string, verified: false} */
    private function declaredIdentity(): array
    {
        $path = resource_path('updates/core-version.json');
        $bytes = is_file($path) ? file_get_contents($path) : false;
        $descriptor = $bytes === false ? null : json_decode($bytes, true);
        $version = is_array($descriptor) && is_string($descriptor['version'] ?? null) && preg_match('/^\d+\.\d+\.\d+$/D', $descriptor['version']) ? $descriptor['version'] : null;

        return ['version' => $version, 'verified' => false];
    }

    public function check(bool $force = false): array
    {
        $state = $this->status();
        $checked = $state['last_attempt_at'] ?? $state['checked_at'];
        if (! $force && is_int($checked) && time() - $checked < (int) config('updates.check_interval', 43200)) {
            return $state;
        }
        $lock = Cache::lock('cms.updates.check', 120);
        if (! $lock->get()) {
            return $state;
        }
        try {
            $stable = $this->releases->listStable();
            usort($stable, fn (array $a, array $b): int => version_compare((string) $b['version'], (string) $a['version']));
            $latest = $stable[0] ?? null;
            $installed = $state['installed']['version'] ?? null;
            $state['available'] = $latest !== null && (! is_string($installed) || version_compare((string) $latest['version'], $installed, '>')) ? UpdateReleaseCandidate::fromRelease($latest)->toArray() : null;
            $state['error'] = null;
            $state['checked_at'] = time();
            $state['last_attempt_at'] = time();
            Cache::forever(self::CACHE_KEY, $state);
            if (is_array($state['available'])) {
                $this->notifications->notify($state['available']);
            }
        } catch (\Throwable $exception) {
            $state['error'] = 'No se pudo comprobar GitHub. Se conserva la última información disponible.';
            // Keep the last successful timestamp while throttling unavailable upstream calls.
            $state['last_attempt_at'] = time();
            Cache::forever(self::CACHE_KEY, $state);
            Log::warning('Core update discovery failed', ['exception' => $exception::class]);
        } finally {
            $lock->release();
        }

        return $state;
    }
}
