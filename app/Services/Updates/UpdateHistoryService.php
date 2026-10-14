<?php

declare(strict_types=1);

namespace App\Services\Updates;

use App\Contracts\Services\UpdateHistoryServiceInterface;
use App\Core\Updates\UpdateRunStore;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;

final class UpdateHistoryService implements UpdateHistoryServiceInterface
{
    public function __construct(private readonly UpdateRunStore $store) {}

    public function recent(int $actorId): array
    {
        $actor = User::find($actorId);
        if ($actor === null || (bool) $actor->getAttribute('is_service_account') || ! $actor->hasPermission('access:admin') || ! $actor->hasPermission('manage:updates')) {
            throw new AuthorizationException('No tienes acceso al historial de actualizaciones.');
        }
        $history = [];
        $scanned = 0;
        foreach (new \DirectoryIterator($this->store->root()) as $entry) {
            if (++$scanned > 1000) {
                break;
            }
            if (! $entry->isDir() || $entry->isLink() || ! Str::isUuid($entry->getFilename())) {
                continue;
            }
            $path = $entry->getPathname().'/summary.json';
            $size = is_file($path) && ! is_link($path) ? filesize($path) : false;
            if ($size === false || $size > 8192) {
                continue;
            }
            $bytes = file_get_contents($path);
            $summary = $bytes === false ? null : json_decode($bytes, true);
            if (! is_array($summary) || ($summary['id'] ?? '') !== $entry->getFilename() || ($summary['actor_id'] ?? null) !== $actorId) {
                continue;
            }
            $history[] = array_intersect_key($summary, array_flip(['id', 'status', 'mode', 'version', 'created_at', 'updated_at', 'completed_at']));
        }
        usort($history, fn (array $a, array $b): int => strcmp((string) ($b['updated_at'] ?? ''), (string) ($a['updated_at'] ?? '')));

        return array_slice($history, 0, 20);
    }
}
