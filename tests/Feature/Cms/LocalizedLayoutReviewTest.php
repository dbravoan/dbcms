<?php

declare(strict_types=1);

use App\Content\Pages\Contracts\PageDomainServiceInterface;
use App\Content\Pages\Models\Page;
use App\Core\Sections\SectionEditorService;
use App\Models\Market;
use App\Models\User;
use Modules\ContentAudit\Services\EditorialWorkflow;
use Tests\Support\CmsFixtures;
use Tests\Support\ContentAuditComposition;

uses(ContentAuditComposition::class);

beforeEach(function (): void {
    CmsFixtures::localization(['es', 'en', 'ca']);
    CmsFixtures::identities();
    $this->marketEs = Market::where('code', 'es')->firstOrFail();
    $this->admin = User::where('email', 'admin@db-cms.io')->firstOrFail();
});

test('saving explicit localized layouts preserves every requested language through review', function (): void {
    $page = Page::create([
        'template' => 'default',
        'layout' => 'app',
        'is_published' => true,
    ]);
    $page->markets()->sync([$this->marketEs->id]);

    $liveSections = app(SectionEditorService::class)->payload($page);

    $payload = [
        'template' => 'default',
        'layout' => 'app',
        'market_ids' => [$this->marketEs->id],
        'translations' => [
            'es' => ['title' => 'Página Multilingüe', 'slug' => 'pagina-multi'],
            'en' => ['title' => 'Multilingual Page', 'slug' => 'multilingual-page'],
            'ca' => ['title' => 'Pàgina Multilingüe', 'slug' => 'pagina-multilingue'],
        ],
        'sections_json' => json_encode(array_fill_keys(['es', 'en', 'ca'], [
            ['type' => 'hero', 'title' => 'Hero Compartido', 'content' => 'Subtítulo base'],
            ['type' => 'features', 'title' => 'Características Base'],
        ]), JSON_THROW_ON_ERROR),
    ];

    $response = $this->actingAs($this->admin, 'web')->put(route('admin.pages.update', $page), $payload);
    $response->assertRedirect(route('admin.pages.index'));

    $response->assertSessionHasNoErrors();
    $live = $page->fresh();
    expect($live->is_published)->toBeTrue();
    expect(app(SectionEditorService::class)->payload($live))->toBe($liveSections);
    $page = app(PageDomainServiceInterface::class)->editData($live)['page'];
    $pendingSections = app(SectionEditorService::class)->payload($page);
    expect($page->sections)->toHaveCount(2);

    $heroSection = $page->sections->where('type', 'hero')->first();
    expect($heroSection)->not->toBeNull();
    // Verify each active language has a SectionTranslation
    expect($heroSection->translations)->toHaveCount(3);
    expect($heroSection->translations->pluck('locale')->all())->toEqualCanonicalizing(['es', 'en', 'ca']);

    $featuresSection = $page->sections->where('type', 'features')->first();
    expect($featuresSection)->not->toBeNull();
    expect($featuresSection->translations)->toHaveCount(3);
    expect($featuresSection->translations->pluck('locale')->all())->toEqualCanonicalizing(['es', 'en', 'ca']);
    app(EditorialWorkflow::class)->approve($live, $this->admin);
    expect(app(SectionEditorService::class)->payload($live->fresh()))->toBe($pendingSections);
    expect($live->fresh()->is_published)->toBeTrue();
});
