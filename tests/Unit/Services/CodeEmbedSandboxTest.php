<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use BlockPackages\Basic\Blocks\CodeEmbedBlock;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3).'/block-packages/Basic/Blocks/CodeEmbedBlock.php';

final class CodeEmbedSandboxTest extends TestCase
{
    public function test_code_embeds_keep_scripts_inside_an_opaque_sandbox(): void
    {
        $html = (new CodeEmbedBlock)->render(['content' => '<script>parent.document.cookie</script><img onerror="evil()">']);
        self::assertStringContainsString('sandbox="allow-scripts"', $html);
        self::assertStringContainsString('srcdoc="', $html);
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringNotContainsString('allow-same-origin', $html);
        self::assertStringNotContainsString('allow-top-navigation', $html);
    }
}
