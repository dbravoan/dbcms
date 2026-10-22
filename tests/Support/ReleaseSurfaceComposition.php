<?php

declare(strict_types=1);

namespace Tests\Support;

/** Deliberate full catalogue only for the release surface audit. */
trait ReleaseSurfaceComposition
{
    public const TEST_MODULES = ['ads', 'ai_assistant', 'analytics', 'blog', 'captcha', 'content_audit', 'creative-studio', 'forms', 'gallery', 'maintenance', 'marketing', 'mcp', 'meta', 'orchestration', 'search', 'shop', 'social-publishing'];
}
