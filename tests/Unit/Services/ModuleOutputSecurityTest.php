<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Contracts\Services\ModuleSettingsServiceInterface;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Foundation\Application as ApplicationContract;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Ads\Services\AdSenseManagerService;
use Modules\Analytics\Services\AnalyticsManagerService;
use Modules\Captcha\Services\CaptchaService;
use Modules\Meta\Services\MetaCapiService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ModuleOutputSecurityTest extends TestCase
{
    private Container $previousContainer;

    private ?ApplicationContract $previousFacadeApplication;

    /** @var array<string,mixed> */
    private array $settings = [];

    protected function setUp(): void
    {
        $this->previousContainer = Container::getInstance();
        $this->previousFacadeApplication = Facade::getFacadeApplication();
        $container = new Application(dirname(__DIR__, 3));
        $container->instance('config', new ConfigRepository);
        $container->instance('encrypter', new Encrypter(random_bytes(32), 'AES-256-CBC'));
        $container->instance('cache', new Repository(new ArrayStore));
        $vite = new Vite;
        $vite->useCspNonce('audit-nonce');
        $container->instance(Vite::class, $vite);
        $settings = $this->createMock(ModuleSettingsServiceInterface::class);
        $settings->method('get')->willReturnCallback(fn (string $module, string $key, mixed $default): mixed => $this->settings[$module.'.'.$key] ?? $default);
        $container->instance(ModuleSettingsServiceInterface::class, $settings);
        Container::setInstance($container);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($container);
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->previousFacadeApplication);
        Container::setInstance($this->previousContainer);
    }

    public function test_correct_captcha_is_accepted_exactly_once_and_wrong_answers_do_not_consume_it(): void
    {
        $service = new CaptchaService;
        $challenge = $service->generateChallenge();
        preg_match('/(\d+)\s*\+\s*(\d+)\s*=/', $challenge['svg'], $operands);
        if (! isset($operands[1], $operands[2])) {
            throw new \LogicException('The generated challenge must contain both operands.');
        }
        $answer = (string) ((int) $operands[1] + (int) $operands[2]);

        self::assertFalse($service->verify('incorrect', $challenge['token']));
        self::assertTrue($service->verify($answer, $challenge['token']));
        self::assertFalse($service->verify($answer, $challenge['token']));
    }

    public function test_expired_and_tampered_captcha_are_rejected(): void
    {
        $service = new CaptchaService;
        $expired = Crypt::encryptString(json_encode(['answer' => '5', 'expires_at' => time() - 1], JSON_THROW_ON_ERROR));
        self::assertFalse($service->verify('5', $expired));
        self::assertFalse($service->verify('5', $expired.'modified'));
    }

    public function test_unconfigured_analytics_does_not_load_a_placeholder_account(): void
    {
        self::assertSame('', (new AnalyticsManagerService)->renderGa4Script());
    }

    public function test_analytics_rejects_script_injection_and_keeps_a_valid_consent_gated_account(): void
    {
        $service = new AnalyticsManagerService;
        self::assertSame('', $service->renderGa4Script("G-X';globalThis.__audit=1;//"));
        self::assertSame('', $service->renderGa4Script('G-ABC123</script><script>globalThis.__audit=1</script>'));
        $html = $service->renderGa4Script('G-ABC1234567');
        self::assertStringContainsString('G-ABC1234567', $html);
        self::assertStringContainsString('cookie-consent-updated', $html);
        self::assertStringContainsString('nonce="audit-nonce"', $html);
    }

    public function test_ad_slot_injection_is_rejected_and_configured_units_are_safe(): void
    {
        $this->settings['ads.adsense_client_id'] = 'ca-pub-1234567890123456';
        $service = new AdSenseManagerService;
        self::assertSame('', $service->renderAdUnit('" onpointerover="globalThis.__audit=1'));
        $html = $service->renderAdUnit('1234567890', 'auto" onclick="globalThis.__audit=1');
        self::assertStringNotContainsString('onclick="globalThis', $html);
        self::assertStringContainsString('data-ad-slot="1234567890"', $html);
        self::assertStringContainsString('nonce="audit-nonce"', $html);
    }

    public function test_unconfigured_ads_do_not_emit_a_placeholder_publisher(): void
    {
        self::assertSame('', (new AdSenseManagerService)->renderAdUnit('1234567890'));
    }

    public function test_meta_sends_credentials_only_in_the_post_body(): void
    {
        $this->settings['meta.pixel_id'] = '1234567890';
        $this->settings['meta.capi_token'] = 'FAKE-audit-credential';
        Http::swap(new HttpFactory);
        Http::preventStrayRequests();
        Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1])]);

        self::assertTrue((new MetaCapiService)->sendConversionEvent('Lead'));
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://graph.facebook.com/v19.0/1234567890/events'
            && $request['access_token'] === 'FAKE-audit-credential'
            && $request['data'][0]['event_name'] === 'Lead');
    }

    public function test_meta_errors_never_log_provider_messages_or_credentials(): void
    {
        $this->settings['meta.pixel_id'] = '1234567890';
        $this->settings['meta.capi_token'] = 'FAKE-audit-credential';
        Http::swap(new HttpFactory);
        Http::preventStrayRequests();
        Http::fake(fn (): never => throw new ConnectionException('Failed URL containing FAKE-audit-credential'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')->with('Meta CAPI dispatch failed.', ['exception_class' => ConnectionException::class]);
        Log::swap($logger);

        self::assertFalse((new MetaCapiService)->sendConversionEvent('Lead'));
    }
}
