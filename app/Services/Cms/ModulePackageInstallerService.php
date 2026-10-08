<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\Contracts\Services\ModulePackageInstallerInterface;
use App\DTOs\Modules\InstalledModulePackageDTO;
use App\DTOs\Modules\ModulePackageDTO;
use App\Models\CmsModule;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use ZipArchive;

/** Archive hardening for trusted administrator code; this is not a PHP sandbox. */
final class ModulePackageInstallerService implements ModulePackageInstallerInterface
{
    public function install(ModulePackageDTO $package): InstalledModulePackageDTO
    {
        $actualBytes = is_file($package->path) ? filesize($package->path) : false;
        if ($actualBytes === false || $actualBytes !== $package->bytes || $actualBytes < 1 || $actualBytes > $this->limit('max_upload_bytes', 52428800)) {
            $this->invalid('The module ZIP size is invalid or exceeds the limit.');
        }
        $zip = new ZipArchive;
        if ($zip->open($package->path, ZipArchive::CHECKCONS) !== true) {
            $this->invalid('The module ZIP is invalid or damaged.');
        }
        $root = (string) config('cms.module_packages.root', base_path('modules'));
        File::ensureDirectoryExists($root, 0755);
        $stagingRoot = $root.'/.package-staging';
        if (is_link($stagingRoot) || is_link($root)) {
            $zip->close();
            $this->invalid('Module installation directories cannot be symbolic links.');
        }
        File::ensureDirectoryExists($stagingRoot, 0700);
        chmod($stagingRoot, 0700);
        $stage = $stagingRoot.'/'.bin2hex(random_bytes(16));
        $lock = null;
        try {
            $entries = $this->inspect($zip);
            $folder = explode('/', array_key_first($entries) ?? '')[0];
            $manifest = $this->manifest($zip, $folder, $entries);
            $target = $root.'/'.$folder;
            $lockPath = $root.'/.package-install.lock';
            if (is_link($lockPath)) {
                $this->invalid('Module installation lock cannot be a symbolic link.');
            }
            $lock = fopen($lockPath, 'c');
            if ($lock === false || ! flock($lock, LOCK_EX)) {
                throw new \RuntimeException('Could not lock module installation.');
            }
            chmod($lockPath, 0600);
            if (file_exists($target) || is_link($target) || CmsModule::where('identifier', $manifest['identifier'])->exists()) {
                $this->invalid('A module with this directory or identifier already exists; nothing was overwritten.');
            }
            File::ensureDirectoryExists($stage, 0700);
            $this->extract($zip, $entries, $stage);
            if (! rename($stage.'/'.$folder, $target)) {
                throw new \RuntimeException('Could not atomically install the module package.');
            }

            return new InstalledModulePackageDTO($target, $manifest);
        } finally {
            $zip->close();
            if (is_resource($lock)) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
            File::deleteDirectory($stage);
        }
    }

    public function rollback(InstalledModulePackageDTO $package): void
    {
        $root = realpath((string) config('cms.module_packages.root', base_path('modules')));
        $directory = realpath($package->directory);
        if ($root === false || $directory === false || dirname($directory) !== $root || is_link($package->directory)) {
            throw new \RuntimeException('Refusing rollback outside the module directory.');
        }
        if (! File::deleteDirectory($directory)) {
            throw new \RuntimeException('Failed to remove the new module after registration failure.');
        }
    }

