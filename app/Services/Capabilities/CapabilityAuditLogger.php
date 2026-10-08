<?php

declare(strict_types=1);

namespace App\Services\Capabilities;

use App\Models\CapabilityAuditLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Records what agents do, and answers "what has the agent done?".
 */
class CapabilityAuditLogger
{
    /**
     * Argument keys never persisted (they are secrets, not history).
     *
     * @var list<string>
     */
    private const REDACTED_KEYS = [
        'password',
        'password_confirmation',
        'secret',
        'secret_key',
        'client_secret',
        'token',
        'access_token',
        'refresh_token',
        'private_key',
        'api_key',
        'authorization',
    ];

    /**
     * @param  array<string, mixed>  $context  tool, ability, permission, status, args, error, duration_ms, user, token_id, client_id, is_m2m, ip
     */
    public function log(array $context): CapabilityAuditLog
    {
        $user = $context['user'] ?? null;

        return CapabilityAuditLog::create([
            'user_id' => $user instanceof User ? $user->getKey() : null,
            'token_id' => is_string($context['token_id'] ?? null) ? (string) $context['token_id'] : null,
            'client_id' => is_string($context['client_id'] ?? null) ? (string) $context['client_id'] : null,
            'is_m2m' => filter_var($context['is_m2m'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'tool' => (string) ($context['tool'] ?? ''),
            'ability' => (string) ($context['ability'] ?? 'read'),
            'permission' => is_string($context['permission'] ?? null) ? (string) $context['permission'] : null,
            'status' => (string) ($context['status'] ?? 'ok'),
            'args' => $this->redact(is_array($context['args'] ?? null) ? $context['args'] : []),
            'error' => $context['error'] ?? null,
            'duration_ms' => is_numeric($context['duration_ms'] ?? null) ? (int) $context['duration_ms'] : null,
            'ip' => is_string($context['ip'] ?? null) ? (string) $context['ip'] : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function redact(array $args): array
    {
        $clean = [];

        foreach ($args as $key => $value) {
            $normalizedKey = strtolower((string) $key);

            foreach (self::REDACTED_KEYS as $secret) {
                if (str_contains($normalizedKey, $secret)) {
                    $clean[$key] = '***';

                    continue 2;
                }
            }

            if (is_array($value)) {
                $clean[$key] = $this->redact($value);

                continue;
            }

            $clean[$key] = is_scalar($value) || $value === null ? $value : '[complex]';
        }

        return $clean;
    }

    /**
     * @return Collection<int, CapabilityAuditLog>
     */
    public function recent(int $limit = 50, ?string $tool = null, bool $onlyProblems = false): Collection
    {
        $query = CapabilityAuditLog::query()->with('user')->latest('id');

        if ($tool !== null && $tool !== '') {
            $query->where('tool', $tool);
        }

        if ($onlyProblems) {
            $query->problems();
        }

        return $query->limit(max(1, min(200, $limit)))->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function stats(?Carbon $since = null): array
    {
        $since = $since ?? Carbon::now()->subDays(7);

        $query = CapabilityAuditLog::query()->where('created_at', '>=', $since);

        return [
            'since' => $since->toIso8601String(),
            'total' => (int) (clone $query)->count(),
            'by_status' => (clone $query)
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status')
                ->map(static fn (mixed $total): int => (int) $total)
                ->all(),
            'top_tools' => (clone $query)
                ->selectRaw('tool, count(*) as total')
                ->groupBy('tool')
                ->orderByDesc('total')
                ->limit(10)
                ->pluck('total', 'tool')
                ->map(static fn (mixed $total): int => (int) $total)
                ->all(),
        ];
    }
}
