<?php

declare(strict_types=1);

namespace App\Services\Marketing;

use App\Contracts\Services\MarketingArtifactStoreInterface;
use App\DTOs\Marketing\ArtifactDescriptor;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

final class MarketingArtifactStore implements MarketingArtifactStoreInterface
{
    private const FORMATS = ['image/jpeg' => ['jpg', 'jpeg'], 'image/png' => ['png'], 'image/webp' => ['webp'], 'video/mp4' => ['mp4'], 'video/quicktime' => ['mov']];

    public function writeStream($stream, string $owner, string $filename, ?string $expectedMime = null): ArtifactDescriptor
    {
        if (! is_resource($stream) || ! preg_match('/^[a-zA-Z0-9_-]+(?:\/[a-zA-Z0-9_-]+){0,3}$/', $owner)) {
            throw new InvalidArgumentException('A valid stream and private artifact owner are required.');
        }
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (! in_array($extension, array_merge(...array_values(self::FORMATS)), true)) {
            throw new InvalidArgumentException('Only supported image and video formats may be imported.');
        }
        $name = substr(Str::slug(pathinfo(basename($filename), PATHINFO_FILENAME)), 0, 100) ?: 'artifact';
        $path = $owner.'/'.Str::uuid().'/'.$name.'.'.$extension;
        $absolute = $this->root().'/'.$path;
        if (! is_dir(dirname($absolute)) && ! mkdir(dirname($absolute), 0700, true) && ! is_dir(dirname($absolute))) {
            throw new RuntimeException('Private artifact storage is unavailable.');
        }
        $destination = fopen($absolute, 'xb');
        if ($destination === false) {
            throw new RuntimeException('Cannot create immutable artifact.');
        }
        try {
            $bytes = stream_copy_to_stream($stream, $destination, (int) config('marketing.max_artifact_bytes', 536870912) + 1);
            fflush($destination);
            if (function_exists('fsync')) {
                fsync($destination);
            }
            fclose($destination);
            chmod($absolute, 0600);
            if ($bytes === false || $bytes < 1 || $bytes > (int) config('marketing.max_artifact_bytes', 536870912)) {
                throw new InvalidArgumentException('Artifact is empty or exceeds the configured size limit.');
            }
            $artifact = $this->describe($path);
            if ($expectedMime !== null && $expectedMime !== $artifact->mimeType) {
                throw new InvalidArgumentException('Artifact bytes do not match the expected MIME type.');
            }

            return $artifact;
        } catch (Throwable $error) {
            if (is_resource($destination)) {
                fclose($destination);
            }
            @unlink($absolute);
            @rmdir(dirname($absolute));
            throw $error;
        }
    }

    public function readStream(ArtifactDescriptor $artifact)
    {
        if (! $this->verify($artifact)) {
            throw new RuntimeException('The immutable artifact is missing or has changed.');
        }
        $stream = fopen($this->absolutePath($artifact), 'rb');
        if ($stream === false) {
            throw new RuntimeException('Private artifact cannot be read.');
        }

        return $stream;
    }

    public function absolutePath(ArtifactDescriptor $artifact): string
    {
        return $this->resolve($artifact->path);
    }

    public function verify(ArtifactDescriptor $artifact): bool
    {
        try {
            $path = $this->absolutePath($artifact);

            return filesize($path) === $artifact->sizeBytes && hash_equals($artifact->sha256, (string) hash_file('sha256', $path));
        } catch (RuntimeException|InvalidArgumentException) {
            return false;
        }
    }

    public function copy(ArtifactDescriptor $artifact, string $owner): ArtifactDescriptor
    {
        $stream = $this->readStream($artifact);
        try {
            return $this->writeStream($stream, $owner, basename($artifact->path), $artifact->mimeType);
        } finally {
            fclose($stream);
        }
    }

