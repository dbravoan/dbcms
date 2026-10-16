<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Services;

use App\Services\Capabilities\SearchNavigationService;

final class EditorialGuidanceService
{
    public function __construct(private readonly AiPromptTemplateEngine $prompts, private readonly SearchNavigationService $search) {}

    /** @return array{locale: string, system_prompt: string} */
    public function prompt(string $locale): array
    {
        return ['locale' => $locale, 'system_prompt' => $this->prompts->buildSystemPrompt($locale, $locale)."\n\n".$this->guide($locale)['content']];
    }

    /** @return array{filename: string, path: string, content: string} */
    public function guide(string $locale): array
    {
        $filename = $locale === 'en' ? 'EDITORIAL_HUMAN_AI_SYSTEM_PROMPT.md' : 'PROMPT_EDITORIAL_HUMAN_IA.md';
        $path = dirname(__DIR__).'/Resources/prompts/'.$filename;

        return ['filename' => $filename, 'path' => $path, 'content' => (string) file_get_contents($path)];
    }

    /**
     * Research uses the public index: drafts and other markets cannot leak into suggestions.
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function research(array $args): array
    {
        $topic = trim((string) ($args['topic'] ?? ''));
        $results = $this->search->search(['query' => $topic, 'limit' => 5] + $args);

        return [
            'topic' => $topic,
            'existing_coverage_count' => $results['total'] ?? 0,
            'suggested_internal_links' => $results['results'] ?? [],
        ];
    }
}
