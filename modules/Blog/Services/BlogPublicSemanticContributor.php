<?php

declare(strict_types=1);

namespace Modules\Blog\Services;

use App\Contracts\Services\LocalizationServiceInterface;
use App\Contracts\Services\PublicContentDiscoveryInterface;
use App\Contracts\Services\PublishedSectionTextInterface;
use App\Core\Content\Contracts\PublicSemanticContributorInterface;
use App\Models\Market;
use App\Services\Content\SafeContentRenderer;
use Modules\Blog\Models\Post;

final class BlogPublicSemanticContributor implements PublicSemanticContributorInterface
{
    public function key(): string
    {
        return 'blog';
    }

    public function ownerModule(): string
    {
        return 'blog';
    }

    public function index(Market $market, string $locale): string
    {
        $base = app(LocalizationServiceInterface::class)->url('/', $market, $locale);
        $blogSlug = app(LocalizationServiceInterface::class)->getModulePrefix('blog', $locale);
        $posts = app(PublicContentDiscoveryInterface::class)->query('post', $market, $locale, ['translations'])
            ->latest()
            ->take(20)
            ->get();

        if ($posts->isEmpty()) {
            return $this->categories($market, $locale);
        }

        $output = "## Technical Articles & Blog Posts\n";
        foreach ($posts as $post) {
            /** @var Post $post */
            $trans = $post->translations->where('locale', $locale)->first();
            if ($trans) {
                $summary = ! empty($trans->summary) ? $trans->summary : 'Artículo técnico publicado en el blog.';
                $output .= "- [{$trans->title}]({$base}/{$blogSlug}/{$trans->slug}): {$summary}\n";
            }
        }

        return $output."\n".$this->categories($market, $locale);
    }

    public function corpus(Market $market, string $locale): string
    {
        $output = '';
        $posts = app(PublicContentDiscoveryInterface::class)->query('post', $market, $locale, ['translations', 'publishedSections.translations'])
            ->get();

        foreach ($posts as $post) {
            /** @var Post $post */
            $trans = $post->translations->where('locale', $locale)->first();
            if (! $trans) {
                continue;
            }

            $output .= "## Article: {$trans->title}\n";
            $output .= '**Source**: '.app(PublicContentDiscoveryInterface::class)->url('post', $market, $locale, (string) $trans->slug)."\n";
            $output .= "**Published**: {$post->published_at?->toIso8601String()}\n\n";
            $output .= "### Summary\n{$trans->summary}\n\n";
            $output .= "### Body\n".$this->visibleText((string) $trans->content)."\n\n";
            $output .= app(PublishedSectionTextInterface::class)->text($post, $market, $locale);
            $output .= "---\n\n";
        }

        return $output;
    }

    /** Preserve public Markdown content while exporting readable paragraph text. */
    private function visibleText(string $content): string
    {
        $html = app(SafeContentRenderer::class)->markdown($content);
        $html = preg_replace('/<br\b[^>]*>|<\/(?:p|div|h[1-6]|li|tr|blockquote|pre)>/i', "\n", $html) ?? '';

        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function categories(Market $market, string $locale): string
    {
        $discovery = app(PublicContentDiscoveryInterface::class);
        $output = "## Topic Taxonomies & Categories\n";
        foreach ($discovery->query('blog_category', $market, $locale)->get() as $category) {
            $translation = $category->getRelation('translations')->firstWhere('locale', $locale);
            $url = $discovery->entityUrl('blog_category', $category, $market, $locale);
            $output .= "- [{$translation->name}]({$url}): {$translation->description}\n";
        }

        return $output."\n";
    }
}
