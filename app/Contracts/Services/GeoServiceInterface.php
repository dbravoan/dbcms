<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Models\Market;

interface GeoServiceInterface
{
    /**
     * Generates standard /llms.txt content summarizing site capabilities for AI crawlers.
     */
    public function generateLlmsTxt(?Market $market = null, ?string $locale = null): string;

    /**
     * Generates extended /llms-full.txt content with full semantic corpus.
     */
    public function generateLlmsFullTxt(?Market $market = null, ?string $locale = null): string;

    /**
     * Chunks arbitrary text into token-bounded semantic blocks for RAG vector indexing.
     *
     * @return array<int, array{index: int, token_count: int, content: string}>
     */
    public function chunkContentForEmbeddings(string $content, int $chunkSize = 512): array;
}
