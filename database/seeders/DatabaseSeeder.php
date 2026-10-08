<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CmsFoundationSeeder::class);
        if (app()->environment('local', 'testing') || filter_var(config('cms.seed_demo', false), FILTER_VALIDATE_BOOLEAN)) {
            $this->call(PlatformShowcaseSeeder::class);
        }
    }
}
