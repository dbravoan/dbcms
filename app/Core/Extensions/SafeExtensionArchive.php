<?php

declare(strict_types=1);

namespace App\Core\Extensions;

use Closure;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use ZipArchive;

/** Archive hardening for trusted administrator code; this is not a PHP sandbox. */
final class SafeExtensionArchive
{
    /** @param Closure(array<string,mixed>, string, array<string,mixed>):void $validate
     * @param  Closure(array<string,mixed>):bool  $collides
     * @return array{directory:string,manifest:array<string,mixed>}
     */
    public function install(string $path, int $bytes, string $root, string $manifestName, Closure $validate, Closure $collides, ?Closure $verifyExtracted = null): array
    {
        $actualBytes = is_file($path) ? filesize($path) : false;
        if ($actualBytes === false || $actualBytes !== $bytes || $actualBytes < 1 || $actualBytes > $this->limit('max_upload_bytes', 52428800)) {
            $this->invalid('The extension ZIP size is invalid or exceeds the limit.');
        }
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CHECKCONS) !== true) {
            $this->invalid('The extension ZIP is invalid or damaged.');
        }
        File::ensureDirectoryExists($root, 0755);
        $stagingRoot = $root.'/.package-staging';
        if (is_link($stagingRoot) || is_link($root)) {
            $zip->close();
            $this->invalid('Extension installation directories cannot be symbolic links.');
        }
        File::ensureDirectoryExists($stagingRoot, 0700);
        chmod($stagingRoot, 0700);
        $stage = $stagingRoot.'/'.bin2hex(random_bytes(16));
        $lock = null;
        try {
            $entries = $this->inspect($zip);
            $folder = explode('/', array_key_first($entries) ?? '')[0];
            $manifest = $this->manifest($zip, $folder, $entries, $manifestName);
            $validate($manifest, $folder, $entries);
            $target = $root.'/'.$folder;
            $lockPath = $root.'/.package-install.lock';
            if (is_link($lockPath)) {
                $this->invalid('Extension installation lock cannot be a symbolic link.');
            }
            $lock = fopen($lockPath, 'c');
            if ($lock === false || ! flock($lock, LOCK_EX)) {
                throw new \RuntimeException('Could not lock module installation.');
            }
            chmod($lockPath, 0600);
            if (file_exists($target) || is_link($target) || $collides($manifest)) {
                $this->invalid('A module with this directory or identifier already exists; nothing was overwritten.');
            }
            File::ensureDirectoryExists($stage, 0700);
            $this->extract($zip, $entries, $stage);
            if ($verifyExtracted !== null) {
                $verifyExtracted($stage.'/'.$folder, $manifest);
            }
            if (! rename($stage.'/'.$folder, $target)) {
                throw new \RuntimeException('Could not atomically install the extension package.');
            }

            return ['directory' => $target, 'manifest' => $manifest];
        } finally {
            $zip->close();
            if (is_resource($lock)) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
            File::deleteDirectory($stage);
        }
    }

    public function remove(string $directory, string $root): void
    {
        $realRoot = realpath($root);
        $realDirectory = realpath($directory);
        if ($realRoot === false || $realDirectory === false || dirname($realDirectory) !== $realRoot || is_link($directory)) {
            throw new \RuntimeException('Refusing removal outside the extension directory.');
        }
        if (! File::deleteDirectory($realDirectory)) {
            throw new \RuntimeException('Failed to remove extension directory.');
        }
    }

    /** @return array<string, array{index:int,size:int,directory:bool,crc:int}> */
    private function inspect(ZipArchive $zip): array
    {
        if ($zip->numFiles < 2 || $zip->numFiles > $this->limit('max_entries', 2000)) {
            $this->invalid('The extension ZIP entry count exceeds the limit.');
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
                $this->invalid('The extension ZIP contains an unsafe path.');
            }
            $parts = explode('/', $clean);
            foreach ($parts as $part) {
                if ($part === '' || $part === '.' || $part === '..' || str_starts_with($part, '.') || preg_match('/[. ]$/', $part) || preg_match('/^(con|prn|aux|nul|com[1-9]|lpt[1-9])(?:\.|$)/i', $part)) {
                    $this->invalid('The extension ZIP contains an unsafe path component.');
                }
            }
            $folder = $parts[0];
            if (! preg_match('/^[A-Z][A-Za-z0-9]{0,63}$/', $folder) || (! $directory && count($parts) < 2)) {
                $this->invalid('The extension ZIP must contain one named extension directory.');
            }
            $folders[$folder] = true;
            if (count($folders) !== 1 || isset($caseNames[strtolower($clean)])) {
                $this->invalid('The extension ZIP contains multiple roots or duplicate paths.');
            }
            $caseNames[strtolower($clean)] = true;
            $opsys = 0;
            $attributes = 0;
            if (! $zip->getExternalAttributesIndex($i, $opsys, $attributes)) {
                $this->invalid('Cannot inspect ZIP entry attributes.');
            }
            $type = ($attributes >> 16) & 0170000;
            if ($opsys === ZipArchive::OPSYS_UNIX && $type !== 0 && $type !== ($directory ? 0040000 : 0100000)) {
                $this->invalid('Links and special files are prohibited in extension packages.');
            }
            $size = $stat['size'];
            $compressed = $stat['comp_size'];
            $expanded += $size;
            if (($stat['encryption_method'] ?? 0) !== 0 || $size < 0 || ($directory && $size !== 0) || $expanded > $this->limit('max_expanded_bytes', 209715200) || $size > $this->limit('max_file_bytes', 52428800) || $size / max(1, $compressed) > $this->limit('max_compression_ratio', 200)) {
                $this->invalid('The extension ZIP is encrypted or exceeds extraction limits.');
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
    private function manifest(ZipArchive $zip, string $folder, array $entries, string $manifestName): array
    {
        $path = $folder.'/'.$manifestName;
        if (! isset($entries[$path]) || $entries[$path]['directory'] || $entries[$path]['size'] > 65536) {
            $this->invalid('A extension manifest is required.');
        }
        $raw = $zip->getFromIndex($entries[$path]['index']);
        $manifest = is_string($raw) ? json_decode($raw, true) : null;
        if (! is_array($manifest)) {
            $this->invalid('The extension manifest must be a JSON object.');
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
        throw ValidationException::withMessages(['package_zip' => $message]);
    }
}
