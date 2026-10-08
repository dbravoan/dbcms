<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\Core\Modules\ModuleManager;
use Illuminate\Http\Request;

class CookieConsentService
{
    public function __construct(private readonly ModuleManager $modules) {}

    /**
     * Check if a CMS module is installed and active.
     */
    public function isModuleActive(string $identifier): bool
    {
        try {
            return $this->modules->isModuleEnabled($identifier);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Get list of disclosed cookie categories based on currently active modules.
     *
     * @return array<string, array{key: string, title: string, description: string, required: bool, default: bool, cookies: array<int, array{name: string, provider: string, purpose: string, duration: string}>}>
     */
    public function getDisclosedCategories(?string $locale = null): array
    {
        $locale = $locale ?? app()->getLocale();
        $categories = [];

        // 1. Necessary / Technical Cookies (Always present and required)
        $categories['necessary'] = [
            'key' => 'necessary',
            'title' => $locale === 'ca' ? 'Cookies Tècniques i Obligatòries' : ($locale === 'en' ? 'Strictly Necessary & Technical Cookies' : 'Cookies Técnicas y Obligatorias'),
            'description' => $locale === 'ca'
                ? 'Fonamentals per a la navegació, seguretat contra atacs CSRF, gestió de la sessió, selecció de regió/idioma i registre de consentiment.'
                : ($locale === 'en'
                    ? 'Essential for basic navigation, CSRF attack prevention, session state, region/language selection, and consent storage.'
                    : 'Fundamentales para la navegación, seguridad contra ataques CSRF, gestión de la sesión, selección de región/idioma y registro de consentimiento.'),
            'required' => true,
            'default' => true,
            'cookies' => [
                [
                    'name' => 'XSRF-TOKEN',
                    'provider' => 'dbCMS Security',
                    'purpose' => $locale === 'ca' ? 'Prevenció d\'atacs de falsificació de petició en llocs creuats (CSRF).' : ($locale === 'en' ? 'Cross-Site Request Forgery (CSRF) protection.' : 'Prevención de ataques de falsificación de petición en sitios cruzados (CSRF).'),
                    'duration' => $locale === 'ca' ? 'Sessió' : ($locale === 'en' ? 'Session' : 'Sesión'),
                ],
                [
                    'name' => 'dbcms_session',
                    'provider' => 'dbCMS Core',
                    'purpose' => $locale === 'ca' ? 'Emmagatzema l\'estat de la sessió de l\'usuari i autenticació al sistema.' : ($locale === 'en' ? 'Stores user session state and platform authentication.' : 'Almacena el estado de la sesión del usuario y autenticación en el sistema.'),
                    'duration' => $locale === 'ca' ? 'Sessió' : ($locale === 'en' ? 'Session' : 'Sesión'),
                ],
                [
                    'name' => 'cms_geo_preference',
                    'provider' => 'dbCMS Geo & Markets',
                    'purpose' => $locale === 'ca' ? 'Recorda la preferència de mercat i idioma seleccionada pel visitant.' : ($locale === 'en' ? 'Remembers visitor market and locale preference.' : 'Recuerda la preferencia de mercado e idioma seleccionada por el visitante.'),
                    'duration' => $locale === 'ca' ? '1 any' : ($locale === 'en' ? '1 year' : '1 año'),
                ],
                [
                    'name' => 'cms_cookie_consent',
                    'provider' => 'dbCMS RGPD Compliance',
                    'purpose' => $locale === 'ca' ? 'Registra les categories de galetes acceptades per l\'usuari sota RGPD.' : ($locale === 'en' ? 'Stores user consent preferences under GDPR regulations.' : 'Registra las categorías de cookies aceptadas por el usuario bajo RGPD.'),
                    'duration' => $locale === 'ca' ? '1 any' : ($locale === 'en' ? '1 year' : '1 año'),
                ],
            ],
        ];

        // 2. Functional / Shop Cookies (If Shop module is active)
        if ($this->isModuleActive('shop')) {
            $categories['functional'] = [
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
            ];
        }

        // 3. Analytics & Performance Cookies (If Analytics module is active)
        if ($this->isModuleActive('analytics')) {
            $categories['analytics'] = [
                'key' => 'analytics',
                'title' => $locale === 'ca' ? 'Cookies d\'Anàlisi i Rendiment' : ($locale === 'en' ? 'Analytics & Performance Cookies' : 'Cookies de Análisis y Rendimiento'),
                'description' => $locale === 'ca'
                    ? 'Recullen informació anònima i agregada sobre la interacció dels usuaris per mesurar i optimitzar el rendiment de la plataforma.'
                    : ($locale === 'en'
                        ? 'Collects anonymous and aggregated usage data to measure and improve platform performance.'
                        : 'Recopilan información anónima y agregada sobre la interacción de los usuarios para medir y optimizar el rendimiento de la plataforma.'),
                'required' => false,
                'default' => false,
                'cookies' => [
                    [
                        'name' => '_ga',
                        'provider' => 'Google Analytics',
                        'purpose' => $locale === 'ca' ? 'Identificador únic per distingir usuaris únics de forma anonimitzada.' : ($locale === 'en' ? 'Unique identifier used to distinguish visitors anonymously.' : 'Identificador único para distinguir usuarios únicos de forma anonimizada.'),
                        'duration' => $locale === 'ca' ? '2 anys' : ($locale === 'en' ? '2 years' : '2 años'),
                    ],
                    [
                        'name' => '_ga_*',
                        'provider' => 'Google Analytics',
                        'purpose' => $locale === 'ca' ? 'Estat i comptador de sessions de navegació.' : ($locale === 'en' ? 'Maintains session state across page requests.' : 'Estado y contador de sesiones de navegación.'),
                        'duration' => $locale === 'ca' ? '2 anys' : ($locale === 'en' ? '2 years' : '2 años'),
                    ],
                ],
            ];
        }

        // 4. Marketing & Ads Cookies (If Ads module is active)
        if ($this->isModuleActive('ads')) {
            $categories['marketing'] = [
                'key' => 'marketing',
                'title' => $locale === 'ca' ? 'Cookies Publicitàries i Màrqueting' : ($locale === 'en' ? 'Marketing & Advertising Cookies' : 'Cookies Publicitarias y Marketing'),
                'description' => $locale === 'ca'
                    ? 'S\'utilitzen per gestionar campanyes promocionals, personalitzar continguts patrocinats i mesurar l\'efectivitat dels anuncis.'
                    : ($locale === 'en'
                        ? 'Used to deliver relevant advertising, personalize sponsored content, and track campaign performance.'
                        : 'Se utilizan para gestionar campañas promocionales, personalizar contenidos patrocinados y medir la efectividad de anuncios.'),
                'required' => false,
                'default' => false,
                'cookies' => [
                    [
                        'name' => 'cms_ad_tracker',
                        'provider' => 'dbCMS Advertising',
                        'purpose' => $locale === 'ca' ? 'Registre anònim d\'impressions i clics en anuncis interns.' : ($locale === 'en' ? 'Anonymous tracking of impressions and clicks on internal ads.' : 'Registro anónimo de impresiones y clics en anuncios internos.'),
                        'duration' => $locale === 'ca' ? '30 dies' : ($locale === 'en' ? '30 days' : '30 días'),
                    ],
                ],
            ];
        }

        return $categories;
    }

    /**
     * Check if visitor has already submitted or configured consent preferences.
     */
    public function hasGivenConsent(?Request $request = null): bool
    {
        $request = $request ?? request();
        $rawCookie = $request->cookie('cms_cookie_consent');

        return is_string($rawCookie) && ! empty(trim($rawCookie));
    }

    /**
     * Check if visitor has consented to a specific cookie category.
     */
    public function hasConsent(string $category, ?Request $request = null): bool
    {
        if ($category === 'necessary' || $category === 'technical') {
            return true;
        }

        $request = $request ?? request();
        $rawCookie = $request->cookie('cms_cookie_consent');
        $cookie = is_string($rawCookie) ? $rawCookie : '';

        if (empty($cookie)) {
            return false;
        }

        $decoded = json_decode($cookie, true);
        if (is_array($decoded)) {
            return ! empty($decoded[$category]);
        }

        $parts = array_map('trim', explode(',', $cookie));

        return in_array($category, $parts, true);
    }

    /**
     * Get parsed consent preferences for current request.
     *
     * @return array<string, bool>
     */
    public function getConsentPreferences(?Request $request = null): array
    {
        $request = $request ?? request();
        $rawCookie = $request->cookie('cms_cookie_consent');
        $cookie = is_string($rawCookie) ? $rawCookie : '';

        $preferences = [
            'necessary' => true,
            'functional' => false,
            'analytics' => false,
            'marketing' => false,
        ];

        if (empty($cookie)) {
            return $preferences;
        }

        $decoded = json_decode($cookie, true);
        if (is_array($decoded)) {
            foreach ($decoded as $key => $val) {
                $preferences[$key] = filter_var($val, FILTER_VALIDATE_BOOLEAN);
            }
            $preferences['necessary'] = true;

            return $preferences;
        }

        $parts = array_map('trim', explode(',', $cookie));
        foreach (['functional', 'analytics', 'marketing'] as $cat) {
            $preferences[$cat] = in_array($cat, $parts, true);
        }
        $preferences['necessary'] = true;

        return $preferences;
    }
}