    public function describe(string $path): ArtifactDescriptor
    {
        $absolute = $this->resolve($path);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($absolute);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (! is_string($mime) || ! isset(self::FORMATS[$mime]) || ! in_array($extension, self::FORMATS[$mime], true)) {
            throw new InvalidArgumentException('Artifact contents and filename must match an allowed format.');
        }
        $width = $height = null;
        $duration = null;
        $metadata = [];
        if (str_starts_with($mime, 'image/')) {
            $dimensions = @getimagesize($absolute);
            if ($dimensions === false || $dimensions[0] < 1 || $dimensions[1] < 1 || $dimensions[0] * $dimensions[1] > 100000000) {
                throw new InvalidArgumentException('Invalid or oversized image dimensions.');
            }
            [$width, $height] = $dimensions;
        } else {
            $metadata = $this->probe($absolute);
            $width = (int) $metadata['width'];
            $height = (int) $metadata['height'];
            $duration = (float) $metadata['duration'];
        }

        return new ArtifactDescriptor(basename(dirname($path)), $path, (string) hash_file('sha256', $absolute), $mime,
            (int) filesize($absolute), $width, $height, $duration, $metadata);
    }

    private function root(): string
    {
        $installation = (string) config('cms.installation_id');
        if (! preg_match('/^[a-zA-Z0-9_-]{1,100}$/', $installation)) {
            throw new InvalidArgumentException('A safe installation identifier is required for private storage.');
        }
        $root = rtrim((string) (config('marketing.artifact_root') ?: storage_path('app/private/marketing/'.$installation)), '/');
        $public = rtrim(public_path(), '/');
        $ancestor = $root;
        while (! file_exists($ancestor) && dirname($ancestor) !== $ancestor) {
            $ancestor = dirname($ancestor);
        }
        $existing = realpath($ancestor);
        $public = realpath($public) ?: $public;
        if (! str_starts_with($root, '/') || str_contains($root, '/../') || $root === $public || str_starts_with($root, $public.'/')
            || ($existing !== false && ($existing === $public || str_starts_with($existing, $public.'/')))) {
            throw new InvalidArgumentException('Marketing artifacts require private storage outside the web root.');
        }

        return $root;
    }

    private function resolve(string $path): string
    {
        if (! preg_match('/^[a-zA-Z0-9_\/-]+\.[a-z0-9]+$/', $path) || str_starts_with($path, '/') || str_contains($path, '..')) {
            throw new InvalidArgumentException('Artifact path must remain within private storage.');
        }
        $root = realpath($this->root());
        $absolute = realpath($this->root().'/'.$path);
        if ($root === false || $absolute === false || ! str_starts_with($absolute, $root.'/') || ! is_file($absolute)) {
            throw new RuntimeException('Private artifact is missing or outside its installation.');
        }

        return $absolute;
    }

    /** @return array<string, mixed> */
    private function probe(string $path): array
    {
        $process = new Process([(string) config('marketing.ffprobe_binary', 'ffprobe'), '-v', 'error', '-show_entries', 'stream=codec_type,codec_name,width,height:format=duration', '-of', 'json', $path]);
        $process->setTimeout(20);
        $process->run();
        if (! $process->isSuccessful()) {
            throw new RuntimeException('Video validation requires a functioning FFprobe installation.');
        }
        $data = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        $video = null;
        foreach ((array) ($data['streams'] ?? []) as $stream) {
            if (($stream['codec_type'] ?? null) === 'video') {
                $video = $stream;
                break;
            }
        }
        $duration = (float) ($data['format']['duration'] ?? 0);
        if (! is_array($video) || (int) ($video['width'] ?? 0) < 1 || (int) ($video['height'] ?? 0) < 1 || $duration <= 0 || ! is_finite($duration)) {
            throw new InvalidArgumentException('Video does not contain a valid stream and duration.');
        }

        return ['width' => (int) $video['width'], 'height' => (int) $video['height'], 'duration' => $duration, 'video_codec' => (string) ($video['codec_name'] ?? '')];
    }
}
