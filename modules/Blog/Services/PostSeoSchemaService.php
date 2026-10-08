<?php

declare(strict_types=1);

namespace Modules\Blog\Services;

use App\Services\Seo\PublicContentModifiedService;
use Modules\Blog\Models\Post;
use Modules\Blog\Models\PostTranslation;

/** Builds only metadata backed by the published article and its public author. */
final class PostSeoSchemaService
{
    /** @return array<string, mixed> */
    public function entity(Post $post, PostTranslation $translation, string $url): array
    {
        $entity = [
            '@type' => in_array($translation->schema_type, ['Article', 'TechArticle', 'BlogPosting', 'NewsArticle'], true) ? $translation->schema_type : 'Article',
            'headline' => $translation->title,
            'mainEntityOfPage' => ['@id' => $url.'#webpage'],
        ];
        if ($translation->summary) {
            $entity['description'] = strip_tags($translation->summary);
        }
        if ($post->published_at !== null) {
            $entity['datePublished'] = $post->published_at->toIso8601String();
        }
        $modified = app(PublicContentModifiedService::class)->forEntity($post);
        if ($modified !== null) {
            $entity['dateModified'] = $modified->toIso8601String();
        }
        if ($post->author !== null && $post->author->name !== '') {
            $entity['author'] = ['@type' => 'Person', 'name' => $post->author->name];
        }
        if ($post->featured_image) {
            $entity['image'] = url($post->featured_image);
        }

        return $entity;
    }
}
