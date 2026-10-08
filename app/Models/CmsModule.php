<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\Services\ModuleSettingsServiceInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $identifier
 * @property string $name
 * @property string $version
 * @property string|null $description
 * @property string $provider
 * @property bool $is_pageable
 * @property bool $is_core
 * @property bool $is_installed
 * @property bool $is_active
 * @property array<string, mixed>|null $settings
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CmsModule extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'identifier',
        'name',
        'version',
        'description',
        'provider',
        'is_pageable',
        'is_core',
        'is_installed',
        'is_active',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'is_pageable' => 'boolean',
            'is_core' => 'boolean',
            'is_installed' => 'boolean',
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }

    /**
     * Get a setting value for this module.
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        if (app()->bound(ModuleSettingsServiceInterface::class)) {
            return app(ModuleSettingsServiceInterface::class)->get($this->identifier, $key, $default);
        }

        return $this->settings[$key] ?? $default;
    }

    /**
     * Set a setting value for this module.
     */
    public function setSetting(string $key, mixed $value): void
    {
        if (app()->bound(ModuleSettingsServiceInterface::class)) {
            app(ModuleSettingsServiceInterface::class)->set($this->identifier, $key, $value);
        } else {
            $curr = (array) ($this->settings ?? []);
            $curr[$key] = $value;
            $this->update(['settings' => $curr]);
        }
    }

    /**
     * Get the settings schema definition for this module.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getSettingsSchema(): array
    {
        if (app()->bound(ModuleSettingsServiceInterface::class)) {
            return app(ModuleSettingsServiceInterface::class)->getSchema($this->identifier);
        }

        return [];
    }

    /**
     * Check if this module is fully configured with all required settings.
     */
    public function isConfigured(): bool
    {
        if (app()->bound(ModuleSettingsServiceInterface::class)) {
            return app(ModuleSettingsServiceInterface::class)->isConfigured($this->identifier);
        }

        return true;
    }
}
