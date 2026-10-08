<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\PlatformShowcaseSeeder;
use Illuminate\Database\Eloquent\Model;
use Modules\Pages\Models\Page;

beforeEach(function (): void {
    $this->seed(PlatformShowcaseSeeder::class);
    $this->admin = User::whereHas('roles', fn ($q) => $q->where('slug', 'admin'))->firstOrFail();
    $this->actingAs($this->admin);
});

test('menu public content picker excludes embargoed pages', function (): void {
    $page = Model::withoutEvents(fn () => Page::create(['template' => 'default', 'is_published' => true, 'published_at' => now()->addDay()]));
    $page->translations()->create(['locale' => 'es', 'title' => 'Contenido reservado', 'slug' => 'contenido-reservado']);
    $response = $this->get(route('admin.menus.index'))->assertOk();
    $ids = collect($response->viewData('availableEntities'))->where('type', 'page')->pluck('id')->all();
    expect($ids)->not->toContain($page->id);
});

test('an unknown sort column does not turn an authorized admin listing into a database error', function (string $sort): void {
    $this->get(route('admin.menus.index', ['sort_by' => $sort]))->assertOk();
})->with(['missing_column', 'name desc; DROP TABLE menus']);
