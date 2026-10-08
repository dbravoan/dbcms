<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Contracts\Services\DatatableQueryServiceInterface;
use App\Contracts\Services\LocalizationServiceInterface;
use App\Contracts\Services\MenuEditorServiceInterface;
use App\Core\Modules\ModuleManager;
use App\DTOs\Admin\DatatableQueryData;
use App\DTOs\Admin\MenuEditorData;
use App\Models\Market;
use App\Models\Menu;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\Post;
use Modules\Pages\Models\Page;
use Modules\Shop\Models\Product;

final class MenuEditorService implements MenuEditorServiceInterface
{
    public function __construct(
        private readonly LocalizationServiceInterface $localization,
        private readonly DatatableQueryServiceInterface $tables,
        private readonly ModuleManager $modules
    ) {}

    public function index(DatatableQueryData $input): MenuEditorData
    {
        $query = Menu::with(['items.translations', 'markets']);

        $menus = $this->tables->paginate(
            $query,
            $input,
            ['name', 'slug', 'location'],
            ['location' => 'location'],
            'created_at',
            'desc'
        );

        $markets = Market::where('is_active', true)->get();

        $availableEntities = [];
        $locales = $this->localization->activeLocales();
        $defaultLocale = $this->localization->defaultLocale();

        $entityTitle = static function (Model $entity, string $locale, string $defaultLocale, string $fallback): string {
            /** @var Collection<int, Model> $translations */
            $translations = $entity->getRelation('translations');

            $translation = $translations->where('locale', $locale)->first()
                ?? $translations->where('locale', $defaultLocale)->first()
                ?? $translations->first();

            $title = (string) ($translation->title ?? $translation->name ?? '');

            return $title !== '' ? $title : $fallback;
        };

        $pushEntity = static function (array $row, array $locales, callable $titleFor) use (&$availableEntities): void {
            $titles = [];
            foreach ($locales as $locale) {
                $titles["title_{$locale}"] = $titleFor($locale);
            }

            $availableEntities[] = $row + $titles;
        };

        // 1. Pages
        try {
            if (class_exists(Page::class) && $this->moduleAvailable(Page::class)) {
                $pages = Page::published()->with('translations')->get();
                foreach ($pages as $page) {
                    $slugTranslation = $page->translations->where('locale', $defaultLocale)->first()
                        ?? $page->translations->first();
                    $slug = $slugTranslation?->slug ?: ($page->template === 'home' ? '' : (string) $page->id);

                    $pushEntity([
                        'type' => 'page',
                        'id' => $page->id,
                        'url' => $page->template === 'home' ? '/' : '/'.ltrim((string) $slug, '/'),
                        'icon' => 'fa-solid fa-file-lines',
                        'badge' => 'PÁGINA',
                    ], $locales, static fn (string $locale): string => $entityTitle($page, $locale, $defaultLocale, 'Página #'.$page->id));
                }
            }
        } catch (\Throwable) {
        }

        // 2. Blog Posts
        try {
            if (class_exists(Post::class) && $this->moduleAvailable(Post::class)) {
                $posts = Post::published()->with('translations')->latest()->take(40)->get();
                $blogPrefix = $this->localization->getModulePrefix('blog', $defaultLocale);

                foreach ($posts as $post) {
                    $slugTranslation = $post->translations->where('locale', $defaultLocale)->first()
                        ?? $post->translations->first();
                    $slug = $slugTranslation?->slug ?: (string) $post->id;

                    $pushEntity([
                        'type' => 'post',
                        'id' => $post->id,
                        'url' => '/'.$blogPrefix.'/'.ltrim((string) $slug, '/'),
                        'icon' => 'fa-solid fa-newspaper',
                        'badge' => 'BLOG',
                    ], $locales, static fn (string $locale): string => $entityTitle($post, $locale, $defaultLocale, 'Post #'.$post->id));
                }
            }
        } catch (\Throwable) {
        }

        // 3. Blog Categories
        try {
            if (class_exists(Category::class) && $this->moduleAvailable(Category::class)) {
                $cats = Category::where('is_active', true)->with('translations')->get();
                $blogPrefix = $this->localization->getModulePrefix('blog', $defaultLocale);

                foreach ($cats as $cat) {
                    $slug = $cat->getSlugPath($defaultLocale);

                    $pushEntity([
                        'type' => 'category',
                        'id' => $cat->id,
                        'url' => '/'.$blogPrefix.'/'.ltrim((string) $slug, '/'),
                        'icon' => 'fa-solid fa-folder',
                        'badge' => 'CATEGORÍA',
                    ], $locales, static fn (string $locale): string => $entityTitle($cat, $locale, $defaultLocale, 'Categoría #'.$cat->id));
                }
            }
        } catch (\Throwable) {
        }

        // 4. Shop Products
        try {
            if (class_exists(Product::class) && $this->moduleAvailable(Product::class)) {
                $products = Product::where('is_active', true)->with('translations')->latest()->take(40)->get();
                $shopPrefix = $this->localization->getModulePrefix('shop', $defaultLocale);

                foreach ($products as $prod) {
                    $slugTranslation = $prod->translations->where('locale', $defaultLocale)->first()
                        ?? $prod->translations->first();
                    $slug = $slugTranslation?->slug ?: (string) $prod->id;

                    $pushEntity([
                        'type' => 'product',
                        'id' => $prod->id,
                        'url' => '/'.$shopPrefix.'/'.ltrim((string) $slug, '/'),
                        'icon' => 'fa-solid fa-bag-shopping',
                        'badge' => 'TIENDA',
                    ], $locales, static fn (string $locale): string => $entityTitle($prod, $locale, $defaultLocale, 'Producto #'.$prod->id));
                }
            }
        } catch (\Throwable) {
        }

        // Entities carry a `label` in the default language for the picker list.
        foreach ($availableEntities as $index => $entity) {
            $availableEntities[$index]['label'] = $entity['title_'.$defaultLocale] ?? reset($entity) ?: '';
        }

        $registeredZones = [
            'header' => [
                'name' => 'Cabecera Principal (Header)',
                'description' => 'Navegación superior principal visible en todas las páginas',
                'icon' => 'fa-solid fa-heading',
            ],
            'footer_quick' => [
                'name' => 'Pie de Página: Enlaces Rápidos',
                'description' => 'Columna de navegación secundaria en el pie de página',
                'icon' => 'fa-solid fa-shoe-prints',
            ],
            'footer_legal' => [
                'name' => 'Pie de Página: Legal & Transparencia',
                'description' => 'Enlaces obligatorios (Aviso Legal, Privacidad, Cookies)',
                'icon' => 'fa-solid fa-scale-balanced',
            ],
            'sidebar' => [
                'name' => 'Barra Lateral (Sidebar)',
                'description' => 'Menú contextual para páginas internas, categorías y blog',
                'icon' => 'fa-solid fa-table-columns',
            ],
            'topbar' => [
                'name' => 'Barra Superior (Topbar)',
                'description' => 'Pre-cabecera de utilidades, idiomas y accesos directos',
                'icon' => 'fa-solid fa-window-minimize',
            ],
            'mobile' => [
                'name' => 'Menú Móvil / Drawer',
                'description' => 'Navegación adaptada a dispositivos móviles y táctiles',
                'icon' => 'fa-solid fa-mobile-screen',
            ],
        ];

        $allMenus = Menu::with('items')->orderBy('name')->get();

        $zoneAssignments = [];
        foreach (array_keys($registeredZones) as $zoneKey) {
            $assigned = $allMenus->firstWhere('location', $zoneKey);
            $zoneAssignments[$zoneKey] = $assigned?->id;
        }

        return new MenuEditorData([
            'menus' => $menus,
            'allMenus' => $allMenus,
            'registeredZones' => $registeredZones,
            'zoneAssignments' => $zoneAssignments,
            'markets' => $markets,
            'availableEntities' => $availableEntities,
            'activeTab' => $input->input('tab', 'menus'),
            'languages' => $this->localization->activeLanguages(),
            'languageFlags' => $this->localization->activeLanguages()
                ->mapWithKeys(static fn ($language): array => [$language->code => $language->flag])
                ->all(),
            'defaultLocale' => $defaultLocale,
        ]);
    }

    /** @param class-string<Model> $class */
    private function moduleAvailable(string $class): bool
    {
        $owner = $this->modules->ownerOfClass($class);

        return $owner === null || $this->modules->isModuleEnabled($owner);
    }
}
