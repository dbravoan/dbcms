<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\Contracts\Services\ModulePackageInstallerInterface;
use App\Core\Extensions\SafeExtensionArchive;
use App\Core\Modules\ModuleManifestGraph;
use App\DTOs\Modules\InstalledModulePackageDTO;
use App\DTOs\Modules\ModulePackageDTO;
use App\Models\CmsModule;
use DomainException;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

final class ModulePackageInstallerService implements ModulePackageInstallerInterface
{
    public function __construct(private readonly SafeExtensionArchive $archives) {}

    public function install(ModulePackageDTO $package): InstalledModulePackageDTO
    {
        $result = $this->archives->install($package->path, $package->bytes, $this->root(), 'module.json', function (array $manifest, string $folder, array $entries): void {
            try {
                ModuleManifestGraph::validateManifest($manifest);
            } catch (DomainException $error) {
                throw ValidationException::withMessages(['module_zip' => $error->getMessage()]);
            }
            $provider = (string) $manifest['provider'];
            $providerPath = str_replace('\\', '/', substr($provider, strlen('Modules\\'))).'.php';
            if (! str_starts_with($provider, 'Modules\\'.$folder.'\\') || ! preg_match('/^Modules\\\\[A-Za-z][A-Za-z0-9]*(?:\\\\[A-Za-z][A-Za-z0-9]*)+$/', $provider) || ! isset($entries[$providerPath]) || $entries[$providerPath]['directory'] || ! empty($manifest['is_core'])) {
                throw ValidationException::withMessages(['module_zip' => 'The provider must exist inside its module namespace; uploaded modules cannot claim core status.']);
            }
        }, fn (array $manifest): bool => $this->identifierExists((string) $manifest['identifier']));

        return new InstalledModulePackageDTO($result['directory'], $result['manifest']);
    }

    public function rollback(InstalledModulePackageDTO $package): void
    {
        $this->archives->remove($package->directory, $this->root());
    }

    private function identifierExists(string $identifier): bool
    {
        if (CmsModule::where('identifier', $identifier)->exists()) {
            return true;
        }
        foreach (File::directories($this->root()) as $directory) {
            if (str_starts_with(basename($directory), '.') || ! File::isFile($directory.'/module.json')) {
                continue;
            }
            $manifest = json_decode(File::get($directory.'/module.json'), true);
            if (is_array($manifest) && ($manifest['identifier'] ?? null) === $identifier) {
                return true;
            }
        }

        return false;
    }

    private function root(): string
    {
        return (string) config('cms.module_packages.root', base_path('modules'));
    }
}
