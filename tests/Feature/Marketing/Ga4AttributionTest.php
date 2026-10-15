<?php

declare(strict_types=1);

use App\Contracts\Services\CampaignAttributionGatewayInterface;
use App\Contracts\Services\ModuleSettingsServiceInterface;
use App\Core\Modules\ModuleManager;
use App\Models\CmsModule;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    config(['cms.installation_id' => 'ga4-installation-a']);
    app(ModuleManager::class)->discoverModules();
    app(ModuleManager::class)->activate('analytics');
    Http::preventStrayRequests();
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($key, $private);
    $this->publicKey = openssl_pkey_get_details($key)['key'];
    $this->credentials = json_encode(['type' => 'service_account', 'client_email' => 'analytics@test-project.iam.gserviceaccount.com', 'private_key' => $private, 'private_key_id' => 'test-key', 'token_uri' => 'https://attacker.invalid/token'], JSON_THROW_ON_ERROR);
    app(ModuleSettingsServiceInterface::class)->setMany('analytics', ['ga4_property_id' => '12345', 'ga4_service_account_json' => $this->credentials, 'ga4_conversion_events' => 'generate_lead,purchase']);
});

function ga4TestResponse(int $sessions = 12, string $events = '2.5'): array
{
    return ['metricHeaders' => [['name' => 'sessions'], ['name' => 'keyEvents']], 'rows' => [['metricValues' => [['value' => (string) $sessions], ['value' => $events]]]], 'rowCount' => 1, 'metadata' => ['subjectToThresholding' => true]];
}

