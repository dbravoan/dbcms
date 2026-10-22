<?php

declare(strict_types=1);

namespace Modules\CreativeStudio\Services;

use App\Models\CmsModule;
use Illuminate\Validation\ValidationException;

final class CreativeSettings
{
    public function budget(): int
    {
        return $this->integer('installation_budget', (int) config('creative_studio.installation_budget', 0));
    }

    public function price(string $modality): int
    {
        return $this->integer('estimate_'.$modality, (int) config('creative_studio.prices.'.$modality, 0));
    }

    public function concurrency(): int
    {
        return max(1, $this->integer('concurrency', (int) config('creative_studio.concurrency', 2)));
    }

    public function currency(): string
    {
        $settings = $this->stored();

        return (string) ($settings['currency'] ?? config('creative_studio.currency', 'USD'));
    }

    private function integer(string $key, int $fallback): int
    {
        $settings = $this->stored();
        $value = $settings[$key] ?? $fallback;
        if (! is_numeric($value) || (float) $value < 0 || (float) $value !== floor((float) $value)) {
            throw ValidationException::withMessages(['budget' => 'Los límites y estimaciones deben ser enteros positivos en céntimos.']);
        }

        return (int) $value;
    }

    /** @return array<string,mixed> */
    private function stored(): array
    {
        return (array) (CmsModule::where('identifier', 'creative-studio')->value('settings') ?? []);
    }
}
