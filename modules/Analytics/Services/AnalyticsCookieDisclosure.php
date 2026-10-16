<?php

declare(strict_types=1);

namespace Modules\Analytics\Services;

use App\Core\Modules\ModuleManager;
use App\Core\Security\Contracts\CookieDisclosureContributorInterface;

final class AnalyticsCookieDisclosure implements CookieDisclosureContributorInterface
{
    public function __construct(private readonly ModuleManager $modules) {}

    public function categories(string $locale): array
    {
        if (! $this->modules->isModuleEnabled('analytics')) {
            return [];
        }

        return ['analytics' => [
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
        ]];
    }
}
