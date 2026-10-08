<?php

declare(strict_types=1);

namespace App\Core\Forms;

use App\Core\Forms\Contracts\FieldType;
use App\Models\CmsModule;
use Modules\Captcha\Fields\CaptchaFieldType;

/**
 * Runtime catalog of the available form field types.
 *
 * The core registers the primitive HTML inputs; modules register their own
 * types in `boot()` (see {@see FieldType}). Consumers
 * (renderer, validator, form builder) resolve everything through this registry
 * instead of branching on a hardcoded type list.
 */
final class FieldTypeRegistry
{
    /** @var array<string, FieldType> */
    private array $types = [];

    /** Whether the module-provided types were already hydrated. */
    private bool $hydrated = false;

    public function register(FieldType $type): void
    {
        $this->types[$type->key()] = $type;
    }

    /**
     * Register a module-provided type only while its module is active.
     *
     * Module service providers boot once, at application start, so a module
     * enabled later from the backoffice (or inside a test) would never register
     * its field type. Consumers that resolve the catalog late call this instead,
     * which keeps the builder reactive without re-booting the provider.
     */
    public function registerIfModuleActive(string $key, string $moduleIdentifier, callable $factory): void
    {
        if (isset($this->types[$key])) {
            return;
        }

        try {
            $module = CmsModule::query()->where('identifier', $moduleIdentifier)->first();
        } catch (\Throwable) {
            return;
        }

        if ($module === null || ! $module->is_active) {
            return;
        }

        $this->register($factory());
    }

    public function forget(string $key): void
    {
        unset($this->types[$key]);
    }

    public function has(string $key): bool
    {
        $this->hydrateModuleTypes();

        return isset($this->types[$key]);
    }

    public function get(string $key): ?FieldType
    {
        $this->hydrateModuleTypes();

        return $this->types[$key] ?? null;
    }

    /**
     * @return array<string, FieldType>
     */
    public function all(): array
    {
        return $this->types;
    }

    /**
     * Types usable right now (their module is installed and active).
     *
     * @return array<string, FieldType>
     */
    public function available(): array
    {
        $this->hydrateModuleTypes();

        return array_filter($this->types, static fn (FieldType $type): bool => $type->available());
    }

    /**
     * Late-register the module-provided types whose module is active now.
     *
     * Kept in one place so every consumer (`available`, `has`, `resolve`)
     * observes the same catalog, even when the module was enabled after boot.
     */
    private function hydrateModuleTypes(): void
    {
        if ($this->hydrated) {
            return;
        }

        $this->hydrated = true;

        if (class_exists(CaptchaFieldType::class)) {
            $this->registerIfModuleActive('captcha', 'captcha', static fn (): FieldType => new CaptchaFieldType);
        }
    }

    /**
     * @return array<int, string>
     */
    public function availableKeys(): array
    {
        return array_keys($this->available());
    }

    /**
     * Resolve the type to use for a field, or `null` when the field must be
     * ignored because its type is declared by an unavailable module.
     *
     * Unknown, undeclared types fall back to `text` for backwards compatibility
     * with legacy schemas.
     */
    public function resolve(string $key): ?FieldType
    {
        $type = $this->get($key);

        if ($type !== null) {
            return $type->available() ? $type : null;
        }

        if (FieldTypeCatalog::isDeclared($key)) {
            return null;
        }

        return $this->get('text');
    }
}
