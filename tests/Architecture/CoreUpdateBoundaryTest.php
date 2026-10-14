<?php

declare(strict_types=1);

use App\Contracts\Services\UpdateExecutionServiceInterface;
use App\Core\Updates\ReleaseManifest;
use App\Services\Updates\UpdateExecutionService;

arch('core update application is not an AI MCP or content capability')
    ->expect(['Modules\\AiAssistant', 'Modules\\Mcp', 'App\\Services\\Capabilities', 'App\\Services\\Orchestration'])
    ->not->toUse([UpdateExecutionServiceInterface::class, UpdateExecutionService::class]);

arch('signed release manifests are immutable')->expect(ReleaseManifest::class)->toBeReadonly();

arch('update controllers only adapt HTTP to common contracts')
    ->expect('App\\Http\\Controllers\\Admin\\Updates')
    ->not->toUse(['App\\Models', 'App\\Services\\Updates', 'Illuminate\\Support\\Facades\\DB', 'PDO', 'ZipArchive']);
