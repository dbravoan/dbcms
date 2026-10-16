<?php

declare(strict_types=1);

use App\Content\Pages\Models\Page;
use App\Core\Blocks\BlockPackageManager;
use App\Core\Blocks\Events\BlockPackageAvailabilityChanged;
use App\Core\Search\SearchIndexer;
use App\Models\SearchDocumentIndex;
use Illuminate\Support\Facades\Event;
use Tests\Support\CmsFixtures;

beforeEach(function (): void {
    CmsFixtures::localization(['es']);
    $this->page = Page::withoutEvents(function (): Page {
        $page = CmsFixtures::home();
        foreach ([['roi', 'Metric available', 'published', true], ['text', 'Public copy', 'published', true], ['text', 'Draft section', 'draft', true], ['text', 'Inactive section', 'published', false]] as [$type, $title, $status, $active]) {
            $section = $page->sections()->create(['type' => $type, 'status' => $status, 'is_active' => $active, 'order' => 0, 'styling_json' => ['type' => $type]]);
            $section->translations()->create(['locale' => 'es', 'content_json' => ['type' => $type, 'title' => $title]]);
        }
        $draft = Page::create(['template' => 'default', 'is_published' => false]);
        $draft->translations()->create(['locale' => 'es', 'title' => 'Draft entity', 'slug' => 'draft']);

        return $page;
    });
    app(SearchIndexer::class)->indexAll();
});

test('package availability rebuilds public indexed text without drafts or disabled sources', function (): void {
    $body = fn (): string => (string) SearchDocumentIndex::where('type', 'page')->where('entity_id', $this->page->id)->value('body');
    expect($body())->toContain('Metric available', 'Public copy')->not->toContain('Draft section', 'Inactive section');
    SearchDocumentIndex::create(['type' => 'missing-module', 'entity_id' => '1', 'locale' => 'es', 'market_code' => 'es', 'url' => '/hidden', 'title' => 'Stale disabled source', 'body' => 'Hidden']);
    app(BlockPackageManager::class)->setEnabled('charts', false);
    expect($body())->toContain('Public copy')->not->toContain('Metric available');
    expect(SearchDocumentIndex::pluck('title')->all())->toBe(['Fixture home']);
    app(BlockPackageManager::class)->setEnabled('charts', true);
    expect($body())->toContain('Metric available');
});

test('a failed availability listener rolls back the package state and index together', function (): void {
    $before = SearchDocumentIndex::firstOrFail()->body;
    Event::listen(BlockPackageAvailabilityChanged::class, static function (): never {
        throw new RuntimeException('Projection failed');
    });
    expect(fn () => app(BlockPackageManager::class)->setEnabled('charts', false))->toThrow(RuntimeException::class, 'Projection failed');
    expect(app(BlockPackageManager::class)->isEnabled('charts'))->toBeTrue()
        ->and(SearchDocumentIndex::firstOrFail()->body)->toBe($before);
});
