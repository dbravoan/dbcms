<?php

declare(strict_types=1);

use App\Services\System\LaravelLtsMonitorService;

it('reports annual Laravel support without claiming LTS or a vulnerability audit', function (): void {
    $status = (new LaravelLtsMonitorService)->statusForVersion('13.32.0', new DateTimeImmutable('2026-10-10'));
    expect($status['is_lts'])->toBeFalse()
        ->and($status['bug_fixes_until'])->toBe('2027-09-17')
        ->and($status['security_fixes_until'])->toBe('2028-03-17')
        ->and($status['support_active'])->toBeTrue()
        ->and($status['security_audit'])->toBe('not_run');
});

it('uses the installed major version support window and rejects expired or unknown branches', function (): void {
    $monitor = new LaravelLtsMonitorService;
    expect($monitor->statusForVersion('11.99.0', new DateTimeImmutable('2026-10-10'))['support_active'])->toBeFalse()
        ->and($monitor->statusForVersion('12.1.0', new DateTimeImmutable('2026-10-10'))['bug_fixes_active'])->toBeFalse()
        ->and($monitor->statusForVersion('99.0.0', new DateTimeImmutable('2026-10-10'))['support_active'])->toBeFalse();
});
