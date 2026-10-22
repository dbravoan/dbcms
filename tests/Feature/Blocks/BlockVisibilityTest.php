<?php

declare(strict_types=1);

use App\Content\Pages\Models\Page;
use App\Content\Pages\Models\PageSection;
use App\Content\Pages\Models\SectionTranslation;
use App\Core\Blocks\BlockPackageManager;
use App\Core\Blocks\Contracts\BlockRegistryInterface;
use App\Core\Content\Contracts\PublicWidgetSemanticContributorInterface;
use App\Core\Content\PublicSemanticRegistry;
use App\Models\Market;
use App\Services\Content\PublishedSectionTextService;
use Modules\AiAssistant\Services\BlockIntelligenceService;
use Tests\Support\AiComposition;

uses(AiComposition::class);

test('unavailable blocks stay out of public semantic text and AI selection', function (): void {
    app(BlockPackageManager::class)->setEnabled('charts', false);
    $section = new PageSection(['type' => 'text', 'styling_json' => ['columns' => [['blocks' => [
        ['type' => 'roi', 'title' => 'Private disabled metric'],
        ['type' => 'text', 'content' => 'Visible authored content'],
    ]]]]]);
    $section->setRelation('translations', collect([new SectionTranslation(['locale' => 'es', 'content_json' => []])]));
    $page = new Page;
    $page->setRelation('publishedSections', collect([$section]));
    $market = new Market(['is_active' => false]);
    $text = app(PublishedSectionTextService::class)->text($page, $market, 'es');
    expect($text)->toContain('Visible authored content')->not->toContain('Private disabled metric');
    $intelligence = app(BlockIntelligenceService::class);
    expect($intelligence->getBlockDefinition('roi'))->toBeNull()
        ->and($intelligence->getBlockTaxonomy())->not->toHaveKey('roi');
    app(BlockPackageManager::class)->setEnabled('charts', true);
    expect(app(PublishedSectionTextService::class)->text($page, $market, 'es'))->toContain('Private disabled metric');
});

test('an unavailable widget never calls its semantic data contributor', function (): void {
    app(BlockPackageManager::class)->setEnabled('charts', false);
    $registry = app(PublicSemanticRegistry::class);
    $contributor = Mockery::mock(PublicWidgetSemanticContributorInterface::class);
    $contributor->shouldReceive('keys')->once()->andReturn(['kpi']);
    $contributor->shouldNotReceive('text');
    $contributor->shouldNotReceive('ownerModule');
    $registry->registerWidget($contributor);
    expect($registry->widgetText(['type' => 'kpi'], new Market(['is_active' => true]), 'es'))->toBe('');
});

test('repeatable editors derive fields from their owner definition', function (): void {
    $registry = app(BlockRegistryInterface::class);
    $schema = $registry->get('pricing')->getEditorSchema();
    expect($schema['items']['type'])->toBe('repeater')
        ->and($schema['items']['fields']['features']['type'])->toBe('list');
    $registry->validate('pricing', ['items' => [['name' => 'Basic', 'features' => ['Feature']]]]);
});
