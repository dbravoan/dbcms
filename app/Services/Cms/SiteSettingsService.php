<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\Contracts\Services\GeneralSettingsServiceInterface;

/**
 * Maps the general settings form payload to grouped CMS settings and persists it.
 *
 * Keeps the backoffice controller thin: the controller validates and delegates,
 * the field mapping lives here.
 */
class SiteSettingsService
{
    public function __construct(
        private readonly GeneralSettingsServiceInterface $settings
    ) {}

    /**
     * @param  array<string, mixed>  $data  Validated form payload.
     */
    public function update(array $data): void
    {
        foreach ($this->map($data) as $group => $items) {
            $this->settings->setMany($items, $group);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, array<string, mixed>>
     */
    private function map(array $data): array
    {
        return [
            'general' => [
                'general.site_name' => $data['general_site_name'] ?? null,
                'general.site_tagline' => $data['general_site_tagline'] ?? null,
                'general.site_description' => $data['general_site_description'] ?? null,
                'general.site_logo' => $data['general_site_logo'] ?? null,
                'general.site_logo_dark' => $data['general_site_logo_dark'] ?? null,
                'general.site_favicon' => $data['general_site_favicon'] ?? null,
                'general.og_image' => $data['general_og_image'] ?? null,
                'general.footer_text' => $data['general_footer_text'] ?? null,
            ],
            'legal' => [
                'legal.company_name' => $data['legal_company_name'] ?? null,
                'legal.tax_id' => $data['legal_tax_id'] ?? null,
                'legal.commercial_registry' => $data['legal_commercial_registry'] ?? null,
                'legal.address' => $data['legal_address'] ?? null,
                'legal.postal_code' => $data['legal_postal_code'] ?? null,
                'legal.city' => $data['legal_city'] ?? null,
                'legal.province' => $data['legal_province'] ?? null,
                'legal.country' => $data['legal_country'] ?? null,
                'legal.activity' => $data['legal_activity'] ?? null,
            ],
            'contact' => [
                'contact.email' => $data['contact_email'] ?? null,
                'contact.support_email' => $data['contact_support_email'] ?? null,
                'contact.phone' => $data['contact_phone'] ?? null,
                'contact.whatsapp' => $data['contact_whatsapp'] ?? null,
                'contact.schedule' => $data['contact_schedule'] ?? null,
            ],
            'gdpr' => [
                'gdpr.dpo_email' => $data['gdpr_dpo_email'] ?? null,
                'gdpr.page_notice' => $data['gdpr_page_notice'] ?? null,
                'gdpr.page_privacy' => $data['gdpr_page_privacy'] ?? null,
                'gdpr.page_terms' => $data['gdpr_page_terms'] ?? null,
                'gdpr.page_cookies' => $data['gdpr_page_cookies'] ?? null,
            ],
            'social' => [
                'social.twitter' => $data['social_twitter'] ?? null,
                'social.linkedin' => $data['social_linkedin'] ?? null,
                'social.instagram' => $data['social_instagram'] ?? null,
                'social.facebook' => $data['social_facebook'] ?? null,
                'social.youtube' => $data['social_youtube'] ?? null,
                'social.tiktok' => $data['social_tiktok'] ?? null,
                'social.github' => $data['social_github'] ?? null,
            ],
            'seo' => [
                'seo.organization_type' => $data['seo_organization_type'] ?? 'Organization',
                'seo.latitude' => $data['seo_latitude'] ?? null,
                'seo.longitude' => $data['seo_longitude'] ?? null,
                'seo.price_range' => $data['seo_price_range'] ?? null,
            ],
            'front' => [
                'front.nav_admin_button' => (bool) ($data['front_nav_admin_button'] ?? false),
                'front.nav_layout' => $data['front_nav_layout'] ?? 'inline',
                'front.nav_toggle_enabled' => (bool) ($data['front_nav_toggle_enabled'] ?? true),
                'front.nav_toggle_icon' => $data['front_nav_toggle_icon'] ?? 'bars',
                'front.nav_toggle_desktop' => (bool) ($data['front_nav_toggle_desktop'] ?? false),
            ],
        ];
    }
}
