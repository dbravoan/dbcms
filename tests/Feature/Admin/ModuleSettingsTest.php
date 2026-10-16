<?php

declare(strict_types=1);

use App\Contracts\Services\ModuleSettingsServiceInterface;
use App\Core\Modules\ModuleManager;
use App\Models\CmsModule;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\CmsFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Modules\Forms\Models\CustomForm;
use Modules\Forms\Models\CustomFormTranslation;
use Modules\Forms\Models\FormField;
use Modules\Forms\Models\FormFieldTranslation;
use Tests\Support\ModuleSettingsComposition;

uses(RefreshDatabase::class, ModuleSettingsComposition::class);

beforeEach(function (): void {
    $this->seed(CmsFoundationSeeder::class);
    $this->manager = app(ModuleManager::class);
    $this->manager->discoverModules();

    $this->admin = User::create([
        'name' => 'Admin User',
        'email' => 'admin@db-cms.io',
        'password' => bcrypt('secret123'),
    ]);
    $adminRole = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
    $this->admin->roles()->sync([$adminRole->id]);

    $this->viewer = User::create([
        'name' => 'Viewer User',
        'email' => 'viewer@db-cms.io',
        'password' => bcrypt('secret123'),
    ]);
    $viewerRole = Role::firstOrCreate(['slug' => 'viewer'], ['name' => 'Viewer']);
    $access = Permission::firstOrCreate(['slug' => 'access:admin'], ['name' => 'Administrative access']);
    $viewerRole->permissions()->syncWithoutDetaching([$access->id]);
    $this->viewer->roles()->sync([$viewerRole->id]);
});

test('unauthenticated users are redirected to admin login', function (): void {
    $response = $this->get(route('admin.modules.settings', ['identifier' => 'analytics']));
    $response->assertRedirect(route('admin.login'));
});

test('non-admin users without manage:modules permission are forbidden (403)', function (): void {
    $response = $this->actingAs($this->viewer, 'web')
        ->get(route('admin.modules.settings', ['identifier' => 'analytics']));

    $response->assertStatus(403);
});

test('admin can view module settings page with dynamic schema fields rendered', function (): void {
    $response = $this->actingAs($this->admin, 'web')
        ->get(route('admin.modules.settings', ['identifier' => 'analytics']));

    $response->assertStatus(200);
    $response->assertSee('Ajustes del Módulo:');
    $response->assertSee('GA4 Measurement ID');
    $response->assertSee('Google Tag Manager ID');
    $response->assertSee('Anonimizar IPs (RGPD)');
});

test('admin can update module settings and secrets are encrypted in database', function (): void {
    $metaModule = CmsModule::where('identifier', 'meta')->firstOrFail();

    $response = $this->actingAs($this->admin, 'web')
        ->put(route('admin.modules.settings.update', ['identifier' => 'meta']), [
            'pixel_id' => '9876543210',
            'capi_token' => 'EAAXXYYZZ_super_secret_token',
            'test_event_code' => 'TEST_LIVE_01',
            'enable_capi' => '1',
        ]);

    $response->assertRedirect(route('admin.modules.settings', ['identifier' => 'meta']));
    $response->assertSessionHas('success');

    $metaModule->refresh();
    expect($metaModule->settings)->toBeArray();
    expect($metaModule->settings['pixel_id'])->toBe('9876543210');
    expect($metaModule->settings['test_event_code'])->toBe('TEST_LIVE_01');

    // Secret must NOT be stored in plaintext in the database column
    expect($metaModule->settings['capi_token'])->not->toBe('EAAXXYYZZ_super_secret_token');
    // Secret can be successfully decrypted
    expect(Crypt::decryptString($metaModule->settings['capi_token']))->toBe('EAAXXYYZZ_super_secret_token');

    // Helper function transparently returns decrypted value
    expect(module_setting('meta', 'pixel_id'))->toBe('9876543210');
    expect(module_setting('meta', 'capi_token'))->toBe('EAAXXYYZZ_super_secret_token');
});

test('updating settings with blank secret retains the existing encrypted secret', function (): void {
    $metaModule = CmsModule::where('identifier', 'meta')->firstOrFail();
    $encryptedToken = Crypt::encryptString('existing_super_secret_token');
    $metaModule->update([
        'settings' => [
            'pixel_id' => '123456789',
            'capi_token' => $encryptedToken,
        ],
    ]);

    // Update with empty capi_token
    $response = $this->actingAs($this->admin, 'web')
        ->put(route('admin.modules.settings.update', ['identifier' => 'meta']), [
            'pixel_id' => '999999999',
            'capi_token' => '',
        ]);

    $response->assertRedirect(route('admin.modules.settings', ['identifier' => 'meta']));

    $metaModule->refresh();
    expect($metaModule->settings['pixel_id'])->toBe('999999999');
    expect($metaModule->settings['capi_token'])->toBe($encryptedToken);
    expect(module_setting('meta', 'capi_token'))->toBe('existing_super_secret_token');
});

