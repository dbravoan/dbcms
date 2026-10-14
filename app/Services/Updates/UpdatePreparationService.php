<?php

declare(strict_types=1);

namespace App\Services\Updates;

use App\Contracts\Services\UpdateDeploymentServiceInterface;
use App\Contracts\Services\UpdatePreparationServiceInterface;
use App\Core\Updates\InventoryComparator;
use App\Core\Updates\ReleaseManifest;
use App\Core\Updates\SafeUpdateArchive;
use App\Core\Updates\SignedManifestVerifier;
use App\Core\Updates\UpdateRunStore;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;

final class UpdatePreparationService implements UpdatePreparationServiceInterface
{
    public function __construct(private readonly GithubReleaseGateway $github, private readonly SignedManifestVerifier $verifier, private readonly UpdateRunStore $store, private readonly SafeUpdateArchive $archives, private readonly InventoryComparator $comparator, private readonly UpdateDeploymentServiceInterface $deployment) {}

    /**
     * @return array<string,mixed> */
    public function prepare(string $version, int $actorId): array
    {
        $this->authorize($actorId);
        $identity = $this->verifiedIdentity();
        if (version_compare($version, $identity['version'], '<=')) {
            throw new \DomainException('Solo se pueden preparar versiones posteriores al núcleo instalado.');
        }
        [$manifest, $raw, $signature, $release] = $this->fetchManifest($version);
        if (! in_array($identity['version'], $manifest->data['supported_from'], true)) {
            throw new \DomainException('Esta release no admite actualizar desde la versión instalada.');
        }
        if (version_compare(PHP_VERSION, $manifest->data['min_php'], '<')) {
            throw new \DomainException('La actualización requiere una versión de PHP posterior.');
        }
        foreach ($manifest->data['extensions'] as $extension) {
            if (! extension_loaded($extension)) {
                throw new \DomainException('Falta la extensión PHP: '.$extension);
            }
        }
        $root = $this->root();
        $managed = $this->deployment->isManaged($root) || $identity['distribution'] !== 'runtime' || version_compare(explode('.', $version)[0], explode('.', $identity['version'])[0], '>') || ! $manifest->data['rollback_compatible'];
        $distribution = $managed ? 'source' : $identity['distribution'];
        // A runtime baseline cannot compare a source-only vendor inventory. Use the
        // official source baseline for review exports and leave runtime untouched.
        $baseline = $this->baselineFiles($identity, $distribution);
        $target = $manifest->distribution($distribution);
        foreach (array_keys($baseline) as $path) {
            if (str_starts_with($path, 'resources/updates/trusted-keys/') && ! isset($target['files'][$path])) {
                throw new \DomainException('La actualización no puede eliminar claves de confianza históricas.');
            }
        }
        $local = $this->local(array_unique([...array_keys($baseline), ...array_keys($target['files'])]));
        $comparison = $this->comparator->compare($baseline, $local, $target['files']);
        if ($distribution === 'runtime') {
            foreach (['composer.json', 'composer.lock', 'package.json', 'package-lock.json', 'vite.config.js', 'vite.config.ts'] as $dependency) {
                if (isset($baseline[$dependency]) && ($local[$dependency] ?? null) !== $baseline[$dependency]) {
                    $managed = true;
                    $distribution = 'source';
                    $baseline = $this->baselineFiles($identity, 'source');
                    $target = $manifest->distribution('source');
                    $local = $this->local(array_unique([...array_keys($baseline), ...array_keys($target['files'])]));
                    $comparison = $this->comparator->compare($baseline, $local, $target['files']);
                    break;
                }
            }
        }
        $preservedHashes = [];
        foreach ($comparison['preserved'] as $path) {
            $preservedHashes[$path] = $local[$path] ?? null;
        }
        $run = $this->store->create([
            'actor_id' => $actorId, 'status' => $comparison['conflicts'] !== [] ? 'conflict' : ($managed ? 'managed' : 'prepared'),
            'mode' => $managed ? 'managed' : 'runtime', 'version' => $version, 'distribution' => $distribution,
            'manifest_hash' => $manifest->hash, 'manifest' => $manifest->data, 'manifest_raw' => $raw, 'signature_raw' => $signature,
            'target_identity' => ['version' => $version, 'source_sha' => $manifest->data['source_sha'], 'distribution' => $distribution, 'files' => $target['files'], 'manifest_raw' => $raw, 'signature_raw' => $signature],
            ...$comparison, 'preserved_hashes' => $preservedHashes, 'plan_hash' => hash('sha256', json_encode($comparison, JSON_THROW_ON_ERROR)),
        ]);
        $directory = $this->store->directory($run['id']);
        try {
            $asset = $this->github->asset($release, $target['asset']);
            if ($target['size'] > (int) config('updates.max_archive_bytes', 536870912)) {
                throw new \DomainException('El paquete excede el tamaño admitido.');
            }
            $archive = $directory.'/package.zip';
            $this->github->download($asset['browser_download_url'], $archive, $target['size']);
            if (filesize($archive) !== $target['size'] || hash_file('sha256', $archive) !== $target['sha256']) {
                throw new \DomainException('El paquete no coincide con la firma de la release.');
            }
            $this->archives->extract($archive, $directory.'/stage', $target['files']);
            $run['stage_path'] = $directory.'/stage';
        } catch (\Throwable $e) {
            $run['status'] = 'failed';
            $run['error'] = $e->getMessage();
            $this->store->save($run['id'], $run);
            throw $e;
        }

        return $this->publicRun($this->store->save($run['id'], $run));
    }

