<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Seo;

use App\Core\Filters\FilterPathResolver;
use App\Core\Filters\FilterSeoResolver;
use App\Http\Controllers\Controller;
use App\Models\FilterPage;
use App\Models\FilterPageSection;
use App\Models\Language;
use App\Models\Market;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;

/**
 * Filtros y búsquedas: las páginas que genera el front al filtrar o buscar.
 *
 * By default they are `noindex`; here the editor picks which ones Google should
 * index, with their own title, description and — composed with the CMS block
 * manager — a rich intro rendered above the results.
 *
 * The form is server-rendered (no dialogs): it opens empty (`?new=1`), editing a
 * curated page (`?edit=<id>`) or pre-filled from the *Indexar en Google* button
 * on the site itself, so the editor never types an address by hand.
 */
final class FiltersGetController extends Controller
{
    public function __construct(
        private readonly FilterPathResolver $resolver,
        private readonly FilterSeoResolver $seo,
    ) {}

    public function __invoke(Request $request): View
    {
        $locale = App::getLocale();
        $modules = [];

        foreach ($this->resolver->modules() as $key => $module) {
            $modules[$key] = $module->filterModuleLabel($locale);
        }

        $languages = Language::query()->where('is_active', true)->orderBy('code')->get();

        return view('admin.filters.index', [
            'filterPages' => FilterPage::query()->orderBy('module')->orderBy('locale')->get(),
            'filterModules' => $modules,
            'markets' => Market::query()->where('is_active', true)->orderBy('code')->get(),
            'languages' => $languages,
            'form' => $this->form($request, $locale, $languages),
        ]);
    }

    /**
     * Form state: null when the editor is not composing a page.
     *
     * @return array<string, mixed>|null
     */
    /**
     * @param  Collection<int,Language>  $languages
     * @return array<string,mixed>|null
     */
    private function form(Request $request, string $locale, Collection $languages): ?array
    {
        $editing = $request->query('edit');

        if ($editing !== null && $editing !== '') {
            $page = FilterPage::query()->find((int) $editing);

            if ($page instanceof FilterPage) {
                return $this->editingForm($page, $languages);
            }
        }

        $path = trim((string) $request->query('path', ''));
        $moduleKey = trim((string) $request->query('module', ''));

        if ($path !== '' && $moduleKey !== '') {
            return $this->prefilledForm($request, $locale, $moduleKey, $path);
        }

        if ($request->boolean('new')) {
            return [
                'mode' => 'create',
                'id' => null,
                'module' => array_key_first($this->resolver->modules()) ?? 'blog',
                'market_code' => '',
                'locale' => $locale,
                'path' => '',
                'title' => '',
                'heading' => '',
                'meta_description' => '',
                'is_indexable' => true,
                'sections' => null,
                'warning' => false,
                'prefilled' => false,
            ];
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @param  Collection<int,Language>  $languages
     * @return array<string,mixed>
     */
    private function editingForm(FilterPage $page, Collection $languages): array
    {
        $sectionsByLocale = [];

        foreach ($languages as $language) {
            $sectionsByLocale[(string) $language->code] = [];
        }

        foreach (FilterPageSection::query()
            ->where('filter_page_id', $page->getKey())
            ->with('translations')
            ->orderBy('order')
            ->get() as $section) {
            $styling = is_array($section->styling_json) ? $section->styling_json : [];

            foreach ($languages as $language) {
                $code = (string) $language->code;
                $translation = $section->translations->firstWhere('locale', $code);

                $item = $styling;
                $item['id'] = 'sec_'.$section->getKey();
                $item['type'] = (string) $section->type;

                if ($translation !== null && is_array($translation->content_json)) {
                    $item = array_merge($item, $translation->content_json);
                }

                $sectionsByLocale[$code][] = $item;
            }
        }

        return [
            'mode' => 'edit',
            'id' => (int) $page->getKey(),
            'module' => (string) $page->module,
            'market_code' => (string) ($page->market_code ?? ''),
            'locale' => (string) $page->locale,
            'path' => (string) ($page->path ?? $page->signature),
            'title' => (string) ($page->title ?? ''),
            'heading' => (string) ($page->heading ?? ''),
            'meta_description' => (string) ($page->meta_description ?? ''),
            'is_indexable' => (bool) $page->is_indexable,
            'sections' => $sectionsByLocale,
            'warning' => str_contains((string) $page->signature, 'search=') || str_contains((string) $page->signature, 'sort='),
            'prefilled' => false,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function prefilledForm(Request $request, string $locale, string $moduleKey, string $path): ?array
    {
        $marketCode = trim((string) $request->query('market_code', ''));
        $requestLocale = trim((string) $request->query('locale', '')) ?: $locale;

        $resolved = $this->resolver->resolve($moduleKey, $marketCode, $requestLocale, $path);

        if ($resolved === null) {
            return null;
        }

        $market = $marketCode !== ''
            ? Market::query()->where('code', $marketCode)->first()
            : Market::query()->where('is_default', true)->first();

        $results = $market !== null
            ? $this->resolver->resultCount($resolved, $market, $requestLocale)
            : 0;

        $composed = $this->seo->resolve(
            $resolved['module'],
            $resolved['set'],
            $resolved['module']->filterDimensions(),
            $results,
            $marketCode !== '' ? $marketCode : (string) $market?->code,
        );

        return [
            'mode' => 'create',
            'id' => null,
            'module' => $moduleKey,
            'market_code' => $marketCode,
            'locale' => $requestLocale,
            'path' => $resolved['canonical'],
            'title' => $composed->title,
            'heading' => $composed->heading,
            'meta_description' => $composed->description,
            'is_indexable' => true,
            'sections' => null,
            'warning' => $resolved['set']->has('search') || $resolved['set']->has('sort'),
            'prefilled' => true,
        ];
    }
}
