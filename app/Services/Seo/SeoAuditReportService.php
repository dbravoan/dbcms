<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Contracts\Services\PublicContentDiscoveryInterface;
use App\Contracts\Services\SeoAuditReportServiceInterface;
use App\Core\Sections\SectionLayout;
use App\DTOs\Seo\SeoAuditReportDTO;
use App\Models\Market;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Modules\Blog\Models\Post;

/** Local editorial checks and discovery counts; no external ranking is inferred. */
final class SeoAuditReportService implements SeoAuditReportServiceInterface
{
    public function __construct(private readonly PublicContentDiscoveryInterface $discovery, private readonly ContentQualityAuditorService $auditor, private readonly JsonLdSchemaService $schema) {}

    public function report(): SeoAuditReportDTO
    {
        $markets = $this->discovery->markets();
        $postIds = Schema::hasTable('posts') ? $this->publicIds('post', $markets) : [];
        $pageIds = Schema::hasTable('pages') ? $this->publicIds('page', $markets) : [];
        $posts = $postIds !== [] ? Post::with(['translations', 'publishedSections.translations'])->whereKey($postIds)->latest()->take(10)->get() : collect();
        $audited = $posts->map(function (Post $post): array {
            $translation = $post->translations->firstWhere('locale', app()->getLocale()) ?? $post->translations->first();
            $content = (string) ($translation->content ?? '');
            foreach ($post->publishedSections as $section) {
                $sectionTranslation = $section->translations->firstWhere('locale', $translation?->locale);
                $payload = SectionLayout::resolve((array) ($section->styling_json ?? []), (array) ($sectionTranslation->content_json ?? []));
                $content .= "\n".SectionLayout::plainText($payload, ['title', 'content', 'body', 'question', 'answer', 'description']);
            }
            $title = (string) ($translation->title ?? '');
            $audit = $this->auditor->audit($title, $content, (string) ($translation->meta_description ?? ''));

            return ['id' => $post->getKey(), 'title' => $title, 'slug' => (string) ($translation->slug ?? ''), 'locale' => (string) ($translation->locale ?? ''), 'score' => $audit['overall_score'], 'recommendations' => $audit['recommendations'], 'word_count' => $audit['metrics']['word_count'] ?? 0, 'type' => 'Blog Post'];
        })->values()->all();

        return new SeoAuditReportDTO(array_values($audited), count($pageIds), count($postIds), $markets, (string) json_encode($this->schema->generateSiteGraph(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), Route::has('geo.llms') && Route::has('geo.llms-full'));
    }

    /** @param Collection<int, Market> $markets
     * @return list<int>
     */
    private function publicIds(string $type, Collection $markets): array
    {
        $ids = [];
        foreach ($markets as $market) {
            foreach ($market->languages as $language) {
                foreach ($this->discovery->query($type, $market, $language->code)->get() as $entity) {
                    $id = (int) $entity->getKey();
                    $ids[$id] = $id;
                }
            }
        }

        return array_values($ids);
    }
}