test('GA4 reporting signs read only JWT and filters exact campaign and content with truthful metrics', function (): void {
    Http::fake(['oauth2.googleapis.com/token' => Http::response(['access_token' => 'fixture-token', 'expires_in' => 3600, 'token_type' => 'Bearer']), 'analyticsdata.googleapis.com/v1beta/properties/12345:runReport' => Http::response(ga4TestResponse())]);
    $report = app(CampaignAttributionGatewayInterface::class)->report('campaña-exacta', 'piece-1', '2026-10-01', '2026-10-13');
    expect($report['status'])->toBe('available')->and($report['source'])->toBe('ga4')->and($report['sessions'])->toBe(12)->and($report['key_events'])->toBe(2.5)->and($report['limitations'])->toContain('subject_to_thresholding');
    Http::assertSent(function ($request): bool {
        if ($request->url() !== 'https://oauth2.googleapis.com/token') {
            return false;
        }
        [$header, $payload, $signature] = explode('.', $request['assertion']);
        $decode = static fn (string $part): string => base64_decode(strtr($part, '-_', '+/'));
        $claims = json_decode($decode($payload), true);
        expect($claims['scope'])->toBe('https://www.googleapis.com/auth/analytics.readonly')->and($claims['aud'])->toBe('https://oauth2.googleapis.com/token');
        expect(openssl_verify($header.'.'.$payload, $decode($signature), $this->publicKey, OPENSSL_ALGO_SHA256))->toBe(1);

        return $request['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer';
    });
    Http::assertSent(function ($request): bool {
        if (! str_contains($request->url(), ':runReport')) {
            return false;
        }
        $expressions = $request['dimensionFilter']['andGroup']['expressions'];
        expect($expressions[0]['filter'])->toBe(['fieldName' => 'sessionManualCampaignName', 'stringFilter' => ['matchType' => 'EXACT', 'value' => 'campaña-exacta', 'caseSensitive' => true]])
            ->and($expressions[1]['filter']['fieldName'])->toBe('sessionManualAdContent');

        return $request->hasHeader('Authorization', 'Bearer fixture-token');
    });
});

test('cached OAuth tokens are reused but successful reports stay bound to installation and credentials', function (): void {
    Http::fake(['oauth2.googleapis.com/token' => Http::response(['access_token' => 'fixture-token', 'expires_in' => 3600, 'token_type' => 'Bearer']), 'analyticsdata.googleapis.com/*' => Http::sequence()->push(ga4TestResponse())->push([], 503)->push([], 503)->push([], 503)]);
    $gateway = app(CampaignAttributionGatewayInterface::class);
    $initial = $gateway->report('campaign', 'piece', '2026-10-01', '2026-10-13');
    $stale = $gateway->report('campaign', 'piece', '2026-10-01', '2026-10-13');
    expect($stale['status'])->toBe('stale')->and($stale['sessions'])->toBe(12)->and($stale['fetched_at'])->toBe($initial['fetched_at']);
    app(ModuleSettingsServiceInterface::class)->set('analytics', 'ga4_service_account_json', str_replace('test-key', 'new-key', $this->credentials));
    expect($gateway->report('campaign', 'piece', '2026-10-01', '2026-10-13')['status'])->toBe('unavailable');
    config(['cms.installation_id' => 'ga4-installation-b']);
    expect($gateway->report('campaign', 'piece', '2026-10-01', '2026-10-13')['status'])->toBe('unavailable');
    expect(collect(Http::recorded())->filter(fn ($pair): bool => str_contains($pair[0]->url(), '/token')))->toHaveCount(3);
});

test('inactive or unconfigured Analytics does not contact Google or fabricate zero', function (): void {
    app(ModuleManager::class)->deactivate('analytics');
    $gateway = app(CampaignAttributionGatewayInterface::class);
    expect($gateway->report('campaign', 'piece', '2026-10-01', '2026-10-13')['sessions'])->toBeNull();
    app(ModuleManager::class)->activate('analytics');
    app(ModuleSettingsServiceInterface::class)->set('analytics', 'ga4_property_id', '');
    expect($gateway->report('campaign', 'piece', '2026-10-01', '2026-10-13')['status'])->toBe('unavailable');
    Http::assertNothingSent();
});

test('invalid report windows are rejected before any provider call', function (): void {
    $gateway = app(CampaignAttributionGatewayInterface::class);
    foreach ([['2026-02-30', '2026-03-01'], ['2026-10-13', '2026-10-01'], ['2025-01-01', '2026-10-13']] as [$from, $to]) {
        expect(fn () => $gateway->report('campaign', 'piece', $from, $to))->toThrow(ValidationException::class);
    }
    Http::assertNothingSent();
});

test('missing metric headers are unavailable but confirmed empty reports legitimately return zero', function (): void {
    Http::fake(['oauth2.googleapis.com/token' => Http::response(['access_token' => 'fixture-token', 'expires_in' => 3600, 'token_type' => 'Bearer']), 'analyticsdata.googleapis.com/*' => Http::sequence()->push([])->push(['metricHeaders' => [['name' => 'sessions'], ['name' => 'keyEvents']], 'rowCount' => 0])]);
    $gateway = app(CampaignAttributionGatewayInterface::class);
    expect($gateway->report('campaign', 'piece', '2026-10-01', '2026-10-13')['sessions'])->toBeNull();
    $empty = $gateway->report('campaign', 'piece', '2026-10-01', '2026-10-13');
    expect($empty['status'])->toBe('available')->and($empty['sessions'])->toBe(0)->and($empty['key_events'])->toBe(0.0);
});

test('service account JSON is encrypted centrally and never echoed by the settings screen', function (): void {
    expect(CmsModule::where('identifier', 'analytics')->firstOrFail()->settings['ga4_service_account_json'])->not->toContain('BEGIN PRIVATE KEY');
    $admin = User::factory()->create();
    $admin->roles()->attach(Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']));
    $this->actingAs($admin)->get(route('admin.modules.settings', ['identifier' => 'analytics']))->assertOk()->assertDontSee('BEGIN PRIVATE KEY')->assertDontSee('analytics@test-project.iam.gserviceaccount.com');
    expect(fn () => app(ModuleSettingsServiceInterface::class)->set('analytics', 'ga4_property_id', '../other'))->toThrow(InvalidArgumentException::class);
});
