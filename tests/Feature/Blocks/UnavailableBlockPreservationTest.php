<?php

declare(strict_types=1);

use App\Content\Pages\Models\Page;
use App\Core\Blocks\BlockPackageManager;
use App\Core\Sections\SectionEditorService;
use App\Core\Sections\SectionSyncService;
use App\Core\Sections\UnavailableBlockGuard;
use Illuminate\Validation\ValidationException;

test('disabled blocks reject insertion and mutation while another field remains editable', function (): void {
    app(BlockPackageManager::class)->setEnabled('charts', false);
    $guard = app(UnavailableBlockGuard::class);
    $old = ['type' => 'text', 'columns' => [['id' => 'main', 'blocks' => [
        ['id' => 'retained', 'type' => 'roi', 'title' => 'Saved ROI'],
        ['id' => 'editable', 'type' => 'text', 'content' => 'Before'],
    ]]]];
    $new = $old;
    $new['columns'][0]['blocks'][1]['content'] = 'After';
    expect($guard->reconcile($new, $old)['columns'][0]['blocks'][0])->toBe($old['columns'][0]['blocks'][0]);
    $new['columns'][0]['blocks'][0]['title'] = 'Tampered';
    expect(fn () => $guard->reconcile($new, $old))->toThrow(ValidationException::class)
        ->and(fn () => $guard->reconcile(['type' => 'roi']))->toThrow(ValidationException::class);
});

test('adding columns cannot bypass a stored unavailable block', function (): void {
    app(BlockPackageManager::class)->setEnabled('charts', false);
    $stored = ['id' => 10, 'type' => 'roi', 'title' => 'Keep'];
    $changed = $stored + ['columns' => [['blocks' => [['type' => 'text', 'content' => 'Replacement']]]]];
    expect(fn () => app(UnavailableBlockGuard::class)->reconcile($changed, $stored))->toThrow(ValidationException::class);
});

test('omitting unavailable nested content restores the stored node', function (): void {
    app(BlockPackageManager::class)->setEnabled('charts', false);
    $old = ['type' => 'text', 'columns' => [['id' => 'main', 'blocks' => [['id' => 'saved', 'type' => 'roi', 'title' => 'Keep']]]]];
    $new = ['type' => 'text', 'columns' => [['id' => 'main', 'blocks' => []]]];
    expect(app(UnavailableBlockGuard::class)->reconcile($new, $old)['columns'][0]['blocks'])->toBe($old['columns'][0]['blocks']);
});

test('unavailable blocks without identities survive deletion and reordering of active siblings', function (bool $keepActiveSibling): void {
    app(BlockPackageManager::class)->setEnabled('charts', false);
    $active = ['type' => 'text', 'content' => 'Editable'];
    $unavailable = ['type' => 'roi', 'title' => 'Keep'];
    $stored = ['type' => 'text', 'columns' => [['id' => 'main', 'blocks' => [$active, $unavailable]]]];
    $children = $keepActiveSibling ? [$unavailable, $active] : [$unavailable];
    $incoming = ['type' => 'text', 'columns' => [['id' => 'main', 'blocks' => $children]]];

    expect(app(UnavailableBlockGuard::class)->reconcile($incoming, $stored)['columns'][0]['blocks'])->toBe($children);
})->with(['delete active sibling' => false, 'reorder active sibling' => true]);

test('a stored unavailable block cannot authorize duplicate insertion', function (bool $withIdentity): void {
    app(BlockPackageManager::class)->setEnabled('charts', false);
    $unavailable = ['type' => 'roi', 'title' => 'Keep'];
    if ($withIdentity) {
        $unavailable['id'] = 'saved';
    }
    $stored = ['type' => 'text', 'columns' => [['id' => 'main', 'blocks' => [$unavailable]]]];
    $incoming = ['type' => 'text', 'columns' => [['id' => 'main', 'blocks' => [$unavailable, $unavailable]]]];

    expect(fn () => app(UnavailableBlockGuard::class)->reconcile($incoming, $stored))->toThrow(ValidationException::class);
})->with(['without identity' => false, 'with identity' => true]);

