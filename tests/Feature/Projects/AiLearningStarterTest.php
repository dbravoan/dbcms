<?php

declare(strict_types=1);

use App\Content\Pages\Models\Page;
use App\Content\Pages\Models\PageSection;
use App\Contracts\Services\GeneralSettingsServiceInterface;
use App\Contracts\Services\LocalizationServiceInterface;
use App\Core\Blocks\BlockPackageManager;
use App\Core\Modules\ModuleManager;
use App\Models\CmsModule;
use App\Models\Market;
use App\Models\Menu;
use App\Models\Setting;
use App\Models\User;
use App\Services\Projects\AiLearningStarterService;
use Illuminate\Support\Facades\Event;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\Post;
use Tests\Support\BlogComposition;
use Tests\Support\CmsFixtures;

uses(BlogComposition::class);

beforeEach(function (): void {
    CmsFixtures::localization(['es'], ['es']);
    CmsFixtures::identities();
    $this->author = User::query()->where('email', 'admin@db-cms.io')->firstOrFail();
    $this->market = Market::query()->where('code', 'es')->firstOrFail();
});

it('provisions editable practical guides and follows their real public links', function (): void {
    $this->artisan('cms:starter:ai-learning', ['--author' => $this->author->email])->assertSuccessful();
    $localization = app(LocalizationServiceInterface::class);
    $home = $this->get($localization->url('/', $this->market, 'es'))->assertOk()->assertSee('La IA se aprende haciendo.');
    expect($home->viewData('seoMeta')->title)->toBe('IA en práctica');
    $document = new DOMDocument;
    $document->loadHTML($home->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    expect($xpath->evaluate('string(//meta[@property="og:image"]/@content)'))->toBe(url('/images/ia-practica-social.png'));
    expect($xpath->evaluate('string(//meta[@name="twitter:image"]/@content)'))->toBe(url('/images/ia-practica-social.png'));
    expect($xpath->evaluate('string(//link[@rel="icon"]/@href)'))->toBe('/images/ia-practica-mark.svg');
    expect(is_file(public_path('images/ia-practica-mark.svg')))->toBeTrue();
    expect(getimagesize(public_path('images/ia-practica-social.png')))->toMatchArray([0 => 1200, 1 => 630, 2 => IMAGETYPE_PNG]);
    $catalogue = json_decode(file_get_contents(resource_path('starters/ia-practica/catalogue.json')), true, flags: JSON_THROW_ON_ERROR);
    foreach ($catalogue['articles'] as $entry) {
        $url = $localization->frontUrl('blog', $entry['slug'], 'es', $this->market);
        $home->assertSee($url, false);
        $this->get($url)->assertOk()->assertSee($entry['title'])->assertSee('Prompt de ejemplo')->assertSee('Verificación humana')->assertSee('Entregable')->assertSee('Límites');
        $post = Post::query()->whereHas('translations', fn ($query) => $query->where('slug', $entry['slug']))->firstOrFail();
        $this->actingAs($this->author)->get(route('admin.blog.posts.edit', $post))->assertOk()->assertSee($entry['title']);
    }
    foreach (['ruta-de-aprendizaje', 'cheatsheets', 'metodologia'] as $slug) {
        $this->get($localization->url('/'.$slug, $this->market, 'es'))->assertOk();
    }
    expect(app(BlockPackageManager::class)->isEnabled('basic'))->toBeTrue();
    expect(Setting::getValue('cms.active_theme'))->toBe('ia-practica');
});

it('preserves edited content and navigation when the starter is repeated', function (): void {
    $starter = app(AiLearningStarterService::class);
    expect($starter->install($this->author->email))->toBeTrue();
    $translation = Post::query()->firstOrFail()->translations()->firstOrFail();
    $translation->update(['title' => 'Edición revisada por el equipo', 'content' => '<p>Nuestro contenido corregido.</p>']);
    $pageTranslation = Page::query()->firstOrFail()->translations()->firstOrFail();
    $pageTranslation->update(['meta_title' => 'Nuestro título editorial propio']);
    $section = PageSection::query()->where('type', 'text')->firstOrFail()->translations()->firstOrFail();
    $section->update(['content_json' => ['title' => '', 'format' => 'markdown', 'content' => 'Orientación propia.']]);
    $menu = Menu::query()->where('slug', 'main')->firstOrFail();
    $menu->update(['name' => 'Navegación revisada']);
    app(GeneralSettingsServiceInterface::class)->setMany([
        'general.og_image' => '/uploads/revised-social.png',
        'general.site_favicon' => '/uploads/revised-mark.svg',
    ]);
    expect($starter->install('author-no-longer-needed@example.test'))->toBeFalse();
    expect($translation->fresh()->title)->toBe('Edición revisada por el equipo');
    expect($pageTranslation->fresh()->meta_title)->toBe('Nuestro título editorial propio');
    expect($translation->fresh()->content)->toBe('<p>Nuestro contenido corregido.</p>');
    expect($section->fresh()->content_json['content'])->toBe('Orientación propia.');
    expect($menu->fresh()->name)->toBe('Navegación revisada');
    expect(Setting::getValue('general.og_image'))->toBe('/uploads/revised-social.png');
    expect(Setting::getValue('general.site_favicon'))->toBe('/uploads/revised-mark.svg');
});

it('rejects an occupied installation before changing module or theme state', function (): void {
    $page = CmsFixtures::home(['es'], ['es']);
    CmsModule::query()->where('identifier', 'blog')->update(['is_active' => false]);
    $this->artisan('cms:starter:ai-learning', ['--author' => $this->author->email])->expectsOutputToContain('instalación editorial vacía')->assertFailed();
    expect($page->fresh()->translations()->firstOrFail()->title)->toBe('Fixture home');
    expect(CmsModule::query()->where('identifier', 'blog')->firstOrFail()->is_active)->toBeFalse();
    expect(Setting::query()->where('key', AiLearningStarterService::MARKER)->exists())->toBeFalse();
    expect(Post::query()->exists())->toBeFalse();
});

it('rolls back every editorial write when a section cannot be created', function (): void {
    $event = 'eloquent.creating: '.PageSection::class;
    Event::listen($event, static function (PageSection $section): void {
        if ($section->type === 'text') {
            throw new RuntimeException('Storage boundary failed');
        }
    });
    try {
        expect(fn () => app(AiLearningStarterService::class)->install($this->author->email))->toThrow(RuntimeException::class, 'Storage boundary failed');
    } finally {
        Event::forget($event);
    }
    expect(Page::query()->exists())->toBeFalse();
    expect(Category::query()->exists())->toBeFalse();
    expect(Post::query()->exists())->toBeFalse();
    expect(Menu::query()->exists())->toBeFalse();
    expect(Setting::query()->where('key', AiLearningStarterService::MARKER)->exists())->toBeFalse();
});

it('keeps pages readable while disabled Blog guides disappear from public discovery', function (): void {
    app(AiLearningStarterService::class)->install($this->author->email);
    $localization = app(LocalizationServiceInterface::class);
    $url = $localization->frontUrl('blog', 'preparar-una-reunion-comercial', 'es', $this->market);
    $this->get($url)->assertOk();
    app(ModuleManager::class)->deactivate('blog');
    $this->get($url)->assertNotFound();
    $this->get($localization->url('/metodologia', $this->market, 'es'))->assertOk();
    $this->get('/sitemap.xml')->assertOk()->assertDontSee('preparar-una-reunion-comercial');
    $this->get('/llms-full.txt')->assertOk()->assertDontSee('Con estas notas, prepara una agenda y cinco preguntas abiertas.');
    expect(Post::query()->whereHas('translations', fn ($query) => $query->where('slug', 'preparar-una-reunion-comercial'))->exists())->toBeTrue();
});
