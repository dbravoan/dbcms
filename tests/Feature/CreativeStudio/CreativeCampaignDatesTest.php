<?php

declare(strict_types=1);
use App\Core\Capabilities\ActorContext;
use App\Core\Modules\ModuleManager;
use App\Models\Language;
use App\Models\Market;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Modules\CreativeStudio\Services\CreativeStudioDomainService;
use Tests\Support\AiMcpFixture;

beforeEach(function (): void {
    Http::preventStrayRequests();
    app(ModuleManager::class)->discoverModules();
    app(ModuleManager::class)->activate('creative-studio');
    $this->actor = new ActorContext(AiMcpFixture::admin()->id);
    $market = Market::firstOrCreate(['code' => 'es'], ['name' => 'España', 'is_active' => true]);
    $language = Language::firstOrCreate(['code' => 'es'], ['name' => 'Español', 'native_name' => 'Español', 'is_active' => true]);
    $market->languages()->syncWithoutDetaching([$language->id]);
    $this->input = ['title' => 'Invierno', 'brief' => 'Una campaña de invierno', 'mode' => 'free', 'objective' => 'Visitas', 'audience' => 'Familias', 'locale' => 'es', 'market_ids' => [$market->id], 'budget_limit' => 0, 'currency' => 'USD'];
});
test('campaign dates are optional and offset dates are normalized to UTC', function (): void {
    $service = app(CreativeStudioDomainService::class);
    $empty = $service->createCampaign($this->input, $this->actor);
    expect($empty->starts_at)->toBeNull();
    $dated = $service->createCampaign($this->input + ['starts_at' => '2026-11-01T10:00:00+01:00', 'ends_at' => '2026-11-20T10:00:00+01:00'], $this->actor);
    expect($dated->starts_at->utc()->format('Y-m-d H:i'))->toBe('2026-11-01 09:00');
});
test('campaign end cannot precede its start', function (): void {
    app(CreativeStudioDomainService::class)->createCampaign($this->input + ['starts_at' => '2026-11-20T09:00:00Z', 'ends_at' => '2026-11-01T09:00:00Z'], $this->actor);
})->throws(ValidationException::class);
test('updating campaign dates leaves a previous revision window sealed', function (): void {
    $service = app(CreativeStudioDomainService::class);
    $campaign = $service->createCampaign($this->input + ['starts_at' => '2026-11-01T09:00:00Z', 'ends_at' => '2026-11-20T09:00:00Z'], $this->actor);
    $revision = $service->createRevision($campaign->id, ['text' => 'Llega el invierno', 'locale' => 'es', 'market' => 'es', 'format' => 'text'], $this->actor);
    $before = $service->getPackage($revision->id, $this->actor)->fingerprint();
    $service->setDates($campaign->id, ['starts_at' => '2026-12-01T09:00:00Z', 'ends_at' => '2026-12-20T09:00:00Z'], $this->actor);
    expect($service->getPackage($revision->id, $this->actor)->fingerprint())->toBe($before);
    expect($service->getPackage($revision->id, $this->actor)->provenance['campaign_window']['starts_at'])->toContain('2026-11-01');
});
