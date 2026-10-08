<?php

declare(strict_types=1);

namespace App\Services\Geo;

use InvalidArgumentException;

class ContextChunkingService
{
    /**
     * Splits text into character-bounded blocks. token_count is only a chars/4
     * estimate; it cannot enforce the token limit of any particular model.
     *
     * @return array<int, array{index: int, token_count: int, content: string}>
     */
    public function chunk(string $text, int $maxTokens = 512, int $overlapTokens = 64): array
    {
        if ($maxTokens < 1 || $overlapTokens < 0) {
            throw new InvalidArgumentException('Chunk budget must be positive and overlap non-negative.');
        }
        $maxChars = $maxTokens * 4;
        $overlapChars = min($overlapTokens * 4, intdiv($maxChars, 2));
        $text = trim($text);
        $length = mb_strlen($text);
        if ($length === 0) {
            return [];
        }
        $chunks = [];
        $offset = 0;
        while ($offset < $length) {
            $window = mb_substr($text, $offset, $maxChars);
            $windowLength = mb_strlen($window);
            if ($offset + $windowLength < $length) {
                // Prefer a paragraph or word boundary in the latter half.
                $boundary = mb_strrpos($window, "\n\n");
                if ($boundary === false || $boundary < intdiv($maxChars, 2)) {
                    $boundary = mb_strrpos($window, ' ');
                }
                if ($boundary !== false && $boundary > intdiv($maxChars, 2)) {
                    $windowLength = $boundary;
                    $window = mb_substr($window, 0, $windowLength);
                }
            }
            $chunks[] = [
                'index' => count($chunks),
                'token_count' => (int) ceil(mb_strlen($window) / 4),
                'content' => $window,
            ];
            if ($offset + $windowLength >= $length) {
                break;
            }
            $offset += max(1, $windowLength - $overlapChars);
        }

        return $chunks;
    }
}
