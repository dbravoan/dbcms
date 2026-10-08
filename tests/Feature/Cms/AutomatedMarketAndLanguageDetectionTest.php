<?php

declare(strict_types=1);

use App\Core\Sections\SectionEditorService;
use App\Models\Language;
use App\Models\Market;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ContentAudit\Contracts\Services\ContentAuditServiceInterface;
use Modules\Pages\Contracts\PageDomainServiceInterface;
use Modules\Pages\Models\Page;
use Modules\Pages\Models\PageSection;
use Modules\Pages\Models\PageTranslation;
use Modules\Pages\Models\SectionTranslation;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->spanish = Language::firstOrCreate(['code' => 'es'], [
        'name' => 'Spanish',
        'native_name' => 'Español',
        'is_default' => true,
        'is_active' => true,
    ]);

    $this->english = Language::firstOrCreate(['code' => 'en'], [
        'name' => 'English',
        'native_name' => 'English',
        'is_default' => false,
        'is_active' => true,
    ]);

    $this->catalan = Language::firstOrCreate(['code' => 'ca'], [
        'name' => 'Catalan',
        'native_name' => 'Català',
        'is_default' => false,
        'is_active' => true,
    ]);

    $this->marketEs = Market::firstOrCreate(['code' => 'es'], [
        'name' => 'Spain',
        'default_locale' => 'es',
        'is_default' => true,
        'is_active' => true,
    ]);

    $this->marketEs->languages()->sync([
        $this->spanish->id => ['is_default' => true],
        $this->catalan->id => ['is_default' => false],
        $this->english->id => ['is_default' => false],
    ]);

    $this->admin = User::create([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'password' => bcrypt('password123'),
    ]);

    $adminRole = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
    $this->admin->assignRole($adminRole);
});

test('backoffice displays all dynamically configured languages in market manager', function (): void {
    $response = $this->actingAs($this->admin, 'web')->get(route('admin.markets.index'));

    $response->assertStatus(200)
        ->assertSee('Spanish')
        ->assertSee('English')
        ->assertSee('Catalan')
        ->assertSee('Idiomas del Sistema (Detección Dinámica)');
});

test('admin can store and toggle a new language dynamically', function (): void {
    $storeResponse = $this->actingAs($this->admin, 'web')->post(route('admin.languages.store'), [
        'code' => 'fr',
        'name' => 'French',
        'native_name' => 'Français',
        'is_active' => 1,
    ]);

    $storeResponse->assertRedirect(route('admin.markets.index'));
    $this->assertDatabaseHas('languages', ['code' => 'fr', 'name' => 'French']);

    $french = Language::where('code', 'fr')->firstOrFail();

    // Toggle active state to inactive
    $toggleResponse = $this->actingAs($this->admin, 'web')->post(route('admin.languages.toggle', $french));
    $toggleResponse->assertRedirect(route('admin.markets.index'));
    expect($french->fresh()->is_active)->toBeFalse();
});

test('page editor provides tabs for all active languages and pre-fills defaults', function (): void {
    $page = Page::create([
        'template' => 'default',
        'layout' => 'app',
        'is_published' => true,
    ]);
    $page->markets()->sync([$this->marketEs->id]);

    PageTranslation::create([
        'page_id' => $page->id,
        'locale' => 'es',
        'title' => 'Página Principal',
        'slug' => 'pagina-principal',
    ]);

    $response = $this->actingAs($this->admin, 'web')->get(route('admin.pages.edit', $page));

    $response->assertStatus(200)
        ->assertSee('Spanish (ES)')
        ->assertSee('English (EN)')
        ->assertSee('Catalan (CA)');
});

test('frontoffice resolves authentic catalan content on localized market route', function (): void {
    $page = Page::create([
        'template' => 'default',
        'layout' => 'app',
        'is_published' => true,
        'published_at' => now(),
    ]);
    $page->markets()->sync([$this->marketEs->id]);

    PageTranslation::create([
        'page_id' => $page->id,
        'locale' => 'es',
        'title' => 'Plataforma CMS',
        'slug' => 'home',
    ]);

    PageTranslation::create([
        'page_id' => $page->id,
        'locale' => 'ca',
        'title' => 'Plataforma CMS Català',
        'slug' => 'home',
    ]);

    $hero = PageSection::create([
        'page_id' => $page->id,
        'type' => 'hero',
        'order' => 1,
        'is_active' => true,
    ]);

    SectionTranslation::create([
        'page_section_id' => $hero->id,
        'locale' => 'es',
        'content_json' => [
            'headline' => 'Titular en Castellano',
            'subheadline' => 'Subtítulo en Castellano',
        ],
    ]);

    SectionTranslation::create([
        'page_section_id' => $hero->id,
        'locale' => 'ca',
        'content_json' => [
            'headline' => 'Titular en Català Autèntic',
            'subheadline' => 'Subtítol en Català Autèntic',
        ],
    ]);

    // Test Catalan endpoint
    $responseCa = $this->get('/es/ca');
    $responseCa->assertStatus(200)
        ->assertSee('Titular en Català Autèntic')
        ->assertSee('Subtítol en Català Autèntic')
        ->assertDontSee('Titular en Castellano');

    // Test Spanish endpoint
    $responseEs = $this->get('/es/es');
    $responseEs->assertStatus(200)
        ->assertSee('Titular en Castellano')
        ->assertSee('Subtítulo en Castellano')
        ->assertDontSee('Titular en Català Autèntic');
});

test('saving a page via admin automatically creates shared block layout translations for all active languages', function (): void {
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
        'markets' => [$this->marketEs->id],
        'title_es' => 'Página Multilingüe',
        'slug_es' => 'pagina-multi',
        'translations' => [
            'es' => ['title' => 'Página Multilingüe', 'slug' => 'pagina-multi'],
            'en' => ['title' => 'Multilingual Page', 'slug' => 'multilingual-page'],
            'ca' => ['title' => 'Pàgina Multilingüe', 'slug' => 'pagina-multilingue'],
        ],
        'sections_json' => json_encode([
            [
                'type' => 'hero',
                'headline' => 'Hero Compartido',
                'subheadline' => 'Subtítulo base',
            ],
            [
                'type' => 'features',
                'title' => 'Características Base',
            ],
        ]),
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
    app(ContentAuditServiceInterface::class)->approve($live, $this->admin);
    expect(app(SectionEditorService::class)->payload($live->fresh()))->toBe($pendingSections);
    expect($live->fresh()->is_published)->toBeTrue();
});
