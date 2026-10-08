<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\PlatformShowcaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(PlatformShowcaseSeeder::class);
});

test('admin has full access to all backoffice modules and management suites', function (): void {
    $admin = User::where('email', 'admin@db-cms.io')->firstOrFail();

    $this->actingAs($admin, 'web')->get(route('admin.dashboard'))->assertStatus(200);
    $this->actingAs($admin, 'web')->get(route('admin.modules.index'))->assertStatus(200);
    $this->actingAs($admin, 'web')->get(route('admin.users.index'))->assertStatus(200);
    $this->actingAs($admin, 'web')->get(route('admin.blog.posts.index'))->assertStatus(200);
    $this->actingAs($admin, 'web')->get(route('admin.forms.index'))->assertStatus(200);
    $this->actingAs($admin, 'web')->get(route('admin.themes.index'))->assertStatus(200);
});

test('editor can access posts and pages but is forbidden from user management', function (): void {
    $editor = User::where('email', 'editor@db-cms.io')->firstOrFail();

    $this->actingAs($editor, 'web')->get(route('admin.blog.posts.index'))->assertStatus(200);
    $this->actingAs($editor, 'web')->get(route('admin.users.index'))->assertStatus(403);
});

test('author can create posts but is forbidden from forms and module management', function (): void {
    $author = User::where('email', 'author@db-cms.io')->firstOrFail();

    $this->actingAs($author, 'web')->get(route('admin.blog.posts.index'))->assertStatus(200);
    $this->actingAs($author, 'web')->get(route('admin.forms.index'))->assertStatus(403);
    $this->actingAs($author, 'web')->get(route('admin.modules.index'))->assertStatus(403);
});

test('marketing role can access forms and leads inbox but is forbidden from users', function (): void {
    $marketing = User::where('email', 'marketing@db-cms.io')->firstOrFail();

    $this->actingAs($marketing, 'web')->get(route('admin.forms.index'))->assertStatus(200);
    $this->actingAs($marketing, 'web')->get(route('admin.forms.submissions'))->assertStatus(200);
    $this->actingAs($marketing, 'web')->get(route('admin.users.index'))->assertStatus(403);
});

test('unauthenticated visitor is redirected to login when accessing admin dashboard', function (): void {
    $response = $this->get(route('admin.dashboard'));

    $response->assertRedirect(route('admin.login'));
});