    /** @return array<string, array{index:int,size:int,directory:bool,crc:int}> */
    private function inspect(ZipArchive $zip): array
    {
        if ($zip->numFiles < 2 || $zip->numFiles > $this->limit('max_entries', 2000)) {
            $this->invalid('The module ZIP entry count exceeds the limit.');
        }
        $entries = [];
        $caseNames = [];
        $folders = [];
        $expanded = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat === false) {
                $this->invalid('Cannot inspect a ZIP entry.');
            }
            $name = $stat['name'];
            $directory = str_ends_with($name, '/');
            $clean = $directory ? substr($name, 0, -1) : $name;
            if ($clean === '' || strlen($clean) > 240 || preg_match('/[\\\\:\x00-\x1f\x7f]/', $clean) || str_starts_with($clean, '/') || str_contains($clean, '//')) {
                $this->invalid('The module ZIP contains an unsafe path.');
            }
            $parts = explode('/', $clean);
            foreach ($parts as $part) {
                if ($part === '' || $part === '.' || $part === '..' || str_starts_with($part, '.') || preg_match('/[. ]$/', $part) || preg_match('/^(con|prn|aux|nul|com[1-9]|lpt[1-9])(?:\.|$)/i', $part)) {
                    $this->invalid('The module ZIP contains an unsafe path component.');
                }
            }
            $folder = $parts[0];
            if (! preg_match('/^[A-Z][A-Za-z0-9]{0,63}$/', $folder) || (! $directory && count($parts) < 2)) {
                $this->invalid('The module ZIP must contain one named module directory.');
            }
            $folders[$folder] = true;
            if (count($folders) !== 1 || isset($caseNames[strtolower($clean)])) {
                $this->invalid('The module ZIP contains multiple roots or duplicate paths.');
            }
            $caseNames[strtolower($clean)] = true;
            $opsys = 0;
            $attributes = 0;
            if (! $zip->getExternalAttributesIndex($i, $opsys, $attributes)) {
                $this->invalid('Cannot inspect ZIP entry attributes.');
            }
            $type = ($attributes >> 16) & 0170000;
            if ($opsys === ZipArchive::OPSYS_UNIX && $type !== 0 && $type !== ($directory ? 0040000 : 0100000)) {
                $this->invalid('Links and special files are prohibited in module packages.');
            }
            $size = $stat['size'];
            $compressed = $stat['comp_size'];
            $expanded += $size;
            if (($stat['encryption_method'] ?? 0) !== 0 || $size < 0 || ($directory && $size !== 0) || $expanded > $this->limit('max_expanded_bytes', 209715200) || $size > $this->limit('max_file_bytes', 52428800) || $size / max(1, $compressed) > $this->limit('max_compression_ratio', 200)) {
                $this->invalid('The module ZIP is encrypted or exceeds extraction limits.');
            }
            $entries[$clean] = ['index' => $i, 'size' => $size, 'directory' => $directory, 'crc' => $stat['crc']];
        }
        foreach ($entries as $name => $entry) {
            $parent = dirname($name);
            while ($parent !== '.') {
                if (isset($entries[$parent]) && ! $entries[$parent]['directory']) {
                    $this->invalid('A ZIP path is both a file and a directory.');
                }
                $parent = dirname($parent);
            }
        }

        return $entries;
    }

    /** @param array<string, array{index:int,size:int,directory:bool,crc:int}> $entries
     * @return array<string, mixed>
     */
    private function manifest(ZipArchive $zip, string $folder, array $entries): array
    {
        $path = $folder.'/module.json';
        if (! isset($entries[$path]) || $entries[$path]['directory'] || $entries[$path]['size'] > 65536) {
            $this->invalid('A module manifest is required.');
        }
        $raw = $zip->getFromIndex($entries[$path]['index']);
        $manifest = is_string($raw) ? json_decode($raw, true) : null;
        if (! is_array($manifest) || ! is_string($manifest['identifier'] ?? null) || ! preg_match('/^[a-z][a-z0-9_]{0,63}$/', $manifest['identifier']) || ! is_string($manifest['name'] ?? null) || trim($manifest['name']) === '' || strlen($manifest['name']) > 100 || ! is_string($manifest['version'] ?? null) || ! preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $manifest['version']) || ! is_string($manifest['provider'] ?? null)) {
            $this->invalid('The module manifest has invalid required fields.');
        }
        $prefix = 'Modules\\'.$folder.'\\';
        $provider = $manifest['provider'];
        if (! str_starts_with($provider, $prefix) || ! preg_match('/^Modules\\\\[A-Za-z][A-Za-z0-9]*(?:\\\\[A-Za-z][A-Za-z0-9]*)+$/', $provider)) {
            $this->invalid('The module provider must belong to its module namespace.');
        }
        $providerPath = str_replace('\\', '/', substr($provider, strlen('Modules\\'))).'.php';
        if (! isset($entries[$providerPath]) || $entries[$providerPath]['directory']) {
            $this->invalid('The declared module provider file is missing.');
        }
        if (! empty($manifest['is_core']) || (isset($manifest['description']) && (! is_string($manifest['description']) || strlen($manifest['description']) > 5000))) {
            $this->invalid('Uploaded modules cannot claim core status and must contain valid metadata.');
        }

        return $manifest;
    }

    /** @param array<string, array{index:int,size:int,directory:bool,crc:int}> $entries */
    private function extract(ZipArchive $zip, array $entries, string $stage): void
    {
        foreach ($entries as $name => $entry) {
            $destination = $stage.'/'.$name;
            if ($entry['directory']) {
                File::ensureDirectoryExists($destination, 0755);

                continue;
            }
            File::ensureDirectoryExists(dirname($destination), 0755);
            $source = $zip->getStreamIndex($entry['index']);
            $output = fopen($destination, 'xb');
            if ($source === false || $output === false) {
                if (is_resource($source)) {
                    fclose($source);
                }
                if (is_resource($output)) {
                    fclose($output);
                }
                throw new \RuntimeException('Cannot extract a module file.');
            }
            try {
                $bytes = stream_copy_to_stream($source, $output, $entry['size'] + 1);
                if ($bytes !== $entry['size']) {
                    $this->invalid('A module file size does not match the archive metadata.');
                }
            } finally {
                fclose($source);
                fclose($output);
            }
            if (hash_file('crc32b', $destination) !== sprintf('%08x', $entry['crc'])) {
                $this->invalid('A module file failed its checksum.');
            }
            chmod($destination, 0644);
        }
    }

    private function limit(string $name, int $default): int
    {
        return max(1, (int) config('cms.module_packages.'.$name, $default));
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['module_zip' => $message]);
    }
}
