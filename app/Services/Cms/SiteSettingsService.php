<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\Contracts\Services\GeneralSettingsServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Validates and atomically patches explicit canonical site settings. */
final class SiteSettingsService
{
    public function __construct(private readonly GeneralSettingsServiceInterface $settings) {}

    /** @return array<string, list<string>> */
    public static function rules(): array
    {
        return [
            'general.site_name' => ['nullable', 'string', 'max:120'],
            'general.site_tagline' => ['nullable', 'string', 'max:255'],
            'general.site_description' => ['nullable', 'string'],
            'general.site_logo' => ['nullable', 'string', 'max:255'],
            'general.site_logo_dark' => ['nullable', 'string', 'max:255'],
            'general.site_favicon' => ['nullable', 'string', 'max:255'],
            'general.og_image' => ['nullable', 'string', 'max:255'],
            'general.footer_text' => ['nullable', 'string', 'max:255'],

            'legal.company_name' => ['nullable', 'string', 'max:180'],
            'legal.tax_id' => ['nullable', 'string', 'max:50'],
            'legal.commercial_registry' => ['nullable', 'string', 'max:255'],
            'legal.address' => ['nullable', 'string', 'max:255'],
            'legal.postal_code' => ['nullable', 'string', 'max:20'],
            'legal.city' => ['nullable', 'string', 'max:100'],
            'legal.province' => ['nullable', 'string', 'max:100'],
            'legal.country' => ['nullable', 'string', 'max:100'],
            'legal.activity' => ['nullable', 'string', 'max:255'],

            'contact.email' => ['nullable', 'email', 'max:120'],
            'contact.support_email' => ['nullable', 'email', 'max:120'],
            'contact.phone' => ['nullable', 'string', 'max:50'],
            'contact.whatsapp' => ['nullable', 'string', 'max:50'],
            'contact.schedule' => ['nullable', 'string', 'max:150'],

            'gdpr.dpo_email' => ['nullable', 'email', 'max:120'],
            'gdpr.page_notice' => ['nullable', 'string', 'max:255'],
            'gdpr.page_privacy' => ['nullable', 'string', 'max:255'],
            'gdpr.page_terms' => ['nullable', 'string', 'max:255'],
            'gdpr.page_cookies' => ['nullable', 'string', 'max:255'],

            'social.twitter' => ['nullable', 'url', 'max:255'],
            'social.linkedin' => ['nullable', 'url', 'max:255'],
            'social.instagram' => ['nullable', 'url', 'max:255'],
            'social.facebook' => ['nullable', 'url', 'max:255'],
            'social.youtube' => ['nullable', 'url', 'max:255'],
            'social.tiktok' => ['nullable', 'url', 'max:255'],
            'social.github' => ['nullable', 'url', 'max:255'],

            'seo.organization_type' => ['nullable', 'string', 'max:50'],
            'seo.latitude' => ['nullable', 'numeric'],
            'seo.longitude' => ['nullable', 'numeric'],
            'seo.price_range' => ['nullable', 'string', 'max:10'],

            'front.nav_admin_button' => ['boolean'],
            'front.nav_layout' => ['nullable', 'string', 'in:inline,stacked,dropdown'],
            'front.nav_toggle_enabled' => ['boolean'],
            'front.nav_toggle_icon' => ['nullable', 'string', 'in:bars,chevron,plus,grid,dots'],
            'front.nav_toggle_desktop' => ['boolean'],
        ];
    }

    /** @param array<string, mixed> $attributes */
    public function update(array $attributes): void
    {
        $rules = self::rules();
        $unknown = array_diff(array_keys($attributes), array_keys($rules));
        if ($unknown !== []) {
            throw ValidationException::withMessages(['attributes' => 'Unknown site settings: '.implode(', ', $unknown)]);
        }

        // Escape literal dots: canonical setting keys are flat, not nested input.
        $validationRules = [];
        foreach ($rules as $key => $constraints) {
            $validationRules[str_replace('.', '\\.', $key)] = $constraints;
        }
        Validator::make($attributes, $validationRules)->validate();

        $grouped = [];
        foreach ($attributes as $key => $value) {
            [$group] = explode('.', $key, 2);
            $grouped[$group][$key] = $value;
        }

        try {
            DB::transaction(function () use ($grouped): void {
                foreach ($grouped as $group => $settings) {
                    $this->settings->setMany($settings, $group);
                }
            });
        } finally {
            // A failed later group must not leave earlier writes in the singleton cache.
            $this->settings->flushCache();
        }
    }
}
