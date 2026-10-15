<?php

declare(strict_types=1);

use App\Services\Cms\PublicPagePersonalization;
use App\Services\Cms\ThemeManagerService;
use App\Services\Install\EnvValueSerializer;
use Dotenv\Dotenv;
use Modules\Shop\Services\CheckoutPolicy;

it('rejects simulated payment methods without changing offline methods', function (): void {
    expect(CheckoutPolicy::paymentMethod('bank_transfer'))->toBe('bank_transfer');
    expect(CheckoutPolicy::paymentMethod('cod'))->toBe('cod');
    expect(fn () => CheckoutPolicy::paymentMethod('stripe'))->toThrow(InvalidArgumentException::class);
    expect(fn () => CheckoutPolicy::paymentMethod('card'))->toThrow(InvalidArgumentException::class);
});

it('never stores visitor fragments or csrf tokens in the shared snapshot', function (): void {
    $themes = Mockery::mock(ThemeManagerService::class);
    $themes->shouldReceive('getAvailableThemes')->andReturn(['db-cyber' => []]);
    $personalization = new PublicPagePersonalization($themes);
    $html = '<main>Public</main><!--cms-private:themes.db-cyber.partials.header:start--><b>Alice cart</b><!--cms-private:end--><input value="first-session-token">';
    $snapshot = $personalization->snapshot($html, 'first-session-token');
    expect($snapshot)->not->toContain('Alice cart')->not->toContain('first-session-token');
    $second = $personalization->hydrate($snapshot, 'second-session-token', fn (string $view): string => '<b>Bob cart</b>');
    expect($second)->toContain('Public', 'Bob cart', 'second-session-token')->not->toContain('Alice');
});

it('rejects unregistered private fragment views from cached content', function (): void {
    $personalization = new PublicPagePersonalization(Mockery::mock(ThemeManagerService::class));
    expect(fn () => $personalization->hydrate('<!--cms-fragment:admin.users.index-->', 'token', fn () => 'private'))
        ->toThrow(UnexpectedValueException::class);
});

it('serializes dotenv values without injecting keys or interpolating secrets', function (): void {
    $value = 'password $HOME # \\ " quoted';
    $encoded = EnvValueSerializer::encode($value);
    expect(Dotenv::parse('DB_PASSWORD='.$encoded)['DB_PASSWORD'])->toBe($value);
    expect(fn () => EnvValueSerializer::encode("value\nAPP_DEBUG=true"))->toThrow(InvalidArgumentException::class);
});
