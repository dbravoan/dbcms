<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Language;
use App\Models\Market;
use Illuminate\Database\Seeder;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Throwable;

/**
 * The minimum a working installation needs: the language matrix, the market
 * matrix and a Passport personal access client.
 *
 * Idempotent (`firstOrCreate`) and free of demo content: the narrative demo
 * lives in {@see PlatformShowcaseSeeder}. Module installation is an explicit
 * operation owned by the installer, never by this foundation seeder.
 */
class CmsFoundationSeeder extends Seeder
{
    /**
     * Languages: code => [name, native, direction, default].
     *
     * `zh-CN` and `ar` are part of the matrix on purpose: the platform is
     * multi-market, and the Arabic language exercises RTL rendering.
     *
     * @var array<string, array{name: string, native: string, direction: string, default: bool}>
     */
    public const LANGUAGES = [
        'es' => ['name' => 'Spanish', 'native' => 'Español', 'direction' => 'ltr', 'default' => true],
        'en' => ['name' => 'English', 'native' => 'English', 'direction' => 'ltr', 'default' => false],
        'fr' => ['name' => 'French', 'native' => 'Français', 'direction' => 'ltr', 'default' => false],
        'it' => ['name' => 'Italian', 'native' => 'Italiano', 'direction' => 'ltr', 'default' => false],
        'pt' => ['name' => 'Portuguese', 'native' => 'Português', 'direction' => 'ltr', 'default' => false],
        'de' => ['name' => 'German', 'native' => 'Deutsch', 'direction' => 'ltr', 'default' => false],
        'zh-CN' => ['name' => 'Chinese (Simplified)', 'native' => '简体中文', 'direction' => 'ltr', 'default' => false],
        'ar' => ['name' => 'Arabic', 'native' => 'العربية', 'direction' => 'rtl', 'default' => false],
    ];

    /**
     * Markets: code => [name, currency, default locale, default market, locales].
     *
     * @var array<string, array{name: string, currency: string, default_locale: string, default: bool, locales: list<string>}>
     */
    public const MARKETS = [
        'eu' => ['name' => 'European Union', 'currency' => 'EUR', 'default_locale' => 'es', 'default' => true, 'locales' => ['es', 'en', 'fr', 'it', 'pt', 'de']],
        'es' => ['name' => 'Spain', 'currency' => 'EUR', 'default_locale' => 'es', 'default' => false, 'locales' => ['es', 'en']],
        'uk' => ['name' => 'United Kingdom', 'currency' => 'GBP', 'default_locale' => 'en', 'default' => false, 'locales' => ['en']],
        'us' => ['name' => 'United States', 'currency' => 'USD', 'default_locale' => 'en', 'default' => false, 'locales' => ['en', 'es']],
        'mx' => ['name' => 'Mexico', 'currency' => 'MXN', 'default_locale' => 'es', 'default' => false, 'locales' => ['es', 'en']],
        'co' => ['name' => 'Colombia', 'currency' => 'COP', 'default_locale' => 'es', 'default' => false, 'locales' => ['es']],
        'cn' => ['name' => 'China', 'currency' => 'CNY', 'default_locale' => 'zh-CN', 'default' => false, 'locales' => ['zh-CN', 'en']],
        'gcc' => ['name' => 'GCC / Arabian Gulf', 'currency' => 'AED', 'default_locale' => 'ar', 'default' => false, 'locales' => ['ar', 'en']],
    ];

    public function run(): void
    {
        $languages = [];
        foreach (self::LANGUAGES as $code => $definition) {
            $languages[$code] = Language::firstOrCreate(
                ['code' => $code],
                [
                    'name' => $definition['name'],
                    'native_name' => $definition['native'],
                    'direction' => $definition['direction'],
                    'is_default' => $definition['default'],
                    'is_active' => true,
                ]
            );
        }

        foreach (self::MARKETS as $code => $definition) {
            $market = Market::firstOrCreate(
                ['code' => $code],
                [
                    'name' => $definition['name'],
                    'currency' => $definition['currency'],
                    'default_locale' => $definition['default_locale'],
                    'is_default' => $definition['default'],
                    'is_active' => true,
                ]
            );

            $sync = [];
            foreach ($definition['locales'] as $locale) {
                if (! isset($languages[$locale])) {
                    continue;
                }

                $sync[$languages[$locale]->id] = ['is_default' => $locale === $definition['default_locale']];
            }

            $market->languages()->syncWithoutDetaching($sync);
        }

        $this->ensurePersonalAccessClient();
    }

    /**
     * Ensure a Passport personal access client exists so `cms:issue-mcp-token`
     * (and the backoffice "generate token" action) work on a fresh install.
     */
    protected function ensurePersonalAccessClient(): void
    {
        try {
            if (Client::query()->where('grant_types', 'like', '%personal_access%')->exists()) {
                return;
            }

            app(ClientRepository::class)->createPersonalAccessGrantClient((string) config('cms.name', 'CMS').' Personal Access Client');
        } catch (Throwable) {
            // Passport tables not migrated yet — nothing to do.
        }
    }
}
