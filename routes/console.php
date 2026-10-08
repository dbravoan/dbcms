<?php

declare(strict_types=1);

use App\Contracts\Services\GeoExportServiceInterface;
use App\Services\System\LaravelLtsMonitorService;
use Illuminate\Support\Facades\Artisan;

Artisan::command('geo:generate-llms-txt {--output= : Absolute private export directory}', function (GeoExportServiceInterface $service): int {
    try {
        $output = $this->option('output');
        if ($output !== null && ! is_string($output)) {
            throw new InvalidArgumentException('The export directory must be a string.');
        }
        $paths = $service->export($output);
    } catch (InvalidArgumentException $exception) {
        $this->error($exception->getMessage());

        return 1;
    }
    $this->info('Private GEO snapshots exported: '.implode(', ', $paths).'. Live public documents remain dynamic.');

    return 0;
})->purpose('Exports private offline GEO snapshots without replacing dynamic public routes');

Artisan::command('system:check-lts', function (LaravelLtsMonitorService $monitor): void {
    $this->info('Inspecting Laravel annual release support (no vulnerability audit)...');

    $status = $monitor->checkLtsStatus();
    $this->table(['Metric', 'Status'], [
        ['Current Laravel Version', $status['current_version']],
        ['Release Policy', 'Annual releases: 18 months bug fixes, 2 years security fixes'],
        ['Bug Fixes Until', $status['bug_fixes_until'] ?? 'Unknown'],
        ['Security Fixes Until', $status['security_fixes_until'] ?? 'Unknown'],
        ['Vulnerability Audit', 'Not run; execute composer audit --locked separately'],
        ['Support Window Active', $status['support_active'] ? 'Active' : 'Expired'],
        ['Recommendation', $status['recommendation']],
    ]);

    $proposalPath = $monitor->generateUpgradeProposal();
    $this->info("Generated/Updated proposal at: {$proposalPath}");
})->purpose('Reports framework support lifecycle and writes an assessment (legacy command name)');
