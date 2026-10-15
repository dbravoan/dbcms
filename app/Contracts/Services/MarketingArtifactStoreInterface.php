<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\DTOs\Marketing\ArtifactDescriptor;

interface MarketingArtifactStoreInterface
{
    /** @param resource $stream */
    public function writeStream($stream, string $owner, string $filename, ?string $expectedMime = null): ArtifactDescriptor;

    /** @return resource */
    public function readStream(ArtifactDescriptor $artifact);

    public function absolutePath(ArtifactDescriptor $artifact): string;

    public function verify(ArtifactDescriptor $artifact): bool;

    public function copy(ArtifactDescriptor $artifact, string $owner): ArtifactDescriptor;

    public function describe(string $path): ArtifactDescriptor;
}
