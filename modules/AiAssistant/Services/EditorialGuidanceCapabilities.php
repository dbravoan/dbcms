<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Services;

use App\Core\Capabilities\CallableCapability;
use App\Core\Capabilities\CapabilityRegistry;
use App\Services\Ai\AntiAiEditorialService;

final class EditorialGuidanceCapabilities
{
    public function register(CapabilityRegistry $registry): void
    {
        $registry->register(new CallableCapability(
            'cms_research_topic_context',
            'Researches a topic against CMS knowledge base to suggest relevant internal link targets, candidate categories, and target markets',
            ['topic' => ['type' => 'string', 'description' => 'Topic to research'], 'market' => ['type' => 'string', 'description' => 'Market code (default: \'es\')'], 'locale' => ['type' => 'string', 'description' => 'Locale code (default: \'es\')']],
            ['topic'],
            'use:ai',
            'read',
            static fn (array $args): array => app(EditorialGuidanceService::class)->research($args),
        ), 'ai_assistant');

        $registry->register(new CallableCapability(
            'cms_get_editorial_prompt',
            'Retrieves the official Human+AI editorial system prompt, style guide, and anti-AI rules',
            ['locale' => ['type' => 'string', 'description' => 'Locale code (\'es\' or \'en\', default: \'es\')']],
            [],
            'use:ai',
            'read',
            static fn (array $args): array => app(EditorialGuidanceService::class)->prompt((string) ($args['locale'] ?? 'es')),
        ), 'ai_assistant');

        $registry->register(new CallableCapability(
            'cms_validate_editorial_tone',
            'Audits text against anti-AI heuristics: sentence length burstiness, Spanish cliché markers, title casing, and punctuation',
            ['title' => ['type' => 'string', 'description' => 'Article or page title'], 'content' => ['type' => 'string', 'description' => 'Markdown or HTML body content']],
            ['title', 'content'],
            'use:ai',
            'read',
            static fn (array $args): array => app(AntiAiEditorialService::class)->validateEditorialTone((string) ($args['title'] ?? ''), (string) ($args['content'] ?? ''), (string) ($args['locale'] ?? 'es')),
        ), 'ai_assistant');

        $registry->register(new CallableCapability(
            'cms_humanize_prose',
            'Humanizes robotic prose by replacing detected AI clichés with natural Spanish idioms and rhythmic sentence variations',
            ['content' => ['type' => 'string', 'description' => 'Content to humanize'], 'style' => ['type' => 'string', 'enum' => ['periodistico', 'ensayo', 'tecnico'], 'description' => 'Target editorial style (default: periodistico)']],
            ['content'],
            'use:ai',
            'read',
            static fn (array $args): array => app(AntiAiEditorialService::class)->humanizeProse((string) ($args['content'] ?? ''), (string) ($args['locale'] ?? 'es'), (string) ($args['style'] ?? 'periodistico')),
        ), 'ai_assistant');

    }
}
