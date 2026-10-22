<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Contracts;

interface AiAssistantDomainServiceInterface
{
    /**
     * Generates a single block structure and payload based on intent and block type.
     *
     * @return array{type: string, data: array<string, mixed>, reasoning: string}|array{success:false,status:'needs_input',question:string,message:string}
     */
    public function generateBlock(string $blockType, string $prompt, string $locale = 'es', string $market = 'es'): array;

    /**
     * Recommends a sequenced list of block types for a given page/content intent.
     *
     * @return array{intent: string, recommended_blocks: list<array{type: string, name: string, rationale: string}>}
     */
    public function recommendBlocks(string $intent, string $locale = 'es'): array;

    /**
     * Generates a real, content-filled block sequence from a brief.
     *
     * @return array{sections: list<array<string, mixed>>}
     */
    public function composeBlocks(string $brief, string $locale = 'es', string $title = '', string $summary = ''): array;

    /**
     * Drafts a complete article including body prose, SEO metadata, and optional block layout.
     *
     * @return array{title: string, content: string, excerpt: string, meta_title: string, meta_description: string, recommended_blocks?: list<array<string, mixed>>, anti_ai_audit: array<string, mixed>}
     */
    public function draftArticle(string $topic, string $market = 'es', string $locale = 'es', string $style = 'periodistico', bool $includeBlocks = false): array;

    /**
     * Builds a complete element from a free brief using the entity schema
     * declared by its module. Never persists: the caller creates the draft.
     *
     * @return array{type: string, label: string, fields: array<string, string>, sections: list<array<string, mixed>>, keywords: list<string>, anti_ai_audit: array<string, mixed>}
     */
    public function buildPageElement(string $type, string $prompt, string $locale = 'es', string $market = 'es'): array;

    /**
     * Corrects and improves grammar, rhythm, and clarity of given prose.
     *
     * @return array{original: string, improved: string, changes: list<string>}
     */
    public function proofread(string $content, string $locale = 'es', string $action = 'improve'): array;

    /**
     * Humanizes synthetic prose by removing detected robotic clichés and balancing sentence rhythm.
     *
     * @return array{original: string, humanized: string, cliches_removed: list<string>, anti_ai_score: int}
     */
    public function humanize(string $content, string $locale = 'es', string $style = 'periodistico'): array;

    /**
     * Generates SEO title, meta description, and keywords for existing content.
     *
     * @return array{meta_title: string, meta_description: string, keywords: list<string>}
     */
    public function generateSeo(string $title, string $content, string $locale = 'es'): array;

    /**
     * Tests connectivity to a configured AI provider.
     *
     * @return array{success: bool, provider: string, model: string, message: string, latency_ms: int}
     */
    public function testProviderConnection(string $provider, string $modality = 'text'): array;
}
