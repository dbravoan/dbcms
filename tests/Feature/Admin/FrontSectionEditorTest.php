<?php

declare(strict_types=1);

use App\Content\Pages\Models\Page;
use App\Content\Pages\Models\PageSection;
use App\Core\Sections\SectionEditorService;
use App\Models\User;
use App\Services\Capabilities\ContentSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CmsFixtures;
use Tests\Support\VisualContentFixtures;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    CmsFixtures::localization();
    CmsFixtures::identities();
    VisualContentFixtures::home();
    $this->admin = User::where('email', 'admin@db-cms.io')->firstOrFail();
});

test('the public page only renders published sections', function (): void {
    $page = Page::firstOrFail();
    $marker = 'PUBLIC-MARKER-'.uniqid();

    $page->sections()->delete();

    /** @var PageSection $section */
    $section = $page->sections()->create([
        'type' => 'text',
        'order' => 1,
        'is_active' => true,
        'status' => 'draft',
        'styling_json' => ['type' => 'text'],
    ]);
    $section->translations()->create([
        'locale' => 'es',
        'content_json' => ['type' => 'text', 'title' => $marker, 'content' => $marker],
    ]);

    // Guest request: the draft section must not reach the public output.
    $this->followingRedirects()->get('/')->assertOk()->assertDontSee($marker, false);

    $section->update(['status' => 'published', 'published_at' => now()]);

    $this->followingRedirects()->get('/')->assertOk()->assertSee($marker, false);
});

test('the section payload exposes the real id so the front can deep-link', function (): void {
    $page = Page::firstOrFail();
    $page->sections()->delete();

    /** @var PageSection $section */
    $section = $page->sections()->create([
        'type' => 'text',
        'order' => 1,
        'is_active' => true,
        'status' => 'published',
        'published_at' => now(),
        'styling_json' => ['type' => 'text'],
    ]);
    $section->translations()->create([
        'locale' => 'es',
        'content_json' => ['type' => 'text'],
    ]);

    $payload = app(SectionEditorService::class)->payload($page);

    expect($payload['es'][0]['id'] ?? null)->toBe((int) $section->getKey());
    expect($payload['en'])->toBe([]);
});

test('the front section carries a data-cms-section marker for the admin deep-link', function (): void {
    $page = Page::query()
        ->whereHas('translations', fn ($query) => $query->where('slug', 'home'))
        ->firstOrFail();

    $section = $page->sections()->orderBy('order')->firstOrFail();

    $html = (string) $this->get('/es/es/')->assertOk()->getContent();

    expect($html)->toContain('data-cms-section="'.$section->getKey().'"');
});

test('the backoffice editor focuses the requested section in the block manager', function (): void {
    $page = Page::firstOrFail();
    $section = $page->sections()->orderBy('order')->firstOrFail();

    $response = $this->actingAs($this->admin, 'web')
        ->get(route('admin.pages.edit', ['page' => $page, 'section' => $section->getKey()]));

    $response->assertOk();

    // The block manager receives the target id as its last constructor argument.
    expect((string) $response->getContent())
        ->toMatch('/blockManager\(.*,\s*'.$section->getKey().'\)"/s');
});

test('the content audit snapshot includes the block-manager sections', function (): void {
    $page = Page::firstOrFail();

    $snapshot = app(ContentSnapshotService::class)->capture($page);

    expect($snapshot)->toHaveKey('section_records');
    expect($snapshot['section_records'])->toBeArray();
});
