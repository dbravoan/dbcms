<?php

declare(strict_types=1);

namespace App\Services\Install;

use App\Contracts\Services\InstallationProvisioningServiceInterface;
use App\DTOs\Install\InstallationResultDTO;
use App\Models\Role;
use App\Services\Security\PassportKeyPermissionService;
use Database\Seeders\CmsFoundationSeeder;
use Database\Seeders\PlatformShowcaseSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Laravel\Passport\ClientRepository;
use Symfony\Component\Console\Output\OutputInterface;

final class InstallationProvisioningService implements InstallationProvisioningServiceInterface
{
    public function __construct(private readonly PassportKeyPermissionService $keys, private readonly ClientRepository $clients) {}

    public function provision(bool $withDemo = false, ?OutputInterface $output = null): InstallationResultDTO
    {
        $logs = [];
        try {
            $this->command('migrate', ['--force' => true], $output);
            $logs[] = 'Tablas relacionales del núcleo migradas correctamente.';
            foreach (File::directories(base_path('modules')) as $module) {
                $path = $module.'/Database/Migrations';
                if (File::isDirectory($path)) {
                    $this->command('migrate', ['--path' => substr($path, strlen(base_path()) + 1), '--force' => true], $output);
                    $logs[] = 'Módulo ['.basename($module).']: esquemas y tablas migrados.';
                }
            }
            $this->infrastructure($output);
            $logs[] = 'Claves OAuth2, cliente de acceso personal y almacenamiento preparados.';
            $this->command('db:seed', ['--class' => $withDemo ? PlatformShowcaseSeeder::class : CmsFoundationSeeder::class, '--force' => true], $output);
            DB::transaction(fn () => Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Administrador', 'description' => 'Acceso administrativo total']));
            $logs[] = $withDemo ? 'Datos de demostración solicitados inicializados.' : 'Estructura base inicializada sin contenido de demostración.';

            return new InstallationResultDTO(true, 'Aprovisionamiento completado con éxito.', $logs);
        } catch (\Throwable $error) {
            Log::error('Installer provisioning failed', ['exception' => $error::class]);

            return new InstallationResultDTO(false, 'Error durante el aprovisionamiento. Revisa los logs del servidor.', $logs);
        }
    }

    private function infrastructure(?OutputInterface $output): void
    {
        $privateExists = File::isFile(storage_path('oauth-private.key'));
        $publicExists = File::isFile(storage_path('oauth-public.key'));
        if ($privateExists !== $publicExists) {
            throw new \RuntimeException('The existing OAuth key pair is incomplete; it will not be overwritten.');
        }
        if (! $privateExists) {
            $this->command('passport:keys', ['--force' => true], $output);
            if (! File::isFile(storage_path('oauth-private.key')) || ! File::isFile(storage_path('oauth-public.key'))) {
                throw new \RuntimeException('OAuth keys were not created.');
            }
        }
        $this->verifyOAuthKeyPair();
        $this->keys->harden(storage_path());
        if ((fileperms(storage_path('oauth-private.key')) & 0777) !== 0600) {
            throw new \RuntimeException('The private OAuth key permissions are unsafe.');
        }
        $provider = (string) config('auth.guards.api.provider', 'users');
        try {
            $this->clients->personalAccessClient($provider);
        } catch (\RuntimeException) {
            DB::transaction(fn () => $this->clients->createPersonalAccessGrantClient('dbCMS Personal Access Client', $provider));
        }
        if (! File::exists(public_path('storage')) && ! is_link(public_path('storage'))) {
            $this->command('storage:link', [], $output);
            $this->verifyStorageLink(public_path('storage'));
        }
    }

    private function verifyOAuthKeyPair(): void
    {
        $private = @openssl_pkey_get_private(File::get(storage_path('oauth-private.key')));
        $public = @openssl_pkey_get_public(File::get(storage_path('oauth-public.key')));
        $privateDetails = $private === false ? false : openssl_pkey_get_details($private);
        $publicDetails = $public === false ? false : openssl_pkey_get_details($public);
        if ($privateDetails === false || $publicDetails === false || $privateDetails['type'] !== OPENSSL_KEYTYPE_RSA || $privateDetails['bits'] < 2048 || ! hash_equals($privateDetails['key'], $publicDetails['key'])) {
            throw new \RuntimeException('The existing OAuth key pair is invalid; it will not be overwritten.');
        }
    }

    private function verifyStorageLink(string $path): void
    {
        clearstatcache(true, $path);
        if (! is_link($path)) {
            throw new \RuntimeException('Public storage link was not created.');
        }
    }

    /** @param array<string, mixed> $arguments */
    private function command(string $name, array $arguments, ?OutputInterface $output): void
    {
        if (Artisan::call($name, $arguments, $output) !== 0) {
            throw new \RuntimeException('Installation command failed: '.$name);
        }
    }
}
