<?php

declare(strict_types=1);
use App\Models\CmsModule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Modules\AiAssistant\Contracts\AiAssistantDomainServiceInterface;
use Modules\AiAssistant\Contracts\AiConfigBridgeInterface;
use Modules\AiAssistant\Services\AiProviderSettings;
use Tests\Support\AiComposition;

uses(AiComposition::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    config(['services.higgsfield.key' => null, 'services.gemini.key' => null]);
});
test('Higgsfield stores separate encrypted credentials and formats the authorization header', function (): void {
    app(AiConfigBridgeInterface::class)->saveProviderConfig('higgsfield', ['api_key_id' => 'account-id', 'api_key_secret' => 'account-secret']);
    $stored = CmsModule::where('identifier', 'ai_assistant')->firstOrFail()->settings;
    expect($stored)->not->toHaveKey('higgsfield_api_key');
    expect($stored['higgsfield_api_key_id'])->not->toBe('account-id');
    expect(Crypt::decryptString($stored['higgsfield_api_key_id']))->toBe('account-id');
    expect(Crypt::decryptString($stored['higgsfield_api_key_secret']))->toBe('account-secret');
    expect(app(AiProviderSettings::class)->apiKey('higgsfield'))->toBe('account-id:account-secret');
});
test('masked Higgsfield edits preserve both fields and rotate a separate secret', function (): void {
    $bridge = app(AiConfigBridgeInterface::class);
    $bridge->saveProviderConfig('higgsfield', ['api_key_id' => 'account-id', 'api_key_secret' => 'account-secret']);
    $bridge->saveProviderConfig('higgsfield', ['api_key_id' => AiProviderSettings::MASKED_KEY, 'api_key_secret' => '']);
    expect(app(AiProviderSettings::class)->apiKey('higgsfield'))->toBe('account-id:account-secret');
    $bridge->saveProviderConfig('higgsfield', ['api_key_secret' => 'rotated-secret']);
    expect(app(AiProviderSettings::class)->apiKey('higgsfield'))->toBe('account-id:rotated-secret');
});
test('new incomplete Higgsfield credentials are rejected without persisting a partial secret', function (): void {
    app(AiProviderSettings::class)->save('higgsfield', ['api_key_id' => 'partial-id']);
})->throws(ValidationException::class);
test('rotating only Higgsfield ID cannot forward an existing secret to a different endpoint', function (): void {
    $settings = app(AiProviderSettings::class);
    $settings->save('higgsfield', ['api_key_id' => 'id-one', 'api_key_secret' => 'secret-one']);
    $settings->save('higgsfield', ['api_key_id' => 'id-two', 'base_url' => 'https://example.com']);
})->throws(InvalidArgumentException::class);
test('Higgsfield credential rotation invalidates the earlier connection fingerprint', function (): void {
    $bridge = app(AiConfigBridgeInterface::class);
    $bridge->saveProviderConfig('higgsfield', ['api_key_id' => 'id-one', 'api_key_secret' => 'secret-one']);
    $key = 'ai_assistant:models:higgsfield:'.hash('sha256', 'id-one:secret-one|');
    Cache::put($key.':connection', 'verified', 60);
    $bridge->saveProviderConfig('higgsfield', ['api_key_secret' => 'secret-two']);
    expect(app(AiProviderSettings::class)->apiKey('higgsfield'))->toBe('id-one:secret-two');
    expect(Cache::has($key.':connection'))->toBeFalse();
});
test('a text connection check makes a catalogue GET and never starts inference', function (): void {
    Http::fake(['generativelanguage.googleapis.com/v1beta/models' => Http::response(['models' => [['name' => 'models/gemini-test', 'supportedGenerationMethods' => ['generateContent']]]])]);
    app(AiConfigBridgeInterface::class)->saveProviderConfig('gemini', ['api_key' => 'fake-key', 'model' => 'gemini-test']);
    $result = app(AiAssistantDomainServiceInterface::class)->testProviderConnection('gemini', 'text');
    expect($result['success'])->toBeTrue();
    expect($result['generation_verified'])->toBeFalse();
    Http::assertSent(fn ($request): bool => $request->method() === 'GET');
    Http::assertNotSent(fn ($request): bool => $request->method() === 'POST');
});
test('visual providers without a readonly authentication probe remain explicitly unverified', function (): void {
    app(AiConfigBridgeInterface::class)->saveProviderConfig('higgsfield', ['api_key_id' => 'id', 'api_key_secret' => 'secret', 'video_model' => 'bytedance/seedance-2.0/text-to-video']);
    $result = app(AiAssistantDomainServiceInterface::class)->testProviderConnection('higgsfield', 'video');
    expect($result['success'])->toBeFalse();
    expect($result['connection_state'])->toBe('configured_unverified');
    expect($result['generation_verified'])->toBeFalse();
    Http::assertNothingSent();
});
test('connection checks expose three independent modalities without creating unsupported inference', function (): void {
    $service = app(AiAssistantDomainServiceInterface::class);
    foreach (['text', 'image', 'video'] as $modality) {
        $result = $service->testProviderConnection('anthropic', $modality);
        expect($result['modality'])->toBe($modality);
        expect($result['generation_verified'])->toBeFalse();
    }Http::assertNothingSent();
});

test('Higgsfield rejects the retired combined credential field', function (): void {
    app(AiProviderSettings::class)->save('higgsfield', ['api_key' => 'account-id:account-secret']);
})->throws(ValidationException::class);
