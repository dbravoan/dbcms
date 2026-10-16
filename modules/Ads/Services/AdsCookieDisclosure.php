<?php

declare(strict_types=1);

namespace Modules\Ads\Services;

use App\Core\Modules\ModuleManager;
use App\Core\Security\Contracts\CookieDisclosureContributorInterface;

final class AdsCookieDisclosure implements CookieDisclosureContributorInterface
{
    public function __construct(private readonly ModuleManager $modules) {}

    public function categories(string $locale): array
    {
        if (! $this->modules->isModuleEnabled('ads')) {
            return [];
        }

        return ['marketing' => [
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
        ]];
    }
}
