<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for the general site & company settings form.
 */
class SiteSettingsUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The route is already protected by EnsureAdminAuthenticated + CheckPermission.
        return true;
    }

    /**
     * Checkboxes are absent when unchecked: normalise them to booleans.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'front_nav_admin_button' => $this->boolean('front_nav_admin_button'),
            'front_nav_toggle_enabled' => $this->boolean('front_nav_toggle_enabled'),
            'front_nav_toggle_desktop' => $this->boolean('front_nav_toggle_desktop'),
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'general_site_name' => ['nullable', 'string', 'max:120'],
            'general_site_tagline' => ['nullable', 'string', 'max:255'],
            'general_site_description' => ['nullable', 'string'],
            'general_site_logo' => ['nullable', 'string', 'max:255'],
            'general_site_logo_dark' => ['nullable', 'string', 'max:255'],
            'general_site_favicon' => ['nullable', 'string', 'max:255'],
            'general_og_image' => ['nullable', 'string', 'max:255'],
            'general_footer_text' => ['nullable', 'string', 'max:255'],

            'legal_company_name' => ['nullable', 'string', 'max:180'],
            'legal_tax_id' => ['nullable', 'string', 'max:50'],
            'legal_commercial_registry' => ['nullable', 'string', 'max:255'],
            'legal_address' => ['nullable', 'string', 'max:255'],
            'legal_postal_code' => ['nullable', 'string', 'max:20'],
            'legal_city' => ['nullable', 'string', 'max:100'],
            'legal_province' => ['nullable', 'string', 'max:100'],
            'legal_country' => ['nullable', 'string', 'max:100'],
            'legal_activity' => ['nullable', 'string', 'max:255'],

            'contact_email' => ['nullable', 'email', 'max:120'],
            'contact_support_email' => ['nullable', 'email', 'max:120'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'contact_whatsapp' => ['nullable', 'string', 'max:50'],
            'contact_schedule' => ['nullable', 'string', 'max:150'],

            'gdpr_dpo_email' => ['nullable', 'email', 'max:120'],
            'gdpr_page_notice' => ['nullable', 'string', 'max:255'],
            'gdpr_page_privacy' => ['nullable', 'string', 'max:255'],
            'gdpr_page_terms' => ['nullable', 'string', 'max:255'],
            'gdpr_page_cookies' => ['nullable', 'string', 'max:255'],

            'social_twitter' => ['nullable', 'url', 'max:255'],
            'social_linkedin' => ['nullable', 'url', 'max:255'],
            'social_instagram' => ['nullable', 'url', 'max:255'],
            'social_facebook' => ['nullable', 'url', 'max:255'],
            'social_youtube' => ['nullable', 'url', 'max:255'],
            'social_tiktok' => ['nullable', 'url', 'max:255'],
            'social_github' => ['nullable', 'url', 'max:255'],

            'seo_organization_type' => ['nullable', 'string', 'max:50'],
            'seo_latitude' => ['nullable', 'numeric'],
            'seo_longitude' => ['nullable', 'numeric'],
            'seo_price_range' => ['nullable', 'string', 'max:10'],

            'front_nav_admin_button' => ['boolean'],
            'front_nav_layout' => ['nullable', 'string', 'in:inline,stacked,dropdown'],
            'front_nav_toggle_enabled' => ['boolean'],
            'front_nav_toggle_icon' => ['nullable', 'string', 'in:bars,chevron,plus,grid,dots'],
            'front_nav_toggle_desktop' => ['boolean'],
        ];
    }
}
