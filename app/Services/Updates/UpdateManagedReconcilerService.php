<?php

declare(strict_types=1);

namespace App\Services\Updates;

use App\Contracts\Services\UpdateManagedReconcilerServiceInterface;
use App\Contracts\Services\UpdateMigrationServiceInterface;
use App\Core\Updates\SignedManifestVerifier;
use App\Core\Updates\UpdatePathPolicy;
use App\Core\Updates\UpdateRunStore;

final class UpdateManagedReconcilerService implements UpdateManagedReconcilerServiceInterface
{
    public function __construct(private readonly UpdateRunStore $store, private readonly SignedManifestVerifier $verifier, private readonly UpdateMigrationServiceInterface $migrations) {}

    public function reconcile(string $runId): array
    {
        return $this->store->withLock($runId, function (array $run) use ($runId): array {
            if (($run['mode'] ?? '') !== 'managed' || ! in_array($run['status'] ?? '', ['managed', 'conflict'], true)) {
                return $run;
            }
            $root = realpath((string) config('updates.root_path'));
            if ($root === false) {
                return $run;
            }
            $raw = $this->read($root.'/resources/updates/installed-manifest.json');
            $signature = $this->read($root.'/resources/updates/installed-manifest.sig');
            if ($raw === null || $signature === null || ! hash_equals((string) ($run['manifest_raw'] ?? ''), $raw) || ! hash_equals((string) ($run['signature_raw'] ?? ''), $signature)) {
                return $run;
            }
            $manifest = $this->verifier->verify($raw, $signature, config('updates.trusted_keys', []));
            if ($manifest->data['version'] !== $run['version'] || $manifest->hash !== $run['manifest_hash']) {
                return $run;
            }
            $inventory = $manifest->distribution('source')['files'];
            $descriptor = 'resources/updates/core-version.json';
            if (! isset($inventory[$descriptor]) || $this->digest($root, $descriptor) !== $inventory[$descriptor]) {
                return $run;
            }
            $decoded = json_decode($this->read($root.'/'.$descriptor) ?? '', true);
            if (! is_array($decoded) || ($decoded['version'] ?? null) !== $run['version']) {
                return $run;
            }
            $preserved = $run['preserved_hashes'] ?? [];
            foreach ($run['preserved'] ?? [] as $path) {
                if (! array_key_exists($path, $preserved)) {
                    return $run;
                }
            }
            foreach ($inventory as $path => $expected) {
                $expected = array_key_exists($path, $preserved) ? $preserved[$path] : $expected;
                if ($this->digest($root, $path) !== $expected) {
                    return $run;
                }
            }
            foreach ($run['conflicts'] ?? [] as $conflict) {
                if ($this->digest($root, $conflict['path']) !== ($inventory[$conflict['path']] ?? null)) {
                    return $run;
                }
            }
            foreach ($run['plan'] ?? [] as $entry) {
                if ($entry['new_hash'] === null && $this->digest($root, $entry['path']) !== null) {
                    return $run;
                }
            }
            if ($this->migrations->pending($manifest->data) !== []) {
                return $run;
            }
            $identity = ['version' => $manifest->data['version'], 'source_sha' => $manifest->data['source_sha'], 'distribution' => 'source', 'files' => $inventory, 'manifest_raw' => $raw, 'signature_raw' => $signature, 'customizations' => array_keys($preserved), 'managed_run_id' => $runId, 'adopted_at' => gmdate(DATE_ATOM)];
            $this->store->atomic($this->store->root().'/identity.json', json_encode($identity, JSON_THROW_ON_ERROR));
            $run['status'] = 'completed';
            $run['completed_at'] = gmdate(DATE_ATOM);
            $run['message'] = 'El despliegue del núcleo firmado está instalado; sus personalizaciones conservadas y migraciones se han comprobado.';

            return $this->store->save($runId, $run);
        });
    }

    private function read(string $path): ?string
    {
        $size = is_file($path) && ! is_link($path) ? filesize($path) : false;
        if ($size === false || $size > 16777216) {
            return null;
        }
        $contents = file_get_contents($path);

        return $contents === false ? null : $contents;
    }

    private function digest(string $root, string $path): ?string
    {
        (new UpdatePathPolicy)->requireAllowed($path);
        $full = $root.'/'.$path;
        if (is_link($full)) {
            throw new \DomainException('El despliegue contiene un enlace en un archivo del núcleo.');
        }
        if (! file_exists($full)) {
            return null;
        }
        $real = realpath($full);
        if ($real === false || ! str_starts_with($real, $root.'/') || ! is_file($real)) {
            throw new \DomainException('Un archivo del despliegue está fuera de la raíz del núcleo.');
        }
        $digest = hash_file('sha256', $real);

        return $digest === false ? null : $digest;
    }
}
