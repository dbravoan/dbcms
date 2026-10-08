<?php

declare(strict_types=1);

namespace Tests\Feature\AiAssistant;

use App\Contracts\Services\GeneralSettingsServiceInterface;
use App\Core\Modules\ModuleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\AiAssistant\Contracts\AiBrandProfileServiceInterface;
use Modules\AiAssistant\Services\AiPromptTemplateEngine;
use Modules\Mcp\Services\McpServerService;
use Tests\Support\AiMcpFixture;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    app(ModuleManager::class)->discoverModules();
});

test('brand profile defaults merge with general site settings', function (): void {
    $generalSettings = app(GeneralSettingsServiceInterface::class);
    $generalSettings->set('general.site_name', 'Clínica Dental Sonrisas', 'general');
    $generalSettings->set('general.site_tagline', 'Odontología Avanzada y Cuidado Humano', 'general');

    /** @var AiBrandProfileServiceInterface $brandService */
    $brandService = app(AiBrandProfileServiceInterface::class);
    $profile = $brandService->getProfile();

    expect($profile['brand_name'])->toBe('Clínica Dental Sonrisas');
    expect($profile['brand_tagline'])->toBe('Odontología Avanzada y Cuidado Humano');
    expect($profile)->toHaveKeys([
        'brand_archetype',
        'brand_treatment',
        'brand_target_audience',
        'brand_value_proposition',
        'brand_preferred_terms',
        'brand_taboo_terms',
        'brand_cta_goals',
        'brand_custom_directives',
    ]);
});

test('brand profile presets define all 6 enterprise industries', function (): void {
    /** @var AiBrandProfileServiceInterface $brandService */
    $brandService = app(AiBrandProfileServiceInterface::class);
    $presets = $brandService->getPresets();

    expect($presets)->toHaveKeys(['saas', 'salud', 'legal', 'retail', 'hosteleria', 'creativo']);

    foreach ($presets as $key => $preset) {
        expect($preset)->toHaveKeys([
            'name',
            'description',
            'icon',
            'brand_archetype',
            'brand_treatment',
            'brand_target_audience',
            'brand_value_proposition',
            'brand_preferred_terms',
            'brand_taboo_terms',
            'brand_cta_goals',
            'brand_custom_directives',
        ]);
    }
});

test('apply preset updates module settings and profile accurately', function (): void {
    /** @var AiBrandProfileServiceInterface $brandService */
    $brandService = app(AiBrandProfileServiceInterface::class);

    $success = $brandService->applyPreset('salud');
    expect($success)->toBeTrue();

    $profile = $brandService->getProfile();
    expect($profile['brand_treatment'])->toBe('usted');
    expect($profile['brand_taboo_terms'])->toContain('milagroso');
    expect($profile['brand_archetype'])->toBe('autoridad');

    $segment = $brandService->buildBrandPromptSegment('es');
    expect($segment)->toContain('TRATAMIENTO GRAMATICAL');
    expect($segment)->toContain('usted');
    expect($segment)->toContain('milagroso');
});

test('prompt template engine integrates brand profile and pronoun treatment', function (): void {
    $generalSettings = app(GeneralSettingsServiceInterface::class);
    $generalSettings->set('general.site_name', 'Lex Veritas Bufete', 'general');

    /** @var AiBrandProfileServiceInterface $brandService */
    $brandService = app(AiBrandProfileServiceInterface::class);
    $brandService->applyPreset('legal');

    $engine = app(AiPromptTemplateEngine::class);
    $prompt = $engine->buildSystemPrompt('es');

    expect($prompt)->toContain('Lex Veritas Bufete');
    expect($prompt)->toContain('usted');
    expect($prompt)->toContain('POLÍTICA ANTI-CLICHÉS');
});

test('admin can save brand profile via backoffice endpoint', function (): void {
    $admin = AiMcpFixture::admin();

    $response = $this->actingAs($admin, 'web')
        ->postJson(route('admin.ai_assistant.save_brand_profile'), [
            'brand_archetype' => 'disruptivo',
            'brand_treatment' => 'tu',
            'brand_target_audience' => 'Fundadores de startups tecnológicas',
            'brand_value_proposition' => 'Escalabilidad sin fricción técnica',
            'brand_preferred_terms' => 'arquitectura, desacoplamiento, resiliencia',
            'brand_taboo_terms' => 'low cost, barato, chapuza',
            'brand_cta_goals' => 'Agendar demo estratégica',
            'brand_custom_directives' => 'Sé conciso y evita retórica vacía.',
        ]);

    $response->assertOk();
    $response->assertJson(['success' => true]);

    /** @var AiBrandProfileServiceInterface $brandService */
    $brandService = app(AiBrandProfileServiceInterface::class);
    $profile = $brandService->getProfile();

    expect($profile['brand_archetype'])->toBe('disruptivo');
    expect($profile['brand_target_audience'])->toBe('Fundadores de startups tecnológicas');
});

test('admin can apply preset via backoffice endpoint', function (): void {
    $admin = AiMcpFixture::admin();

    $response = $this->actingAs($admin, 'web')
        ->postJson(route('admin.ai_assistant.apply_preset'), [
            'preset' => 'saas',
        ]);

    $response->assertOk();
    $response->assertJson(['success' => true]);

    /** @var AiBrandProfileServiceInterface $brandService */
    $brandService = app(AiBrandProfileServiceInterface::class);
    $profile = $brandService->getProfile();

    expect($profile['brand_archetype'])->toBe('tecnico');
    expect($profile['brand_treatment'])->toBe('tu');
});

test('admin can preview system prompt via ajax endpoint', function (): void {
    $admin = AiMcpFixture::admin();

    $response = $this->actingAs($admin, 'web')
        ->postJson(route('admin.ai_assistant.preview_prompt'), [
            'locale' => 'es',
        ]);

    $response->assertOk();
    $response->assertJsonStructure(['success', 'prompt', 'locale']);
    expect($response->json('prompt'))->toContain('REGLA CRÍTICA DE IDIOMA');
});

test('mcp get editorial prompt reflects instance brand profile dynamically', function (): void {
    $generalSettings = app(GeneralSettingsServiceInterface::class);
    $generalSettings->set('general.site_name', 'Gourmet del Mar', 'general');

    /** @var AiBrandProfileServiceInterface $brandService */
    $brandService = app(AiBrandProfileServiceInterface::class);
    $brandService->applyPreset('hosteleria');

    AiMcpFixture::configure();
    $mcpService = app(McpServerService::class);
    $response = $mcpService->handle([
        'jsonrpc' => '2.0',
        'id' => 104,
        'method' => 'tools/call',
        'params' => [
            'name' => 'cms_get_editorial_prompt',
            'arguments' => [
                'locale' => 'es',
            ],
        ],
    ]);

    $result = json_decode($response['result']['content'][0]['text'], true);

    expect($result)->toHaveKey('system_prompt');
    expect($result['system_prompt'])->toContain('Gourmet del Mar');
    expect($result['system_prompt'])->toContain('POLÍTICA ANTI-CLICHÉS');
});
