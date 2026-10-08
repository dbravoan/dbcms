<?php

declare(strict_types=1);

namespace Modules\AiAssistant\DTOs;

final readonly class MultimodalOrchestrationResult
{
    /**
     * @param  list<array<string, mixed>>  $sections
     * @param  list<string>  $keywords
     * @param  list<int|string>  $mediaFileIds
     * @param  array<string, mixed>  $antiAiAudit
     * @param  array<string, mixed>  $blueprint
     * @param  list<array{title: string, url: string, snippet: string}>  $sources
     */
    public function __construct(
        public string $entityType,
        public ?int $entityId,
        public string $title,
        public string $slug,
        public string $summary,
        public string $content,
        public string $metaTitle,
        public string $metaDescription,
        public array $keywords = [],
        public ?string $featuredImageUrl = null,
        public ?string $videoUrl = null,
        public array $mediaFileIds = [],
        public array $sections = [],
        public array $antiAiAudit = [],
        public array $blueprint = [],
        public array $sources = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'title' => $this->title,
            'slug' => $this->slug,
            'summary' => $this->summary,
            'content' => $this->content,
            'meta_title' => $this->metaTitle,
            'meta_description' => $this->metaDescription,
            'keywords' => $this->keywords,
            'featured_image_url' => $this->featuredImageUrl,
            'video_url' => $this->videoUrl,
            'media_file_ids' => $this->mediaFileIds,
            'sections' => $this->sections,
            'anti_ai_audit' => $this->antiAiAudit,
            'blueprint' => $this->blueprint,
            'sources' => $this->sources,
        ];
    }
}
