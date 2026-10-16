<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Blog\Models\Post;
use Modules\Blog\Models\PostTranslation;
use Tests\Support\BlogComposition;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class, BlogComposition::class);

it('labels drafts honestly in the dashboard contributed by Blog', function (): void {
    app()->setLocale('es');
    $post = new Post(['is_published' => false]);
    $post->id = 42;
    $post->setRelation('translations', collect([new PostTranslation(['title' => 'Draft fixture', 'locale' => 'es'])]));
    $post->setRelation('author', null);
    $html = view('blog::admin.dashboard.details', ['recentPosts' => collect([$post]), 'stats' => ['total_posts' => 1]])->render();

    expect($html)->toContain('Draft fixture', __('admin.status.draft'));
    expect(preg_match('/>\s*'.preg_quote(__('admin.dashboard.published'), '/').'\s*</u', $html))->toBe(0);
});
