<?php

declare(strict_types=1);

namespace Tests\Regression;

use App\Services\Geo\ContextChunkingService;
use PHPUnit\Framework\TestCase;

final class ContextChunkingBoundsTest extends TestCase
{
    public function test_long_paragraphs_and_overlap_remain_within_the_character_estimate(): void
    {
        $service = new ContextChunkingService;
        $text = str_repeat('á🙂', 6000);
        $chunks = $service->chunk($text, 512, 0);
        self::assertGreaterThan(1, count($chunks));
        self::assertSame($text, implode('', array_column($chunks, 'content')));
        foreach ($service->chunk($text."\n\n".$text, 32, 64) as $chunk) {
            self::assertLessThanOrEqual(128, mb_strlen($chunk['content']));
            self::assertLessThanOrEqual(32, $chunk['token_count']);
        }
    }
}
