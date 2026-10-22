<?php

declare(strict_types=1);

use App\Contracts\Services\ModuleSettingsServiceInterface;
use App\Models\Market;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\Comment;
use Modules\Blog\Models\Post;
use Tests\Support\BlogComposition;
use Tests\Support\BlogFixtures;
use Tests\Support\CmsFixtures;

uses(BlogComposition::class);

beforeEach(function (): void {
    CmsFixtures::localization();
    CmsFixtures::identities();
    CmsFixtures::home();
    BlogFixtures::post();
    app(ModuleSettingsServiceInterface::class)->set('blog', 'comments_enabled', true);
    $this->publicPost = Post::firstOrFail();
    $this->publicPost->updateQuietly(['allow_comments' => true]);
    $this->slug = $this->publicPost->translations()->where('locale', 'es')->firstOrFail()->slug;
});

test('public comments cannot attach to another article or an unapproved parent', function (bool $sameArticle, bool $approved): void {
    $post = $this->publicPost;
    if (! $sameArticle) {
        $post = Post::create(['user_id' => $this->publicPost->user_id, 'is_published' => true, 'published_at' => now(), 'allow_comments' => true]);
    }
    $parent = Comment::create(['post_id' => $post->getKey(), 'author_name' => 'Moderated author', 'author_email' => 'moderated@example.invalid', 'content' => 'A comment requiring review', 'is_approved' => $approved]);
    $this->postJson("/es/es/blog/{$this->slug}/comments", ['parent_id' => $parent->getKey(), 'author_name' => 'Reply author', 'author_email' => 'reply@example.invalid', 'content' => 'This reply must stay attached to its own public article.'])->assertUnprocessable()->assertJsonValidationErrors('parent_id');
    expect(Comment::where('author_email', 'reply@example.invalid')->exists())->toBeFalse();
})->with([[false, true], [true, false]]);

test('article breadcrumbs do not expose an unavailable taxonomy', function (): void {
    $category = Category::create(['is_active' => false]);
    $category->translations()->create(['locale' => 'es', 'name' => 'Categoría confidencial fuera de catálogo', 'slug' => 'categoria-confidencial']);
    $category->markets()->sync([Market::where('code', 'es')->firstOrFail()->getKey()]);
    $this->publicPost->updateQuietly(['category_id' => $category->getKey()]);
    $this->get("/es/es/blog/{$this->slug}")->assertOk()->assertDontSee('Categoría confidencial fuera de catálogo');
});
