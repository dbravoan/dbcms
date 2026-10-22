<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BlogFormsComposition;
use Tests\Support\CmsFixtures;

uses(RefreshDatabase::class);

uses(BlogFormsComposition::class);

beforeEach(function (): void {
    CmsFixtures::localization();
    CmsFixtures::identities([
        'admin' => [],
        'editor' => ['access:admin', 'manage:posts', 'manage:pages'],
        'author' => ['access:admin', 'manage:posts'],
        'marketing' => ['access:admin', 'manage:forms'],
    ]);
});

test('admin has access to Blog and Forms management', function (): void {
    $admin = User::where('email', 'admin@db-cms.io')->firstOrFail();

    $this->actingAs($admin, 'web')->get(route('admin.blog.posts.index'))->assertStatus(200);
    $this->actingAs($admin, 'web')->get(route('admin.forms.index'))->assertStatus(200);
});

test('editor can access posts', function (): void {
    $editor = User::where('email', 'editor@db-cms.io')->firstOrFail();

    $this->actingAs($editor, 'web')->get(route('admin.blog.posts.index'))->assertStatus(200);
});

test('author can access posts but is forbidden from forms', function (): void {
    $author = User::where('email', 'author@db-cms.io')->firstOrFail();

    $this->actingAs($author, 'web')->get(route('admin.blog.posts.index'))->assertStatus(200);
    $this->actingAs($author, 'web')->get(route('admin.forms.index'))->assertStatus(403);
});

test('marketing role can access forms and leads inbox', function (): void {
    $marketing = User::where('email', 'marketing@db-cms.io')->firstOrFail();

    $this->actingAs($marketing, 'web')->get(route('admin.forms.index'))->assertStatus(200);
    $this->actingAs($marketing, 'web')->get(route('admin.forms.submissions'))->assertStatus(200);
});
