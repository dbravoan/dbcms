<?php

declare(strict_types=1);

use App\Contracts\Services\CampaignAttributionGatewayInterface;
use App\Contracts\Services\MarketingArtifactStoreInterface;
use App\Core\Capabilities\CapabilityAuthorizer;
use App\DTOs\Marketing\ArtifactDescriptor;
use App\DTOs\Marketing\CreativePackage;
use App\Enums\OAuthScope;
use Illuminate\Console\Scheduling\Schedule;

test('marketing REST scopes cannot fall back to content management authority', function (): void {
    $policy = app(CapabilityAuthorizer::class);
    expect($policy->scopeFor('read', 'api', 'manage:creative-studio'))->toBe(OAuthScope::CREATIVE_READ->value)
        ->and($policy->scopeFor('write', 'api', 'manage:creative-studio'))->toBe(OAuthScope::CREATIVE_WRITE->value)
        ->and($policy->scopeFor('read', 'api', 'manage:social-publishing'))->toBe(OAuthScope::SOCIAL_READ->value)
        ->and($policy->scopeFor('write', 'api', 'manage:social-accounts'))->toBe(OAuthScope::SOCIAL_ACCOUNTS->value)
        ->and($policy->scopeFor('write', 'mcp', 'manage:social-publishing'))->toBe(OAuthScope::MCP_WRITE->value);
});

test('private artifacts preserve real formats and detect changed bytes without exposing public URLs', function (): void {
    config(['marketing.artifact_root' => storage_path('framework/testing/marketing-'.bin2hex(random_bytes(8)))]);
    $image = imagecreatetruecolor(12, 16);
    $stream = fopen('php://temp', 'w+b');
    imagepng($image, $stream);
    rewind($stream);
    $store = app(MarketingArtifactStoreInterface::class);
    $artifact = $store->writeStream($stream, 'creative-studio/campaign-1', 'brand.png', 'image/png');
    fclose($stream);
    expect($artifact->mimeType)->toBe('image/png')->and($artifact->width)->toBe(12)
        ->and($artifact->height)->toBe(16)->and($store->verify($artifact))->toBeTrue()
        ->and($artifact->path)->not->toContain('public/');
    $copy = $store->copy($artifact, 'social-publishing/publication-1');
    expect($copy->path)->not->toBe($artifact->path)->and($copy->sha256)->toBe($artifact->sha256);
    file_put_contents($store->absolutePath($artifact), 'changed');
    expect($store->verify($artifact))->toBeFalse()->and($store->verify($copy))->toBeTrue();
});

test('private artifact import rejects executable bytes and unsafe owner paths', function (): void {
    $store = app(MarketingArtifactStoreInterface::class);
    $stream = fopen('php://temp', 'w+b');
    fwrite($stream, '<?php echo "unsafe";');
    rewind($stream);
    expect(fn () => $store->writeStream($stream, 'social-publishing/one', 'image.png', 'image/png'))
        ->toThrow(InvalidArgumentException::class);
    rewind($stream);
    expect(fn () => $store->writeStream($stream, '../public', 'image.png'))
        ->toThrow(InvalidArgumentException::class);
    fclose($stream);
});

test('package fingerprints preserve artifact order and round trip immutable snapshots', function (): void {
    $a = new ArtifactDescriptor('a', 'creative-studio/a/a.png', str_repeat('a', 64), 'image/png', 20);
    $b = new ArtifactDescriptor('b', 'creative-studio/a/b.png', str_repeat('b', 64), 'image/png', 21);
    $one = new CreativePackage('p', 'r', 'c', 'es', 'es', 'Texto', 'https://example.com/es', [$a, $b]);
    $two = new CreativePackage('p', 'r', 'c', 'es', 'es', 'Texto', 'https://example.com/es', [$b, $a]);
    expect(CreativePackage::fromArray($one->toArray())->fingerprint())->toBe($one->fingerprint())
        ->and($two->fingerprint())->not->toBe($one->fingerprint());
});

test('unconfigured attribution remains unavailable instead of fabricating zero visits', function (): void {
    config(['modules.analytics.ga4_property_id' => '', 'modules.analytics.ga4_service_account' => '']);
    $result = app(CampaignAttributionGatewayInterface::class)->report('campaign', 'piece', '2026-10-01', '2026-10-13');
    expect($result['status'])->toBe('unavailable')->and($result['sessions'])->toBeNull()->and($result['key_events'])->toBeNull();
});

test('marketing storage rejects public roots and unsafe installation identifiers', function (): void {
    $store = app(MarketingArtifactStoreInterface::class);
    $stream = fopen('php://temp', 'w+b');
    $image = imagecreatetruecolor(2, 2);
    imagepng($image, $stream);
    rewind($stream);
    config(['marketing.artifact_root' => public_path('marketing-artifacts')]);
    expect(fn () => $store->writeStream($stream, 'test', 'image.png'))->toThrow(InvalidArgumentException::class);
    rewind($stream);
    config(['marketing.artifact_root' => null, 'cms.installation_id' => '../public']);
    expect(fn () => $store->writeStream($stream, 'test', 'image.png'))->toThrow(InvalidArgumentException::class);
    fclose($stream);
});

test('marketing scheduler mutexes remain independent for installations sharing a cache', function (): void {
    $collect = function (string $installation): array {
        config(['cms.installation_id' => $installation, 'marketing.installation_id' => $installation]);
        app()->forgetInstance(Schedule::class);
        $events = app(Schedule::class)->events();
        $names = [];
        foreach ($events as $event) {
            if (is_string($event->command) && (str_contains($event->command, 'creative-studio:') || str_contains($event->command, 'social-publishing:'))) {
                $names[$event->command] = $event->mutexName();
            }
        }

        return $names;
    };
    $first = $collect('installation-alpha');
    $second = $collect('installation-beta');
    expect($first)->toHaveCount(3)->and(array_keys($second))->toBe(array_keys($first));
    foreach ($first as $command => $mutex) {
        expect($second[$command])->not->toBe($mutex);
    }
    expect(count(array_unique($first)))->toBe(3);
});
