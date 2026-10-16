<?php

declare(strict_types=1);

namespace App\Services\Projects;

use App\Content\Pages\Models\Page;
use App\Contracts\Services\GeneralSettingsServiceInterface;
use App\Contracts\Services\LocalizationServiceInterface;
use App\Core\Blocks\BlockPackageManager;
use App\Core\Modules\ModuleManager;
use App\Models\CmsModule;
use App\Models\Language;
use App\Models\Market;
use App\Models\Menu;
use App\Models\Setting;
use App\Models\User;
use App\Services\Cms\PageCacheService;
use App\Services\Cms\ThemeManagerService;
use App\Services\Content\SafeContentRenderer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\Post;

/** Explicit project provisioning; never called by core boot or foundation seeding. */
final class AiLearningStarterService
{
    public const MARKER = 'starter.ia-practica.installed';

    public function __construct(
        private readonly ModuleManager $modules,
        private readonly BlockPackageManager $packages,
        private readonly LocalizationServiceInterface $localization,
        private readonly GeneralSettingsServiceInterface $settings,
        private readonly ThemeManagerService $themes,
        private readonly SafeContentRenderer $renderer,
    ) {}

    /** Returns false for a completed starter, preserving all subsequent edits. */
    public function install(string $authorEmail, string $marketCode = 'es'): bool
    {
        return Cache::lock('starter:ia-practica:'.hash('sha256', (string) DB::connection()->getDatabaseName()), 180)->block(10, function () use ($authorEmail, $marketCode): bool {
            if (Setting::query()->where('key', self::MARKER)->exists()) {
                return false;
            }
            $this->assertEmptyEditorialInstallation();
            $author = User::query()->where('email', $authorEmail)->first();
            if ($author === null || ! $author->isAdmin()) {
                throw new \DomainException('Indica con --author el correo de un administrador existente.');
            }
            $market = Market::query()->where('code', $marketCode)->where('is_active', true)->first();
            if ($market === null || ! Language::query()->where('code', 'es')->where('is_active', true)->exists()
                || ! $market->languages()->where('code', 'es')->where('is_active', true)->exists()) {
                throw new \DomainException('El starter requiere un mercado activo con el idioma español activo.');
            }
            if ($this->modules->getModuleManifest('blog') === null || ! isset($this->packages->all()['basic'])
                || ! isset($this->themes->getAvailableThemes()['ia-practica'])) {
                throw new \DomainException('Instala los archivos de Blog, Basic y del tema ia-practica antes de ejecutar el starter.');
            }
            $catalogue = $this->catalogue();
            $urls = $this->urls($catalogue, $market);
            // Validate and read every resource before module migration or content writes.
            $bodies = [];
            foreach ([...$catalogue['pages'], ...$catalogue['articles']] as $entry) {
                $bodies[$entry['slug']] = strtr(File::get(resource_path('starters/ia-practica/'.$entry['file'])), $urls);
                if (str_contains($bodies[$entry['slug']], '{{url:')) {
                    throw new \DomainException('El catálogo contiene un enlace interno sin resolver.');
                }
            }
            // MySQL DDL cannot be rolled back. Complete explicit extension setup first;
            // editorial content and its completion marker remain one atomic transaction.
            if (! CmsModule::query()->where('identifier', 'blog')->where('is_installed', true)->exists()) {
                $this->modules->install('blog');
            } elseif (! $this->modules->isModuleEnabled('blog')) {
                $this->modules->activate('blog');
            }
            try {
                DB::transaction(function () use ($catalogue, $bodies, $urls, $market, $author): void {
                    $this->assertEmptyEditorialInstallation();
                    $this->packages->setEnabled('basic', true);
                    $categories = [];
                    foreach ($catalogue['categories'] as $entry) {
                        $category = Category::query()->create(['is_active' => true]);
                        $category->translations()->create(['locale' => 'es', 'name' => $entry['name'], 'slug' => $entry['slug'], 'description' => $entry['description']]);
                        $category->markets()->sync([$market->id]);
                        $categories[$entry['slug']] = $category->id;
                    }
                    foreach ($catalogue['pages'] as $entry) {
                        $metaTitle = $entry['title'] === 'IA en práctica' ? $entry['title'] : $entry['title'].' | IA en práctica';
                        $page = Page::query()->create(['template' => 'default', 'layout' => 'default', 'is_published' => true]);
                        $page->translations()->create(['locale' => 'es', 'title' => $entry['title'], 'slug' => $entry['slug'], 'meta_title' => $metaTitle, 'meta_description' => $entry['description'], 'schema_type' => 'WebPage']);
                        $page->markets()->sync([$market->id]);
                        $hero = $page->sections()->create(['type' => 'hero', 'order' => 0, 'is_active' => true, 'status' => 'published', 'published_at' => now()]);
                        $payload = ['title' => $entry['slug'] === 'home' ? 'La IA se aprende haciendo.' : $entry['title'], 'content' => $entry['description'], 'badge' => $entry['slug'] === 'home' ? 'Criterio humano. Práctica compartida.' : 'IA en práctica'];
                        if ($entry['slug'] === 'home') {
                            $payload += ['cta_text' => 'Empieza por una tarea', 'cta_url' => $urls['{{url:ruta-de-aprendizaje}}'], 'secondary_cta_text' => 'Explora las cheatsheets', 'secondary_cta_url' => $urls['{{url:cheatsheets}}']];
                        }
                        $hero->translations()->create(['locale' => 'es', 'content_json' => $payload]);
                        $section = $page->sections()->create(['type' => 'text', 'order' => 1, 'is_active' => true, 'status' => 'published', 'published_at' => now()]);
                        $section->translations()->create(['locale' => 'es', 'content_json' => ['title' => '', 'format' => 'markdown', 'content' => $bodies[$entry['slug']]]]);
                        if ($entry['slug'] === 'home') {
                            $this->createGuideCards($page, $catalogue['articles'], $urls);
                        }
                    }
                    foreach ($catalogue['articles'] as $entry) {
                        $post = Post::query()->create(['user_id' => $author->id, 'category_id' => $categories[$entry['category']], 'is_published' => true, 'is_featured' => true, 'allow_comments' => false, 'reading_time_minutes' => max(1, (int) ceil(str_word_count($bodies[$entry['slug']]) / 180))]);
                        $post->markets()->sync([$market->id]);
                        $post->translations()->create(['locale' => 'es', 'title' => $entry['title'], 'slug' => $entry['slug'], 'summary' => $entry['summary'], 'content' => $this->renderer->markdown($bodies[$entry['slug']]), 'meta_title' => $entry['title'], 'meta_description' => $entry['summary'], 'schema_type' => 'Article']);
                    }
                    $this->createMenu('main', 'header', $market, ['ruta-de-aprendizaje' => 'Aprende', 'cheatsheets' => 'Cheatsheets', 'blog' => 'Artículos', 'metodologia' => 'Nuestro método'], $urls);
                    $this->createMenu('footer', 'footer_quick', $market, ['ruta-de-aprendizaje' => 'Ruta de aprendizaje', 'cheatsheets' => 'Cheatsheets por equipo', 'metodologia' => 'Metodología editorial'], $urls);
                    $this->settings->setMany([
                        'general.site_name' => 'IA en práctica',
                        'general.site_description' => 'Formación práctica en inteligencia artificial, guías por tarea y criterio humano para trabajar entre equipos.',
                        'general.og_image' => '/images/ia-practica-social.png',
                        'general.site_favicon' => '/images/ia-practica-mark.svg',
                    ]);
                    $this->themes->setActiveTheme('ia-practica');
                    $this->settings->set(self::MARKER, now()->toIso8601String(), 'starter');
                });
            } finally {
                $this->settings->flushCache();
                app(PageCacheService::class)->invalidate();
            }

            return true;
        });
    }

