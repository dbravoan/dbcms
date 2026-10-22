<?php

declare(strict_types=1);

use App\Contracts\Services\ModuleSettingsServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Blog\Models\Comment;
use Modules\Blog\Models\Post;
use Modules\Captcha\Services\CaptchaService;
use Tests\Support\BlogCaptchaComposition;
use Tests\Support\BlogFixtures;
use Tests\Support\CmsFixtures;

uses(RefreshDatabase::class, BlogCaptchaComposition::class);

beforeEach(function (): void {
    CmsFixtures::localization();
    CmsFixtures::identities();
    CmsFixtures::home();
    BlogFixtures::post();

    $settings = app(ModuleSettingsServiceInterface::class);
    $settings->set('blog', 'comments_enabled', true);
    $settings->set('blog', 'comments_require_approval', false);
});

test('a blog comment without a captcha is rejected when the module is enabled', function (): void {
    $post = Post::firstOrFail();
    $post->update(['allow_comments' => true]);
    $translation = $post->translations->where('locale', 'es')->first() ?? $post->translations->firstOrFail();

    $response = $this->post("/es/es/blog/{$translation->slug}/comments", [
        'author_name' => 'Carlos',
        'author_email' => 'carlos@example.com',
        'content' => 'Comentario sin captcha, debe fallar la validación.',
    ]);

    $response->assertSessionHasErrors(['captcha_response']);

    expect(Comment::where('author_email', 'carlos@example.com')->count())->toBe(0);
});

test('a blog comment with a valid captcha is accepted', function (): void {
    $post = Post::firstOrFail();
    $post->update(['allow_comments' => true]);
    $translation = $post->translations->where('locale', 'es')->first() ?? $post->translations->firstOrFail();

    $challenge = app(CaptchaService::class)->generateChallenge();
    preg_match('/(\d+)\s*\+\s*(\d+)/', $challenge['svg'], $matches);
    $answer = (string) ((int) $matches[1] + (int) $matches[2]);

    $response = $this->post("/es/es/blog/{$translation->slug}/comments", [
        'author_name' => 'Carlos',
        'author_email' => 'carlos@example.com',
        'content' => 'Comentario con captcha válido.',
        '_captcha_token' => $challenge['token'],
        'captcha_response' => $answer,
    ]);

    $response->assertSessionHasNoErrors();

    expect(Comment::where('author_email', 'carlos@example.com')->count())->toBe(1);
});

test('the blog comment form is rendered through the shared field framework', function (): void {
    $post = Post::firstOrFail();
    $post->update(['allow_comments' => true]);

    $settings = app(ModuleSettingsServiceInterface::class);
    $settings->set('blog', 'comments_enabled', true);

    $translation = $post->translations->where('locale', 'es')->first() ?? $post->translations->firstOrFail();

    $response = $this->get("/es/es/blog/{$translation->slug}");

    $response->assertOk()
        ->assertSee('name="author_name"', false)
        ->assertSee('name="author_email"', false)
        ->assertSee('name="content"', false)
        ->assertSee('_hp_security_check', false)
        // The captcha field comes from the shared schema (module active here).
        ->assertSee('name="captcha_response"', false);
});

test('a blog comment is validated against the active external driver', function (): void {
    $settings = app(ModuleSettingsServiceInterface::class);
    $settings->set('captcha', 'driver', 'recaptcha');
    $settings->set('captcha', 'site_key', 'SITE');
    $settings->set('captcha', 'secret_key', 'SECRET');

    $post = Post::firstOrFail();
    $post->update(['allow_comments' => true]);
    $translation = $post->translations->where('locale', 'es')->first() ?? $post->translations->firstOrFail();

    $payload = [
        'author_name' => 'Carlos',
        'author_email' => 'carlos@example.com',
        'content' => 'Comentario validado con Google.',
    ];

    // Without the Google token the submission is rejected.
    $this->post("/es/es/blog/{$translation->slug}/comments", $payload)
        ->assertSessionHasErrors(['g-recaptcha-response']);

    Http::fake([
        'www.google.com/recaptcha/api/siteverify' => Http::response(['success' => true, 'score' => 0.9]),
    ]);

    $this->post("/es/es/blog/{$translation->slug}/comments", $payload + ['g-recaptcha-response' => 'token'])
        ->assertSessionHasNoErrors();

    expect(Comment::where('author_email', 'carlos@example.com')->count())->toBe(1);
});
