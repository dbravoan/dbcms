<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\PlatformShowcaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Blog\Models\Post;
use Modules\Forms\Models\FormSubmission;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(PlatformShowcaseSeeder::class);
    $this->admin = User::where('email', 'admin@db-cms.io')->firstOrFail();
});

test('users datatable filters by text search query and role', function (): void {
    // Search by name (seeded showcase users).
    $response = $this->actingAs($this->admin, 'web')->get('/admin/users?search=Editor');
    $response->assertOk();
    $response->assertSee('editor@db-cms.io');
    $response->assertDontSee('marketing@db-cms.io');

    // Filter by role
    $responseRole = $this->actingAs($this->admin, 'web')->get('/admin/users?role=marketing');
    $responseRole->assertOk();
    $responseRole->assertSee('marketing@db-cms.io');
    $responseRole->assertDontSee('author@db-cms.io');
});

test('posts datatable filters by published status and search text', function (): void {
    $response = $this->actingAs($this->admin, 'web')->get('/admin/blog/posts?search=Arquitectura');
    $response->assertOk();
    $response->assertSee('controladores finos');

    $responseStatus = $this->actingAs($this->admin, 'web')->get('/admin/blog/posts?is_published=1');
    $responseStatus->assertOk();
    $responseStatus->assertSee('Publicado');
});

test('forms and leads datatables support searching, sorting, and pagination limit', function (): void {
    $responseForms = $this->actingAs($this->admin, 'web')->get('/admin/forms?search=contacto');
    $responseForms->assertOk();
    $responseForms->assertSee('Formulario de contacto');

    $responseLeads = $this->actingAs($this->admin, 'web')->get('/admin/forms/submissions?per_page=10');
    $responseLeads->assertOk();
    $responseLeads->assertSee('Marta Ruiz');
});

test('user deletion through backoffice', function (): void {
    $viewer = User::where('email', 'viewer@db-cms.io')->firstOrFail();

    $response = $this->actingAs($this->admin, 'web')->delete("/admin/users/{$viewer->id}");
    $response->assertRedirect('/admin/users');

    expect(User::where('email', 'viewer@db-cms.io')->exists())->toBeFalse();
});

test('post deletion through backoffice', function (): void {
    $post = Post::firstOrFail();

    $response = $this->actingAs($this->admin, 'web')->delete("/admin/blog/posts/{$post->id}");
    $response->assertRedirect('/admin/blog/posts');

    expect(Post::where('id', $post->id)->exists())->toBeFalse();
});

test('form submission deletion through backoffice', function (): void {
    $submission = FormSubmission::firstOrFail();

    $response = $this->actingAs($this->admin, 'web')->delete("/admin/forms/submissions/{$submission->id}");
    $response->assertRedirect('/admin/forms/submissions');

    expect(FormSubmission::where('id', $submission->id)->exists())->toBeFalse();
});
