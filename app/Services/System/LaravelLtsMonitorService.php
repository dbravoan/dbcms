<?php

declare(strict_types=1);

namespace App\Services\System;

use DateTimeImmutable;
use Illuminate\Support\Facades\File;

class LaravelLtsMonitorService
{
    /** @var array<int, array{string, string}> */
    private const SUPPORT_WINDOWS = [
        11 => ['2025-09-03', '2026-03-12'],
        12 => ['2026-08-13', '2027-02-24'],
        13 => ['2027-09-17', '2028-03-17'],
    ];

    /**
     * Compatibility entry point for the historical system:check-lts command.
     *
     * @return array{current_version: string, is_lts: bool, support_active: bool, bug_fixes_active: bool, bug_fixes_until: ?string, security_fixes_until: ?string, security_audit: string, recommendation: string}
     */
    public function checkLtsStatus(): array
    {
        return $this->statusForVersion(app()->version(), new DateTimeImmutable('now'));
    }

    /**
     * Laravel releases annually: 18 months of bug fixes and two years of security fixes.
     * Laravel 13 bug-fix date derives from its release date; the official table says Q3 2027.
     *
     * @return array{current_version: string, is_lts: bool, support_active: bool, bug_fixes_active: bool, bug_fixes_until: ?string, security_fixes_until: ?string, security_audit: string, recommendation: string}
     */
    public function statusForVersion(string $version, DateTimeImmutable $today): array
    {
        $window = self::SUPPORT_WINDOWS[(int) explode('.', $version)[0]] ?? null;
        $securityActive = $window !== null && $today->format('Y-m-d') <= $window[1];
        $bugFixesActive = $window !== null && $today->format('Y-m-d') <= $window[0];

        return [
            'current_version' => $version,
            'is_lts' => false,
            'support_active' => $securityActive,
            'bug_fixes_active' => $bugFixesActive,
            'bug_fixes_until' => $window[0] ?? null,
            'security_fixes_until' => $window[1] ?? null,
            'security_audit' => 'not_run',
            'recommendation' => $window === null ? 'Unknown support window: verify the official release policy.' : ($securityActive ? ($bugFixesActive ? 'Annual Laravel release within its bug-fix and security support windows.' : 'Security fixes only; plan an upgrade to a branch receiving bug fixes.') : 'Security support expired; upgrade to a supported release.'),
        ];
    }

    /**
     * Writes an automated proposal file if upgrades or issues are detected.
     */
    public function generateUpgradeProposal(): string
    {
        $status = $this->checkLtsStatus();
        $targetFile = base_path('docs/LTS_UPGRADE_PROPOSAL.md');

        $content = "# Laravel Support Assessment\n\n";
        $content .= '> Generated automatically by `LtsWatchdog` Agent on '.date('Y-m-d H:i:s')."\n\n";
        $content .= "## Status Summary\n";
        $content .= "- **Current Version**: `{$status['current_version']}`\n";
        $content .= "- **Release policy**: annual releases; no LTS designation.\n";
        $content .= '- **Bug fixes until**: '.($status['bug_fixes_until'] ?? 'Unknown')."\n";
        $content .= '- **Security fixes until**: '.($status['security_fixes_until'] ?? 'Unknown')."\n";
        $content .= '- **Support Active**: '.($status['support_active'] ? '✅ Active' : '❌ Expired')."\n";
        $content .= "- **Assessment**: {$status['recommendation']}\n\n";
        $content .= "Source: https://laravel.com/docs/13.x/releases (13.x bug fixes: Q3 2027; 2027-09-17 calculated from the 18-month policy).\n\n";
        $content .= "No vulnerability audit was performed by this command. Run `./vendor/bin/sail composer audit --locked` separately; lifecycle support does not prove absence of advisories.\n\n";
        $content .= "## Safe Upgrade Protocol (Zero-Host)\n";
        $content .= "```bash\n";
        $content .= "./vendor/bin/sail composer update laravel/framework --with-dependencies\n";
        $content .= "./vendor/bin/sail pest\n";
        $content .= "./vendor/bin/sail bin phpstan analyse --memory-limit=2G\n";
        $content .= "```\n";

        File::put($targetFile, $content);

        return $targetFile;
    }
}