    private function assertEmptyEditorialInstallation(): void
    {
        foreach (['pages', 'menus', 'posts', 'categories'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new \DomainException('El starter requiere una instalación editorial vacía. No se ha sobrescrito contenido.');
            }
        }
    }

    /** @return array{categories:list<array{slug:string,name:string,description:string}>,pages:list<array{slug:string,title:string,description:string,file:string}>,articles:list<array{slug:string,title:string,summary:string,category:string,file:string}>} */
    private function catalogue(): array
    {
        /** @var array{categories:list<array{slug:string,name:string,description:string}>,pages:list<array{slug:string,title:string,description:string,file:string}>,articles:list<array{slug:string,title:string,summary:string,category:string,file:string}>}|null $catalogue */
        $catalogue = json_decode(File::get(resource_path('starters/ia-practica/catalogue.json')), true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($catalogue)) {
            throw new \DomainException('El catálogo del starter no es válido.');
        }

        return $catalogue;
    }

    /** @param array{pages:list<array{slug:string}>,articles:list<array{slug:string}>} $catalogue
     * @return array<string,string>
     */
    private function urls(array $catalogue, Market $market): array
    {
        $urls = ['{{url:blog}}' => $this->localization->frontUrl('blog', '', 'es', $market)];
        foreach ($catalogue['pages'] as $entry) {
            $urls['{{url:'.$entry['slug'].'}}'] = $this->localization->url($entry['slug'] === 'home' ? '/' : '/'.$entry['slug'], $market, 'es');
        }
        foreach ($catalogue['articles'] as $entry) {
            $urls['{{url:'.$entry['slug'].'}}'] = $this->localization->frontUrl('blog', $entry['slug'], 'es', $market);
        }

        return $urls;
    }

    /** @param list<array{slug:string,title:string,summary:string}> $articles
     * @param  array<string,string>  $urls
     */
    private function createGuideCards(Page $page, array $articles, array $urls): void
    {
        $section = $page->sections()->create(['type' => 'features', 'order' => 2, 'is_active' => true, 'status' => 'published', 'published_at' => now()]);
        $items = array_map(static fn (array $entry): array => ['title' => $entry['title'], 'description' => $entry['summary'], 'url' => $urls['{{url:'.$entry['slug'].'}}'], 'cta_label' => 'Abrir la guía'], $articles);
        $section->translations()->create(['locale' => 'es', 'content_json' => ['title' => 'Una guía para tu próxima tarea', 'grid_columns' => '2', 'items' => $items]]);
    }

    /** @param array<string,string> $links
     * @param  array<string,string>  $urls
     */
    private function createMenu(string $slug, string $location, Market $market, array $links, array $urls): void
    {
        $menu = Menu::query()->create(['slug' => $slug, 'name' => $slug === 'main' ? 'Navegación principal' : 'Recursos', 'location' => $location, 'is_active' => true]);
        $menu->markets()->sync([$market->id]);
        $order = 0;
        foreach ($links as $target => $title) {
            $item = $menu->items()->create(['type' => 'custom', 'url' => $urls['{{url:'.$target.'}}'], 'order' => $order++, 'is_active' => true, 'target_blank' => false]);
            $item->translations()->create(['locale' => 'es', 'title' => $title]);
        }
    }
}
