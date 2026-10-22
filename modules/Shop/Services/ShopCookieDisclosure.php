<?php

declare(strict_types=1);

namespace Modules\Shop\Services;

use App\Core\Modules\ModuleManager;
use App\Core\Security\Contracts\CookieDisclosureContributorInterface;

final class ShopCookieDisclosure implements CookieDisclosureContributorInterface
{
    public function __construct(private readonly ModuleManager $modules) {}

    public function categories(string $locale): array
    {
        if (! $this->modules->isModuleEnabled('shop')) {
            return [];
        }

        return ['functional' => [
            'key' => 'functional',
            'title' => $locale === 'ca' ? 'Cookies Funcionals i Botiga' : ($locale === 'en' ? 'Functional & Shop Cookies' : 'Cookies Funcionales y Tienda'),
            'description' => $locale === 'ca'
                ? 'Permeten recordar els articles afegits al carretó de la compra, les preferències de divisa i mantenir la continuïtat de les comandes.'
                : ($locale === 'en'
                    ? 'Enables shopping cart persistence, currency preferences, and order checkout continuity.'
                    : 'Permiten recordar los artículos agregados al carrito de compra, preferencias de moneda y mantener la continuidad de los pedidos.'),
            'required' => false,
            'default' => true,
            'cookies' => [
                [
                    'name' => 'shop_cart_items',
                    'provider' => 'dbCMS E-Commerce',
                    'purpose' => $locale === 'ca' ? 'Persistència d\'articles al carretó de la compra entre pàgines.' : ($locale === 'en' ? 'Persistence of shopping cart items between visits.' : 'Persistencia de artículos en el carrito de compra entre páginas.'),
                    'duration' => $locale === 'ca' ? '30 dies' : ($locale === 'en' ? '30 days' : '30 días'),
                ],
                [
                    'name' => 'currency_preference',
                    'provider' => 'dbCMS E-Commerce',
                    'purpose' => $locale === 'ca' ? 'Recorda la moneda seleccionada en el catàleg multi-divisa.' : ($locale === 'en' ? 'Remembers selected currency in multi-currency catalog.' : 'Recuerda la moneda seleccionada en el catálogo multi-divisa.'),
                    'duration' => $locale === 'ca' ? '30 dies' : ($locale === 'en' ? '30 days' : '30 días'),
                ],
            ],
        ]];
    }
}
