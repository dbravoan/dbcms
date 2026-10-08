<?php

declare(strict_types=1);

use App\Contracts\Services\ModuleSettingsServiceInterface;
use App\Core\Modules\ModuleManager;
use App\Models\Role;
use App\Models\User;
use Modules\AiAssistant\Services\AiProviderSettings;

beforeEach(function (): void {
    app(ModuleManager::class)->discoverModules();
    $this->admin = User::factory()->create();
    $this->admin->assignRole(Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']));
    $this->settings = app(ModuleSettingsServiceInterface::class);
});

test('common settings boundary preserves masked credentials', function (): void {
    $this->settings->set('ai_assistant', 'custom_api_key', 'fake-private-key');
    $this->settings->setMany('ai_assistant', ['custom_api_key' => AiProviderSettings::MASKED_KEY]);
    expect($this->settings->get('ai_assistant', 'custom_api_key'))->toBe('fake-private-key');
});

test('common settings boundary cannot rebind credentials to another endpoint', function (): void {
    $this->settings->setMany('ai_assistant', ['custom_api_key' => 'fake-private-key', 'custom_base_url' => 'http://localhost:11434/v1']);
    expect(fn () => $this->settings->setMany('ai_assistant', ['custom_base_url' => 'http://localhost:9999/v1', 'custom_api_key' => AiProviderSettings::MASKED_KEY]))->toThrow(InvalidArgumentException::class);
    expect($this->settings->get('ai_assistant', 'custom_base_url'))->toBe('http://localhost:11434/v1');
});

test('whitespace replacement does not authorize an endpoint credential rebind', function (): void {
    $this->settings->setMany('ai_assistant', ['custom_api_key' => 'fake-private-key', 'custom_base_url' => 'http://localhost:11434/v1']);
    expect(fn () => $this->settings->setMany('ai_assistant', ['custom_base_url' => 'http://localhost:9999/v1', 'custom_api_key' => '   ']))->toThrow(InvalidArgumentException::class);
});

test('settings HTTP rejects credential arrays before persistence', function (): void {
    $this->actingAs($this->admin)->putJson('/admin/modules/meta/settings', ['pixel_id' => '123', 'capi_token' => ['invalid']])->assertUnprocessable()->assertJsonValidationErrors('capi_token');
});

test('settings HTTP reports endpoint credential conflict as validation instead of server failure', function (): void {
    $this->settings->setMany('ai_assistant', ['custom_api_key' => 'fake-private-key', 'custom_base_url' => 'http://localhost:11434/v1']);
    $this->actingAs($this->admin)->putJson('/admin/modules/ai_assistant/settings', ['is_enabled' => true, 'custom_base_url' => 'http://localhost:9999/v1', 'custom_api_key' => AiProviderSettings::MASKED_KEY])->assertUnprocessable()->assertJsonValidationErrors('settings');
    expect($this->settings->get('ai_assistant', 'custom_base_url'))->toBe('http://localhost:11434/v1');
});