test('omitting a nested layout preserves its unavailable descendants', function (): void {
    app(BlockPackageManager::class)->setEnabled('charts', false);
    $nested = ['id' => 'layout', 'type' => 'text', 'columns' => [['id' => 'inner', 'blocks' => [
        ['id' => 'saved', 'type' => 'roi', 'title' => 'Keep'],
    ]]]];
    $old = ['type' => 'text', 'columns' => [['id' => 'main', 'blocks' => [$nested]]]];
    $new = ['type' => 'text', 'columns' => [['id' => 'main', 'blocks' => []]]];

    expect(app(UnavailableBlockGuard::class)->reconcile($new, $old)['columns'][0]['blocks'])->toBe([$nested]);
});

test('saving a page while its package is disabled preserves its stored sections and translations', function (): void {
    $page = Page::create(['template' => 'default', 'is_published' => false]);
    $sync = app(SectionSyncService::class);
    app(BlockPackageManager::class)->setEnabled('charts', true);
    $sync->sync($page, json_encode(['es' => [['type' => 'roi', 'title' => 'Retained']]], JSON_THROW_ON_ERROR));
    $before = app(SectionEditorService::class)->payload($page);
    app(BlockPackageManager::class)->setEnabled('charts', false);
    $sync->sync($page, json_encode(['es' => [['type' => 'text', 'content' => 'New section']]], JSON_THROW_ON_ERROR));
    $after = app(SectionEditorService::class)->payload($page);
    expect(collect($after['es'])->firstWhere('id', $before['es'][0]['id'])['title'])->toBe('Retained');
});

test('section writes require explicit locale keys and preserve stored content on invalid input', function (): void {
    $page = Page::create(['template' => 'default', 'is_published' => false]);
    $sync = app(SectionSyncService::class);
    $sync->sync($page, json_encode(['es' => [['type' => 'text', 'content' => 'Stored']]], JSON_THROW_ON_ERROR));
    $before = app(SectionEditorService::class)->payload($page);

    expect(fn () => $sync->sync($page, json_encode([['type' => 'text', 'content' => 'Ambiguous']], JSON_THROW_ON_ERROR)))
        ->toThrow(ValidationException::class);
    expect(app(SectionEditorService::class)->payload($page->fresh()))->toBe($before);
});

test('malformed section locale payloads cannot silently erase existing content', function (mixed $invalid): void {
    $page = Page::create(['template' => 'default', 'is_published' => false]);
    $sync = app(SectionSyncService::class);
    $sync->sync($page, json_encode(['es' => [['type' => 'text', 'content' => 'Stored']]], JSON_THROW_ON_ERROR));
    $before = app(SectionEditorService::class)->payload($page);

    expect(fn () => $sync->sync($page, json_encode($invalid, JSON_THROW_ON_ERROR)))->toThrow(ValidationException::class);
    expect(app(SectionEditorService::class)->payload($page->fresh()))->toBe($before);
})->with([
    'unknown locale' => [['unknown' => [['type' => 'text', 'content' => 'Replacement']]]],
    'invalid locale list' => [['es' => 'invalid']],
    'invalid section' => [['es' => ['invalid']]],
    'empty block' => [['es' => [[]]]],
    'scalar payload' => ['invalid'],
    'scalar columns' => [['es' => [['type' => 'text', 'columns' => 'invalid']]]],
    'column object instead of list' => [['es' => [['type' => 'text', 'columns' => ['blocks' => []]]]]],
    'invalid column' => [['es' => [['type' => 'text', 'columns' => [123]]]]],
    'null column blocks' => [['es' => [['type' => 'text', 'columns' => [['blocks' => null]]]]]],
    'block object instead of list' => [['es' => [['type' => 'text', 'columns' => [['blocks' => ['type' => 'text']]]]]]],
    'invalid nested block' => [['es' => [['type' => 'text', 'columns' => [['blocks' => [123]]]]]]],
    'invalid descendant block' => [['es' => [['type' => 'text', 'columns' => [['blocks' => [
        ['type' => 'text', 'columns' => [['blocks' => [123]]]],
    ]]]]]]],
]);
