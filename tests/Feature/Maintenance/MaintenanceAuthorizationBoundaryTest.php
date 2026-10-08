<?php

declare(strict_types=1);

use App\Core\Modules\ModuleManager;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Modules\Maintenance\Services\MaintenanceService;

test('a role without backoffice access does not bypass public maintenance', function (): void {
    app(ModuleManager::class)->discoverModules();
    app(MaintenanceService::class)->enable();
    $user = User::factory()->create();
    $user->assignRole(Role::firstOrCreate(['slug' => 'custom-no-access'], ['name' => 'No backoffice access']));
    try {
        $this->actingAs($user)->get('/')->assertStatus(503);
    } finally {
        app(MaintenanceService::class)->disable();
        File::delete(storage_path('framework/cms-maintenance.json'));
    }
});