    /**
     * @return array<string,mixed> */
    public function adopt(string $version, string $distribution, int $actorId): array
    {
        $this->authorize($actorId);
        if ($this->store->identity() !== null) {
            throw new \DomainException('La instalación ya tiene un inventario oficial adoptado.');
        }
        try {
            [$manifest, $raw, $signature] = $this->fetchManifest($version);
        } catch (\DomainException $e) {
            $latest = $this->github->listStable()[0] ?? throw $e;
            [$manifest, $raw, $signature] = $this->fetchManifest($version, $latest, 'baseline-v'.$version.'-manifest');
        }
        $files = $manifest->distribution($distribution)['files'];
        $local = $this->local(array_keys($files));
        $conflicts = [];
        foreach ($files as $path => $hash) {
            if (($local[$path] ?? null) !== $hash) {
                $conflicts[] = ['path' => $path, 'reason' => 'differs_from_official_baseline'];
            }
        }
        // Adoption records original signed hashes, never current personalized hashes.
        $identity = ['version' => $version, 'source_sha' => $manifest->data['source_sha'], 'distribution' => $distribution, 'files' => $files, 'manifest_raw' => $raw, 'signature_raw' => $signature, 'adopted_by' => $actorId, 'adopted_at' => gmdate(DATE_ATOM), 'customizations' => $conflicts];
        $identity = $this->recordInitialIdentity($identity);

        return array_intersect_key($identity, array_flip(['version', 'source_sha', 'distribution', 'adopted_at', 'customizations']));
    }