test('module_setting helper falls back to runtime config then provided default', function (): void {
    // When no setting exists in DB
    $nonExistent = module_setting('analytics', 'custom_key', 'fallback_value');
    expect($nonExistent)->toBe('fallback_value');

    // When runtime config exists
    config(['modules.analytics.custom_config_key' => 'from_config']);
    expect(module_setting('analytics', 'custom_config_key'))->toBe('from_config');
});

test('isConfigured reports correct status based on required fields', function (): void {
    /** @var CmsModule $metaModule */
    $metaModule = CmsModule::where('identifier', 'meta')->firstOrFail();

    // Initially without settings, required fields (pixel_id, capi_token) are missing
    $metaModule->update(['settings' => []]);
    $settings = app(ModuleSettingsServiceInterface::class);
    expect($settings->isConfigured('meta'))->toBeFalse();

    // Once required fields are set
    $settings->set('meta', 'pixel_id', '12345');
    $settings->set('meta', 'capi_token', 'token_secret');
    expect($settings->isConfigured('meta'))->toBeTrue();
});

test('modules index view displays settings link and configuration badges', function (): void {
    $response = $this->actingAs($this->admin, 'web')
        ->get(route('admin.modules.index'));

    $response->assertStatus(200);
    $response->assertSee('Ajustes');
    $response->assertSee(route('admin.modules.settings', ['identifier' => 'analytics']));
    $response->assertSee(route('admin.modules.settings', ['identifier' => 'meta']));
});

test('admin can view and update the canonical shop module settings', function (): void {
    // 1. GET module settings for shop
    $response = $this->actingAs($this->admin, 'web')
        ->get(route('admin.modules.settings', ['identifier' => 'shop']));

    $response->assertStatus(200);
    $response->assertSee('Product Catalog &amp; E-Commerce', false);
    $response->assertSee('Modo de funcionamiento de la tienda');
    $response->assertSee('Tasa impositiva / IVA (%)');
    $response->assertSee('Email de notificaciones comerciales');

    // 2. PUT module settings update for shop
    $updateResp = $this->actingAs($this->admin, 'web')
        ->put(route('admin.modules.settings.update', ['identifier' => 'shop']), [
            'shop_mode' => 'catalog_only',
            'catalog_show_prices' => '0',
            'currency' => 'USD',
            'tax_rate' => 15.5,
            'enable_stock' => '1',
            'enable_inquiry' => '1',
            'notification_email' => 'finance@db-cms.io',
        ]);

    $updateResp->assertRedirect(route('admin.modules.settings', ['identifier' => 'shop']));
    $updateResp->assertSessionHas('success');

    // 3. Verify via module_setting helper
    expect(module_setting('shop', 'shop_mode'))->toBe('catalog_only');
    expect(module_setting('shop', 'currency'))->toBe('USD');
    expect((float) module_setting('shop', 'tax_rate'))->toBe(15.5);
    expect(module_setting('shop', 'notification_email'))->toBe('finance@db-cms.io');

});

test('forms index renders preview button and interactive modal with form engine', function (): void {
    // Create a custom form with a field
    $form = CustomForm::create([
        'slug' => 'formulario-auditoria-tecnica',
        'is_active' => true,
    ]);

    CustomFormTranslation::create([
        'custom_form_id' => $form->id,
        'locale' => 'es',
        'name' => 'Formulario de Auditoría Técnica',
        'submit_button_text' => 'Solicitar Auditoría',
        'success_message' => 'Solicitud de auditoría recibida con éxito.',
    ]);

    $field = FormField::create([
        'custom_form_id' => $form->id,
        'field_key' => 'empresa',
        'type' => 'text',
        'is_required' => true,
        'order' => 1,
    ]);

    FormFieldTranslation::create([
        'form_field_id' => $field->id,
        'locale' => 'es',
        'label' => 'Nombre de la Empresa',
    ]);

    $response = $this->actingAs($this->admin, 'web')
        ->get(route('admin.forms.index'));

    $response->assertStatus(200);
    $response->assertSee('Ver Formulario');
    $response->assertSee('previewFormId');
    $response->assertSee('Vista Previa: Formulario de Auditoría Técnica');
    $response->assertSee('Nombre de la Empresa');
    $response->assertSee('Solicitar Auditoría');
    $response->assertSee('&lt;x-forms::form-engine slug="formulario-auditoria-tecnica" /&gt;', false);
});
