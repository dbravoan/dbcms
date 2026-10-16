<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\Core\Security\Contracts\CookieDisclosureContributorInterface;
use Illuminate\Container\Attributes\Tag;
use Illuminate\Http\Request;

class CookieConsentService
{
    /** @param iterable<CookieDisclosureContributorInterface> $contributors */
    public function __construct(#[Tag('cms.cookie-disclosures')] private readonly iterable $contributors = []) {}

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

        foreach ($this->contributors as $contributor) {
            foreach ($contributor->categories($locale) as $key => $category) {
                $categories[$key] = $category;
            }
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

        return is_string($rawCookie) && is_array(json_decode($rawCookie, true));
    }

    /**
     * Check if visitor has consented to a specific cookie category.
     */
    public function hasConsent(string $category, ?Request $request = null): bool
    {
        return $this->getConsentPreferences($request)[$category] ?? false;
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
            foreach (array_keys($preferences) as $key) {
                $preferences[$key] = ($decoded[$key] ?? false) === true;
            }
            $preferences['necessary'] = true;
        }

        return $preferences;
    }
}
