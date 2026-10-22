<?php

declare(strict_types=1);

use App\Core\Blocks\BlockPackageManager;
use App\Core\Blocks\Contracts\BlockRegistryInterface;
use App\Core\Sections\SectionRendererService;
use Illuminate\Validation\ValidationException;

test('bundled visual packages have a single catalogue and hide without deleting their definitions', function (): void {
    $packages = app(BlockPackageManager::class);
    $registry = app(BlockRegistryInterface::class);
    expect($registry->get('roi'))->not->toBeNull();
    $packages->setEnabled('charts', true);
    expect($registry->isAvailable('roi'))->toBeTrue();
    $packages->setEnabled('charts', false);
    expect($registry->get('roi'))->not->toBeNull()
        ->and($registry->isAvailable('roi'))->toBeFalse()
        ->and(app(SectionRendererService::class)->renderBlockContent('roi', []))->toBe('')
        ->and(app(SectionRendererService::class)->renderSection(['type' => 'text', 'columns' => [['blocks' => [['type' => 'roi']]]]]))->toBe('');
    $packages->setEnabled('charts', true);
    expect($registry->isAvailable('roi'))->toBeTrue();
});

test('bundled packages cannot be uninstalled', function (): void {
    expect(fn () => app(BlockPackageManager::class)->uninstall('charts'))->toThrow(ValidationException::class);
});

test('the editor catalogue includes schemas and hides unavailable definitions', function (): void {
    app(BlockPackageManager::class)->setEnabled('before_after', false);
    $registry = app(BlockRegistryInterface::class);
    expect($registry->get('hero')->getEditorSchema())->toHaveKey('title')
        ->and($registry->getActiveBlocks())->not->toHaveKey('before_after')
        ->and($registry->all())->toHaveKey('before_after');
});

test('the bundled basic package uses the same lifecycle as uploaded visual extensions', function (): void {
    $packages = app(BlockPackageManager::class);
    $registry = app(BlockRegistryInterface::class);
    expect($packages->all())->toHaveKey('basic');
    $packages->setEnabled('basic', false);
    expect($registry->isAvailable('text'))->toBeFalse()
        ->and($registry->get('text'))->not->toBeNull()
        ->and($registry->render('hero', ['title' => 'Hidden']))->toBe('');
    expect(fn () => $registry->validate('text', ['content' => 'New']))->toThrow(ValidationException::class);
    expect(fn () => $packages->uninstall('basic'))->toThrow(ValidationException::class);
    $packages->setEnabled('basic', true);
    expect($registry->isAvailable('text'))->toBeTrue();
});

test('block validation checks repeater item shapes and field values before rendering', function (): void {
    $registry = app(BlockRegistryInterface::class);
    app(BlockPackageManager::class)->setEnabled('charts', true);

    $registry->validate('charts', ['items' => [['label' => 'Revenue', 'value' => 42]]]);
    expect(fn () => $registry->validate('charts', ['items' => [['value' => 'invalid']]]))->toThrow(ValidationException::class)
        ->and(fn () => $registry->validate('charts', ['items' => ['invalid']]))->toThrow(ValidationException::class);
});
