<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\DTOs\Admin\AdminNavigationSection;
use App\Models\User;

interface AdminNavigationServiceInterface
{
    /**
     * Get structured navigation sections filtered by module enablement and user permissions.
     *
     * @return array<int, AdminNavigationSection>
     */
    public function getNavigationSections(?User $user = null): array;

    /**
     * Get identifiers of all active and enabled modules.
     *
     * @return array<int, string>
     */
    public function getActiveModuleIdentifiers(): array;

    /**
     * Get pending content audit items count.
     */
    public function getPendingAuditCount(): int;

    /**
     * Check if maintenance mode is active.
     */
    public function isMaintenanceActive(): bool;
}
