<?php

declare(strict_types=1);

namespace Modules\SocialPublishing\Connectors;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Modules\Marketing\Contracts\MarketingArtifactStoreInterface;
use Modules\Marketing\DTOs\CreativePackage;
use Modules\SocialPublishing\Contracts\SocialConnector;
use Modules\SocialPublishing\DTOs\OperationResult;
use Modules\SocialPublishing\Models\SocialAccount;

abstract class AbstractConnector implements SocialConnector
{
    public function __construct(protected readonly MarketingArtifactStoreInterface $files) {}

    protected function http(SocialAccount $account): PendingRequest
    {
        return Http::withToken($account->access_token ?? '')->acceptJson()->connectTimeout(5)->timeout(30)->withOptions(['allow_redirects' => false, 'progress' => static function (float $total, float $received): void {
            if ($received > 2097152) {
                throw new \RuntimeException('Respuesta demasiado grande.');
            }
        }]);
    }

    protected function result(Response $r, string $field = 'id', string $state = 'published', ?string $next = null): OperationResult
    {
        if (in_array($r->status(), [401, 403], true)) {
            return new OperationResult('blocked', error: 'account_grant_revoked');
        }
        if ($r->status() === 429) {
            return new OperationResult('retry_safe', error: 'provider_rate_limited', retryAfter: max(60, min(3600, (int) $r->header('Retry-After'))));
        }
        if ($r->serverError() || $r->redirect()) {
            return new OperationResult('pending_confirmation', error: 'provider_result_uncertain');
        }
        if (! $r->successful() || strlen($r->body()) > 2097152 || $r->json('error')) {
            return new OperationResult('failed', error: 'provider_rejected');
        }
        $id = $field === '@restli' ? $r->header('x-restli-id') : $r->json($field);
        if (! is_string($id) || $id === '' || strlen($id) > 250) {
            return new OperationResult('pending_confirmation', error: 'remote_identifier_missing');
        }

        return new OperationResult($state, $next, $state === 'published' ? $id : null, ['id' => $id]);
    }

    protected function ensure(bool $valid, string $message): void
    {
        if (! $valid) {
            throw ValidationException::withMessages(['publication' => $message]);
        }
    }

    /** @param list<string> $formats */
    protected function media(CreativePackage $package, array $formats, int $max): void
    {
        $this->ensure(count($package->artifacts) <= $max, 'La cantidad de archivos supera la capacidad de esta red.');
        foreach ($package->artifacts as $file) {
            $this->ensure(in_array($file->mimeType, $formats, true) && $this->files->verify($file), 'Formato no compatible o archivo modificado.');
        }
    }

    public function mutation(string $step): bool
    {
        return ! in_array($step, ['poll', 'confirm', 'reconcile'], true) && ! str_starts_with($step, 'poll:');
    }

    /** @return array<string,mixed> */
    public function metrics(SocialAccount $account, string $remote): array
    {
        return ['availability' => 'unavailable', 'source' => $account->provider, 'reason' => 'metrics_permission_or_contract_unavailable', 'values' => []];
    }

    protected function signedUpload(string $url, string $domain): string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $this->ensure(parse_url($url, PHP_URL_SCHEME) === 'https' && ($host === $domain || str_ends_with($host, '.'.$domain)) && parse_url($url, PHP_URL_USER) === null && parse_url($url, PHP_URL_PORT) === null, 'El proveedor devolvió un destino de subida no autorizado.');

        return $url;
    }
}