    public function proposal(string $runId, int $actorId): string
    {
        $this->authorize($actorId);

        return $this->store->withLock($runId, function (array $run) use ($runId, $actorId): string {
            if ($run['actor_id'] !== $actorId || ! in_array($run['status'], ['prepared', 'managed', 'conflict'], true)) {
                throw new \DomainException('La propuesta no está disponible para este actor.');
            }
            $path = $this->store->directory($runId).'/proposal.zip';
            $zip = new \ZipArchive;
            if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('No se puede crear la propuesta.');
            }
            try {
                $zip->addFromString('plan.json', json_encode(['version' => $run['version'], 'manifest_hash' => $run['manifest_hash'], 'plan_hash' => $run['plan_hash'], 'plan' => $run['plan'], 'conflicts' => $run['conflicts'], 'preserved' => $run['preserved']], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
                $zip->addFromString('README.txt', "Propuesta de actualización dbCMS\n\nRevise plan.json y sus conflictos. Los archivos bajo files/ son únicamente propuestas. Incorpórelos en una rama; revise eliminaciones y personalizaciones; resuelva dependencias, ejecute CI y respaldo antes del despliegue. No copie vendor/ sobre dependencias personalizadas. Ejecute solo migraciones pendientes del núcleo y módulos instalados. Nunca ejecute el instalador ni seeders. Esta propuesta no cambia su repositorio.\n");
                $zip->addFromString('files/resources/updates/installed-manifest.json', $run['manifest_raw']);
                $zip->addFromString('files/resources/updates/installed-manifest.sig', $run['signature_raw']);
                foreach ($run['plan'] as $operation) {
                    if ($operation['new_hash'] !== null) {
                        $source = $run['stage_path'].'/'.$operation['path'];
                        if (hash_file('sha256', $source) !== $operation['new_hash']) {
                            throw new \DomainException('La preparación cambió desde su verificación.');
                        }
                        $zip->addFile($source, 'files/'.$operation['path']);
                    }
                }
                foreach ($run['conflicts'] as $conflict) {
                    $source = $run['stage_path'].'/'.$conflict['path'];
                    if (is_file($source)) {
                        if (hash_file('sha256', $source) !== ($run['manifest']['distributions'][$run['distribution']]['files'][$conflict['path']] ?? null)) {
                            throw new \DomainException('Un archivo en conflicto cambió desde su verificación.');
                        }
                        $zip->addFile($source, 'conflicts/'.$conflict['path']);
                    }
                }
            } finally {
                $zip->close();
            }
            chmod($path, 0600);

            return $path;
        });
    }

    /**
     * @return array<string,mixed> */
    private function verifiedIdentity(): array
    {
        $identity = $this->store->identity();
        if ($identity === null && is_file($this->root().'/resources/updates/installed-manifest.json') && is_file($this->root().'/resources/updates/installed-manifest.sig')) {
            $raw = file_get_contents($this->root().'/resources/updates/installed-manifest.json');
            $signature = file_get_contents($this->root().'/resources/updates/installed-manifest.sig');
            if ($raw === false || $signature === false) {
                throw new \DomainException('No se puede leer la identidad del paquete.');
            }
            $manifest = $this->verifier->verify($raw, $signature, $this->keys());
            $files = $manifest->distribution('runtime')['files'];
            if ($this->local(array_keys($files)) !== $files) {
                // Order independent comparison; do not silently mark customized
                // files as official just because this is a recent package.
                $local = $this->local(array_keys($files));
                foreach ($files as $path => $hash) {
                    if (($local[$path] ?? null) !== $hash) {
                        throw new \DomainException('El paquete difiere de su inventario oficial; incorpore la versión con revisión.');
                    }
                }
            }
            $identity = ['version' => $manifest->data['version'], 'source_sha' => $manifest->data['source_sha'], 'distribution' => 'runtime', 'files' => $files, 'manifest_raw' => $raw, 'signature_raw' => $signature];
            $identity = $this->recordInitialIdentity($identity);
        }
        if ($identity === null || ! is_string($identity['manifest_raw'] ?? null) || ! is_string($identity['signature_raw'] ?? null)) {
            throw new \DomainException('Debe incorporar el inventario oficial de esta instalación antes de actualizar.');
        }
        $manifest = $this->verifier->verify($identity['manifest_raw'], $identity['signature_raw'], $this->keys());
        if (($identity['version'] ?? null) !== $manifest->data['version'] || ($identity['source_sha'] ?? null) !== $manifest->data['source_sha']) {
            throw new \DomainException('La identidad del núcleo no coincide con su manifiesto firmado.');
        }
        $identity['files'] = $manifest->distribution($identity['distribution'])['files'];

        return $identity;
    }

    /**
     * @param array<string,mixed>|null $release
     * @return array{ReleaseManifest,string,string,array<string,mixed>} */
    private function fetchManifest(string $version, ?array $release = null, string $basename = 'manifest'): array
    {
        $release ??= $this->github->release($version);
        $raw = $this->github->contents($this->github->asset($release, $basename.'.json')['browser_download_url'], 16777216);
        $signature = $this->github->contents($this->github->asset($release, $basename.'.sig')['browser_download_url'], 16384);
        $manifest = $this->verifier->verify($raw, $signature, $this->keys());
        if ($manifest->data['version'] !== $version) {
            throw new \DomainException('La versión firmada no coincide con la solicitada.');
        }

        return [$manifest, $raw, $signature, $release];
    }

    /**
     * @return array<string,string> */
    private function keys(): array
    {
        return config('updates.trusted_keys', []);
    }

    private function root(): string
    {
        return rtrim((string) config('updates.root_path', base_path()), '/');
    }

    /**
     * @param array<array-key,int|string> $paths
     * @return array<string,string> */
    private function local(array $paths): array
    {
        $files = [];
        foreach ($paths as $path) {
            $path = (string) $path;
            $full = $this->root().'/'.$path;
            $linked = false;
            for ($ancestor = $full; strlen($ancestor) > strlen($this->root()); $ancestor = dirname($ancestor)) {
                if (is_link($ancestor)) {
                    $linked = true;
                    break;
                }
            }
            if ($linked) {
                $files[$path] = 'symlink';
            } elseif (is_file($full)) {
                $files[$path] = hash_file('sha256', $full) ?: 'unreadable';
            } elseif (file_exists($full)) {
                $files[$path] = 'non_regular_file';
            }
        }

        return $files;
    }

    /**
     * @param array<string,mixed> $identity
     * @return array<string,string> */
    private function baselineFiles(array $identity, string $distribution): array
    {
        return $this->verifier->verify($identity['manifest_raw'], $identity['signature_raw'], $this->keys())->distribution($distribution)['files'];
    }

    /**
     * @param array<string,mixed> $run
     * @return array<string,mixed> */
    private function publicRun(array $run): array
    {
        return array_intersect_key($run, array_flip(['id', 'actor_id', 'status', 'mode', 'version', 'manifest_hash', 'plan_hash', 'conflicts', 'preserved', 'created_at', 'updated_at']));
    }

    private function authorize(int $actorId): void
    {
        $user = User::find($actorId);
        if ($user === null || ! $user->hasPermission('access:admin') || ! $user->hasPermission('manage:updates') || (bool) $user->getAttribute('is_service_account') || (! app()->runningInConsole() && Auth::id() !== $actorId)) {
            throw new AuthorizationException('No puedes preparar esta actualización.');
        }
    }

    /** @param array<string,mixed> $identity
     * @return array<string,mixed>
     */
    private function recordInitialIdentity(array $identity): array
    {
        $path = $this->store->root().'/identity.lock';
        if (is_link($path)) {
            throw new \DomainException('El bloqueo de identidad no es seguro.');
        }
        $handle = fopen($path, 'c');
        if ($handle === false || ! flock($handle, LOCK_EX)) {
            throw new \RuntimeException('No se puede registrar la identidad del núcleo.');
        }
        try {
            chmod($path, 0600);
            $current = $this->store->identity();
            if ($current !== null) {
                if (($current['version'] ?? null) === $identity['version'] && ($current['source_sha'] ?? null) === $identity['source_sha'] && ($current['distribution'] ?? null) === $identity['distribution']) {
                    return $current;
                }
                throw new \DomainException('Otra petición ya ha registrado la identidad del núcleo.');
            }
            $this->store->atomic($this->store->root().'/identity.json', json_encode($identity, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return $identity;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
