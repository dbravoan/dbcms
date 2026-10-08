<?php

declare(strict_types=1);

namespace App\Http\ViewComposers;

use App\Contracts\Services\LocalizationServiceInterface;
use App\Models\Language;
use App\Models\Market;
use App\Services\Cms\FrontChromeTranslator;
use App\Services\Cms\MenuService;
use App\Services\Cms\PageCacheService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Modules\Shop\Services\CartService;
use Modules\Shop\Services\ShopDomainService;

class FrontendLayoutComposer
{
    /**
     * Active markets collection.
     *
     * @var Collection<int, Market>|null
     */
    protected ?Collection $activeMarkets = null;

    public function __construct(
        protected MenuService $menuService,
        protected LocalizationServiceInterface $localization,
        protected FrontChromeTranslator $chrome,
        protected PageCacheService $pageCache
    ) {}

    /**
     * Bind global frontend presentation data to the view.
     */
    public function compose(View $view): void
    {
        $allMarkets = $this->activeMarkets();

        /** @var Market|null $currentMarket */
        $currentMarket = request()->attributes->get('market')
            ?? (app()->bound('current.market') ? app('current.market') : null)
            ?? $allMarkets->where('is_default', true)->first()
            ?? $allMarkets->first();

        $currentLocale = app()->getLocale();
        $marketCode = $currentMarket->code ?? 'es';
        $galleryPrefix = $this->localization->getModulePrefix('gallery', $currentLocale);

        $view->with(array_merge(
            $this->chrome->for($currentLocale),
            [
                'shopService' => $shopService = $this->shopService(),
                'isShopEnabled' => $shopService !== null && $this->isShopEnabled($shopService),
                'allActiveMarkets' => $allMarkets,
                'allMarkets' => $allMarkets,
                'currentMarket' => $currentMarket,
                'currentLocale' => $currentLocale,
                'marketCode' => $marketCode,
                'headerMenu' => $this->menuService->getMenu('header', $currentMarket, $currentLocale),
                'footerQuick' => $this->menuService->getMenu('footer_quick', $currentMarket, $currentLocale),
                'footerLegal' => $this->menuService->getMenu('footer_legal', $currentMarket, $currentLocale),
                'hasMarkets' => $allMarkets->count() > 1,
                'hasLangs' => $currentMarket !== null && $currentMarket->languages->count() > 1,
                'galleryPrefix' => $galleryPrefix,
                'galPrefix' => $galleryPrefix,
                'localization' => $this->localization,
                'cartCount' => $this->cartCount(),
                'flags' => $this->flags(),
                'frontNav' => $this->frontNav(),
            ]
        ));
    }

    /**
     * Backoffice-configured front navigation options (`front.nav_*` settings).
     *
     * @return array{admin: bool, layout: string, toggle: bool, icon: string, toggleDesktop: bool, adminUrl: string, adminLabel: string}
     */
    private function frontNav(): array
    {
        $adminUrl = url('/'.config('cms.admin_path', 'admin'));

        try {
            $layout = (string) setting('front.nav_layout', 'inline');
            $icon = (string) setting('front.nav_toggle_icon', 'bars');

            return [
                'admin' => (bool) setting('front.nav_admin_button', false),
                'layout' => in_array($layout, ['inline', 'stacked', 'dropdown'], true) ? $layout : 'inline',
                'toggle' => (bool) setting('front.nav_toggle_enabled', true),
                'icon' => in_array($icon, ['bars', 'chevron', 'plus', 'grid', 'dots'], true) ? $icon : 'bars',
                'toggleDesktop' => (bool) setting('front.nav_toggle_desktop', false),
                'adminUrl' => $adminUrl,
                'adminLabel' => '>_admin',
            ];
        } catch (\Throwable) {
            // Settings table not migrated yet (installer): keep safe defaults.
            return [
                'admin' => false,
                'layout' => 'inline',
                'toggle' => true,
                'icon' => 'bars',
                'toggleDesktop' => false,
                'adminUrl' => $adminUrl,
                'adminLabel' => '>_admin',
            ];
        }
    }

    /**
     * @return Collection<int, Market>
     */
    private function activeMarkets(): Collection
    {
        if ($this->activeMarkets !== null) {
            return $this->activeMarkets;
        }

        try {
            // Cached: resolved on every front request and changes only when a
            // market/language is edited (PageCacheObserver bumps the version).
            $key = 'cms:markets:v'.$this->pageCache->version();

            return $this->activeMarkets = Cache::remember(
                $key,
                $this->pageCache->ttl(),
                static fn (): Collection => Market::with('languages')->where('is_active', true)->get()
            );
        } catch (\Throwable) {
            return $this->activeMarkets = new Collection;
        }
    }

    private function cartCount(): int
    {
        if (! class_exists(CartService::class) || ! app()->bound(CartService::class)) {
            return 0;
        }

        try {
            return app(CartService::class)->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function shopService(): ?ShopDomainService
    {
        if (! class_exists(ShopDomainService::class)) {
            return null;
        }

        try {
            return app(ShopDomainService::class);
        } catch (\Throwable) {
            return null;
        }
    }

    private function isShopEnabled(ShopDomainService $shopService): bool
    {
        try {
            return $shopService->isShopEnabled();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Flag emoji per market code and per language code, as assigned in the
     * backoffice (with the globe as the neutral fallback).
     *
     * @return array<string, string>
     */
    private function flags(): array
    {
        try {
            // Versioned cache: invalidated when a market/language is edited.
            $key = 'cms:flags:v'.$this->pageCache->version();

            return Cache::remember($key, $this->pageCache->ttl(), function (): array {
                $flags = [];

                foreach ($this->activeMarkets() as $market) {
                    $flags[$market->code] = $market->flag;

                    foreach ($market->languages as $language) {
                        $flags[$language->code] ??= $language->flag;
                    }
                }

                foreach (Language::query()->where('is_active', true)->get() as $language) {
                    $flags[$language->code] ??= $language->flag;
                }

                return $flags;
            });
        } catch (\Throwable) {
            // Database not ready (installer / fresh boot).
            return [];
        }
    }
}
