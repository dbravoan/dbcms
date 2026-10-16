<?php

declare(strict_types=1);

namespace App\Core\Modules;

use App\Models\User;

interface ModuleInterface
{
    /**
     * Unique module identifier (e.g. 'blog', 'gallery', 'forms').
     */
    public function getIdentifier(): string;

    /**
     * Human-readable module name.
     */
    public function getName(): string;

    /**
     * Semantic version of the module.
     */
    public function getVersion(): string;

    /**
     * If true, this module registers its own top-level frontoffice routes
     * (e.g., Blog registers /blog/{slug}, /blog/categoria/{slug}).
     */
    public function isPageable(): bool;

    /**
     * Registers frontoffice routes if isPageable() is true.
     */
    public function registerPageableRoutes(): void;

    /**
     * Backoffice navigation items for admin sidebar.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAdminNavigation(): array;

    /**
     * Whether this module provides a dedicated administrative portal decoupled from the CMS.
     */
    /** @return array{views: array<string, string>, data: array<string, mixed>}|null */
    public function getAdminDashboard(User $user): ?array;

    public function hasDedicatedPanel(): bool;

    /**
     * URL to access the dedicated administrative portal if available.
     */
    public function getDedicatedPanelUrl(): ?string;

    /**
     * Returns the settings schema definition for the module.
     *
     * @return array<string, array{type: string, label: string, description?: string, placeholder?: string, required?: bool, secret?: bool, default?: mixed, options?: array<string, string>}>
     */
    public function getSettingsSchema(): array;
}
