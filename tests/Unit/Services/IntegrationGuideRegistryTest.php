<?php

declare(strict_types=1);

use App\Core\Integrations\IntegrationGuideRegistry;
use App\DTOs\Integrations\GuideStep;
use App\DTOs\Integrations\SetupGuideDescriptor;

test('tutorial descriptors reject executable URLs and repeated step identities', function (): void {
    expect(fn () => new GuideStep('test', 'Title', 'External', 'CMS', 'Result', ['Bad' => 'javascript:alert(1)']))->toThrow(InvalidArgumentException::class);
    $step = new GuideStep('same', 'Title', 'External', 'CMS', 'Result');
    expect(fn () => new SetupGuideDescriptor('guide', 'platform', 'Title', [], [$step, $step], [], [], '2026-10-13'))->toThrow(InvalidArgumentException::class);
});

test('registry keeps module namespaces independent and refuses accidental descriptor replacement', function (): void {
    $registry = new IntegrationGuideRegistry;
    $guide = new SetupGuideDescriptor('guide', 'platform', 'Title', [], [new GuideStep('test', 'Title', 'External', 'CMS', 'Result')], [], [], '2026-10-13');
    $registry->register('one', [$guide]);
    $registry->register('two', [$guide]);
    expect($registry->find('one', 'guide'))->toBe($guide)->and($registry->all())->toHaveKeys(['one', 'two']);
    expect(fn () => $registry->register('one', [$guide]))->toThrow(LogicException::class);
});

test('shared tutorial controls provide matching Spanish and English translations', function (): void {
    $root = dirname(__DIR__, 3);
    $spanish = require $root.'/lang/es/integrations.php';
    $english = require $root.'/lang/en/integrations.php';
    expect(array_keys($english))->toBe(array_keys($spanish));
    foreach ($english as $translation) {
        expect($translation)->toBeString()->not->toBe('');
    }
});
