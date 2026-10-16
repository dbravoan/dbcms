<?php

declare(strict_types=1);

namespace Tests\Feature\Blog;

use App\Contracts\Services\LocalizationServiceInterface;
use App\Contracts\Services\ModuleSettingsServiceInterface;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Blog\Models\Comment;
use Modules\Blog\Models\Post;
use Tests\Support\BlogComposition;
use Tests\Support\BlogFixtures;
use Tests\Support\CmsFixtures;

uses(RefreshDatabase::class, BlogComposition::class);

beforeEach(function (): void {
    CmsFixtures::localization();
    CmsFixtures::identities();
    CmsFixtures::home();
    BlogFixtures::post();
});

test('icons are self-hosted through the bundled stylesheet (no FontAwesome CDN)', function (): void {
    $admin = User::where('email', 'admin@db-cms.io')->firstOrFail();

    foreach ([$this->actingAs($admin)->get('/admin'), $this->get('/es/es')] as $response) {
        $response->assertStatus(200);
        // Bundled CSS carries the self-hosted FontAwesome subset.
        $response->assertSee('/build/assets/', false);
        $response->assertDontSee('cdnjs.cloudflare.com/ajax/libs/font-awesome', false);
    }
});

test('post allowsComments returns expected boolean based on global setting and post flag', function (): void {
    $post = Post::firstOrFail();
    $settings = app(ModuleSettingsServiceInterface::class);

    // Default: allow_comments is true, module setting comments_enabled is true
    $post->update(['allow_comments' => true]);
    $settings->set('blog', 'comments_enabled', true);
    expect($post->allowsComments())->toBeTrue();

    // Per-post disabled
    $post->update(['allow_comments' => false]);
    expect($post->allowsComments())->toBeFalse();

    // Globally disabled
    $post->update(['allow_comments' => true]);
    $settings->set('blog', 'comments_enabled', false);
    expect($post->allowsComments())->toBeFalse();
});

test('blog show page conditionally renders comment form or disabled notice', function (): void {
    $post = Post::firstOrFail();
    $translation = $post->translations->where('locale', 'es')->first() ?? $post->translations->firstOrFail();
    $settings = app(ModuleSettingsServiceInterface::class);

    $commentForm = static function (string $html) use ($translation, $post): ?\DOMNode {
        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $action = app(LocalizationServiceInterface::class)->url("/blog/{$translation->slug}/comments", $post->markets()->firstOrFail(), 'es');
        $forms = (new \DOMXPath($document))->query('//form[@action="'.$action.'"][@method="POST" or @method="post"]');

        return $forms === false ? null : $forms->item(0);
    };

    // Case 1: Comments allowed
    $post->update(['allow_comments' => true]);
    $settings->set('blog', 'comments_enabled', true);

    $response = $this->get("/es/es/blog/{$translation->slug}");
    $response->assertStatus(200);
    expect($commentForm((string) $response->getContent()))->toBeInstanceOf(\DOMElement::class);

    // Case 2: Comments disabled for this post
    $post->update(['allow_comments' => false]);
    $responseDisabled = $this->get("/es/es/blog/{$translation->slug}");
    $responseDisabled->assertStatus(200);
    expect($commentForm((string) $responseDisabled->getContent()))->toBeNull();
});

test('user can submit comment when comments are enabled and honeypot is empty', function (): void {
    $post = Post::firstOrFail();
    $translation = $post->translations->where('locale', 'es')->first() ?? $post->translations->firstOrFail();
    $settings = app(ModuleSettingsServiceInterface::class);

    $post->update(['allow_comments' => true]);
    $settings->set('blog', 'comments_enabled', true);
    $settings->set('blog', 'comments_require_approval', false);

    $response = $this->post("/es/es/blog/{$translation->slug}/comments", [
        'author_name' => 'Carlos Delgado',
        'author_email' => 'carlos@example.com',
        'content' => 'Excelente artículo sobre la arquitectura de microservicios.',
        'website_url' => '', // honeypot
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('comment_success');

    $comment = Comment::where('post_id', $post->id)
        ->where('author_email', 'carlos@example.com')
        ->first();

    expect($comment)->not->toBeNull();
    expect($comment->author_name)->toBe('Carlos Delgado');
    expect($comment->content)->toBe('Excelente artículo sobre la arquitectura de microservicios.');
    expect($comment->is_approved)->toBeTrue();
});

test('comment submission is rejected when comments are disabled for post', function (): void {
    $post = Post::firstOrFail();
    $translation = $post->translations->where('locale', 'es')->first() ?? $post->translations->firstOrFail();

    $post->update(['allow_comments' => false]);

    $response = $this->post("/es/es/blog/{$translation->slug}/comments", [
        'author_name' => 'Carlos Delgado',
        'author_email' => 'carlos@example.com',
        'content' => 'Comentario que no debe ser admitido.',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors('comment_error');

    $commentExists = Comment::where('post_id', $post->id)
        ->where('author_email', 'carlos@example.com')
        ->exists();

    expect($commentExists)->toBeFalse();
});

test('spam bot filling honeypot gets silently rejected', function (): void {
    $post = Post::firstOrFail();
    $translation = $post->translations->where('locale', 'es')->first() ?? $post->translations->firstOrFail();

    $post->update(['allow_comments' => true]);

    $response = $this->post("/es/es/blog/{$translation->slug}/comments", [
        'author_name' => 'Spam Bot',
        'author_email' => 'bot@spammer.net',
        'content' => 'Buy cheap backlinks here http://spam.xyz',
        'website_url' => 'http://spam.xyz', // Bot fills honeypot
    ]);

    $response->assertRedirect();

    $commentExists = Comment::where('author_email', 'bot@spammer.net')->exists();
    expect($commentExists)->toBeFalse();
});

test('admin can update post allow_comments status from backoffice', function (): void {
    $admin = User::where('email', 'admin@db-cms.io')->firstOrFail();
    $post = Post::firstOrFail();
    $translation = $post->translations->firstOrFail();

    // Verify admin form contains the toggle
    $editResponse = $this->actingAs($admin)->get("/admin/blog/posts/{$post->id}/edit");
    $editResponse->assertStatus(200);
    $editResponse->assertSee('name="allow_comments"', false);

    // Update with allow_comments = 0
    $updateData = [
        'category_id' => $post->category_id,
        'user_id' => $post->user_id,
        'status' => 'published',
        'is_featured' => false,
        'allow_comments' => 0,
        'reading_time_minutes' => 5,
        'translations' => [
            $translation->locale => [
                'title' => $translation->title,
                'slug' => $translation->slug,
                'content' => $translation->content,
            ],
        ],
    ];

    $updateResponse = $this->actingAs($admin)->put("/admin/blog/posts/{$post->id}", $updateData);
    $updateResponse->assertRedirect();

    $updateResponse->assertSessionHasNoErrors();
    $post->refresh();
    expect($post->is_published)->toBeTrue();
    expect($post->allow_comments)->toBeFalse();
});
