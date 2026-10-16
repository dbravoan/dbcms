<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Content\Pages\Models\Page;
use App\Contracts\Services\AdminDashboardServiceInterface;
use App\Core\Modules\ModuleManager;
use App\Models\CmsModule;
use App\Models\Market;
use App\Models\User;

final class AdminDashboardService implements AdminDashboardServiceInterface
{
    public function __construct(private readonly ModuleManager $modules) {}

    /** @return array<string, mixed> */
    public function data(User $user): array
    {
        $fresh = User::findOrFail((int) $user->getKey());
        abort_unless(! $fresh->is_service_account && ($fresh->isAdmin() || $fresh->hasPermission('access:admin')), 403);
        $allowed = static fn (string $permission): bool => $fresh->isAdmin() || $fresh->hasPermission($permission);
        $contributions = [];
        foreach ($this->modules->getActiveModules() as $module) {
            $contribution = $module->getAdminDashboard($fresh);
            if ($contribution !== null) {
                $contributions[] = $contribution;
            }
        }

        return [
            'stats' => [
                'total_users' => $allowed('manage:users') ? User::count() : 0,
                'active_markets' => $allowed('manage:modules') ? Market::where('is_active', true)->count() : 0,
                'installed_modules' => $allowed('manage:modules') ? CmsModule::where('is_installed', true)->count() : 0,
                'total_pages' => $allowed('manage:pages') ? Page::count() : 0,
            ],
            'moduleDashboard' => $contributions,
            'hasPages' => $allowed('manage:pages'),
            'canManageModules' => $allowed('manage:modules'),
            'canManageUsers' => $allowed('manage:users'),
            'hasSeo' => $allowed('manage:seo'),
        ];
    }
}
