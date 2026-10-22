<?php

declare(strict_types=1);

use App\Core\Search\SearchIndexer;
use App\Models\Market;
use App\Models\User;
use App\Services\Capabilities\EntityMutationService;
use Modules\AiAssistant\Services\EditorialGuidanceService;
use Tests\Support\CmsFixtures;
use Tests\Support\EditorialGuidanceComposition;

uses(EditorialGuidanceComposition::class);

beforeEach(function (): void {
    CmsFixtures::localization();
    CmsFixtures::identities();
    $this->admin = User::where('email', 'admin@db-cms.io')->firstOrFail();
});

test('editorial research suggests public content from the requested market and excludes drafts', function (): void {
    $market = Market::where('code', 'es')->firstOrFail();
    foreach ([true, false] as $published) {
        app(EntityMutationService::class)->create('post', [
            'publish' => $published,
            'market_ids' => [$market->id],
            'translations' => ['es' => ['title' => $published ? 'Documento público' : 'Documento privado', 'content' => 'Documentación editorial.']],
        ], $this->admin);
    }
    app(SearchIndexer::class)->indexAll();

    $research = app(EditorialGuidanceService::class)->research(['topic' => 'documento', 'market' => 'es', 'locale' => 'es']);

    expect($research['existing_coverage_count'])->toBe(1)
        ->and(array_column($research['suggested_internal_links'], 'title'))->toBe(['Documento público']);
});

test('the editorial guide is available to authorized users in the requested language', function (): void {
    $route = route('admin.ai.download-prompt', ['locale' => 'en']);
    $this->get($route)->assertRedirect();
    $this->actingAs($this->admin)->get($route)->assertDownload('EDITORIAL_HUMAN_AI_SYSTEM_PROMPT.md');
});
