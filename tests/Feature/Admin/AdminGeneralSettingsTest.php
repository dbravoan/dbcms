<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Contracts\Services\GeneralSettingsServiceInterface;
use App\Models\Role;
use App\Models\User;
use App\Services\Seo\JsonLdSchemaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminGeneralSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        $this->admin = User::factory()->create();
        $this->admin->roles()->syncWithoutDetaching([$role->id]);
    }

    public function test_admin_settings_index_page_loads_successfully_with_all_tabs(): void
    {
        $response = $this->actingAs($this->admin, 'web')->get(route('admin.settings.index'));

        $response->assertOk();
        $response->assertSee('Ajustes Generales del Sitio y Empresa');
        $response->assertSee('Identidad &amp; Marca', false);
        $response->assertSee('Datos Fiscales &amp; LSSI', false);
        $response->assertSee('Contacto &amp; Soporte', false);
        $response->assertSee('RGPD &amp; DPO', false);
        $response->assertSee('Redes Sociales');
        $response->assertSee('SEO Local &amp; Schema.org', false);
    }

    public function test_admin_can_update_general_and_legal_settings(): void
    {
        $payload = [
            'general_site_name' => 'Enterprise Quantum CMS',
            'general_site_tagline' => 'Next-Gen Multi-Market Architecture',
            'general_site_description' => 'Autonomous CMS engine',
            'general_footer_text' => '© 2026 Enterprise Quantum. All rights reserved.',
            'general_og_image' => '/uploads/og/enterprise-quantum.png',
            'general_site_favicon' => '/uploads/brand/favicon.png',
            'legal_company_name' => 'Quantum Cybernetics S.L.',
            'legal_tax_id' => 'B-99887766',
            'legal_commercial_registry' => 'Registro Mercantil de Madrid, Tomo 1234, Folio 56, Hoja M-7890',
            'legal_address' => 'Paseo de la Castellana 100, 28046 Madrid, España',
            'contact_email' => 'soporte@quantum-cms.io',
            'contact_phone' => '+34 910 000 000',
            'contact_whatsapp' => '+34 600 000 000',
            'gdpr_dpo_email' => 'dpo@quantum-cms.io',
            'gdpr_page_cookies' => '/politica-cookies',
            'gdpr_page_privacy' => '/privacidad',
            'gdpr_page_notice' => '/aviso-legal',
            'social_twitter' => 'https://x.com/quantum_cms',
            'social_linkedin' => 'https://linkedin.com/company/quantum-cms',
            'social_github' => 'https://github.com/quantum-cms',
            'seo_organization_type' => 'Corporation',
        ];

        $response = $this->actingAs($this->admin, 'web')->put(route('admin.settings.update'), $payload);

        $response->assertRedirect(route('admin.settings.index'));
        $response->assertSessionHas('success');

        // Test helper and service integration
        expect(setting('general.site_name'))->toBe('Enterprise Quantum CMS');
        expect(setting('general.og_image'))->toBe('/uploads/og/enterprise-quantum.png');
        expect(setting('general.site_favicon'))->toBe('/uploads/brand/favicon.png');
        expect(setting('legal.company_name'))->toBe('Quantum Cybernetics S.L.');
        expect(setting('legal.tax_id'))->toBe('B-99887766');
        expect(setting('contact.email'))->toBe('soporte@quantum-cms.io');
        expect(setting('social.twitter'))->toBe('https://x.com/quantum_cms');
        expect(setting('seo.organization_type'))->toBe('Corporation');
    }

    public function test_general_settings_render_media_pickers_for_brand_assets(): void
    {
        $response = $this->actingAs($this->admin, 'web')->get(route('admin.settings.index'));

        $response->assertOk();
        $response->assertSee('siteSettings()', false);
        $response->assertSee('openMedia', false);

        // Favicon, main logo and OG image are wired to the media manager modal.
        $response->assertSee('name="general_site_logo"', false);
        $response->assertSee('name="general_site_favicon"', false);
        $response->assertSee('name="general_og_image"', false);
        $response->assertSee('Elegir del gestor', false);
    }

    public function test_json_ld_schema_service_incorporates_custom_business_settings(): void
    {
        /** @var GeneralSettingsServiceInterface $settingsService */
        $settingsService = app(GeneralSettingsServiceInterface::class);
        $settingsService->setMany([
            'general.site_name' => 'Acme Corporation AI',
            'legal.company_name' => 'Acme Technologies International S.A.',
            'legal.tax_id' => 'A-11223344',
            'contact.email' => 'contact@acme-corp.ai',
            'contact.phone' => '+34 900 111 222',
            'legal.address' => 'Avenida Diagonal 450, 08006 Barcelona',
            'social.twitter' => 'https://x.com/acmecorp',
            'social.linkedin' => 'https://linkedin.com/company/acmecorp',
            'seo.organization_type' => 'Organization',
        ]);

        $schemaService = app(JsonLdSchemaService::class);
        $siteGraph = $schemaService->generateSiteGraph();

        $graphNodes = $siteGraph['@graph'] ?? [];
        $orgNode = collect($graphNodes)->firstWhere('@type', 'Organization');

        expect($orgNode)->not->toBeNull();
        expect($orgNode['name'])->toBe('Acme Technologies International S.A.');
        expect($orgNode['taxID'])->toBe('A-11223344');
        expect($orgNode['email'])->toBe('contact@acme-corp.ai');
        expect($orgNode['telephone'])->toBe('+34 900 111 222');
        expect($orgNode['sameAs'])->toContain('https://x.com/acmecorp');
        expect($orgNode['sameAs'])->toContain('https://linkedin.com/company/acmecorp');
    }
}
