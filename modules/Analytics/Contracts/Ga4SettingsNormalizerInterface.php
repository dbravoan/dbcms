<?php

declare(strict_types=1);

namespace Modules\Analytics\Contracts;

interface Ga4SettingsNormalizerInterface
{
    /** @param array<string,mixed> $settings
     * @return array<string,mixed>
     */
    public function normalize(array $settings): array;

    /** @return array{email:string,key:string,key_id:?string} */
    public function credentials(string $json): array;
}
