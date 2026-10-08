<?php

declare(strict_types=1);

namespace Modules\Shop\Models;

use App\Contracts\Services\ModuleSettingsServiceInterface;

/**
 * Shop settings facade backed by the generic module settings store.
 *
 * Historically these lived in a dedicated `shop_settings` table; they are now
 * persisted exclusively through {@see ModuleSettingsServiceInterface} (module
 * "shop"). This class preserves the static API and normalizes legacy dotted
 * keys (e.g. `shop.mode` → `shop_mode`).
 */
final class ShopSetting
{
    /**
     * Map legacy dotted keys to standard module schema keys.
     */
    private const KEY_MAP = [
        'shop.mode' => 'shop_mode',
        'shop.catalog_show_prices' => 'catalog_show_prices',
        'shop.currency' => 'currency',
        'shop.tax_rate' => 'tax_rate',
        'shop.enable_stock' => 'enable_stock',
        'shop.enable_inquiry' => 'enable_inquiry',
        'shop.notification_email' => 'notification_email',
        'shop.slug_default' => 'slug_default',
        // A language created later (e.g. `shop.slug_fr`) must not be written as
        // a dotted key the settings schema cannot resolve.
        'shop.slug_{locale}' => 'slug_{locale}',
    ];

    public static function normalizeKey(string $key): string
    {
        if (isset(self::KEY_MAP[$key])) {
            return self::KEY_MAP[$key];
        }

        if (str_starts_with($key, 'shop.slug_')) {
            return substr($key, 5);
        }

        return str_starts_with($key, 'shop.') ? substr($key, 5) : $key;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (! app()->bound(ModuleSettingsServiceInterface::class)) {
            return $default;
        }

        /** @var ModuleSettingsServiceInterface $settings */
        $settings = app(ModuleSettingsServiceInterface::class);

        $value = $settings->get('shop', self::normalizeKey($key), null);
        if ($value !== null && $value !== '') {
            return $value;
        }

        // Fallback to the raw key in case an older store wrote the dotted form.
        $legacy = $settings->get('shop', $key, null);

        return ($legacy !== null && $legacy !== '') ? $legacy : $default;
    }

    public static function set(string $key, mixed $value): void
    {
        if (! app()->bound(ModuleSettingsServiceInterface::class)) {
            return;
        }

        app(ModuleSettingsServiceInterface::class)->set('shop', self::normalizeKey($key), $value);
    }
}
