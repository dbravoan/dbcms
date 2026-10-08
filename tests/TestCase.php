<?php

declare(strict_types=1);

namespace Tests;

use App\Models\CmsModule;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Captcha is an opt-in form field: tests that exercise it enable the
        // module explicitly, everything else runs with it disabled.
        $this->deactivateCaptchaModule();
    }

    protected function tearDown(): void
    {
        $lockFile = storage_path('framework/cms-maintenance.json');
        if (file_exists($lockFile)) {
            @unlink($lockFile);
        }
        parent::tearDown();
    }

    protected function deactivateCaptchaModule(): void
    {
        try {
            if (Schema::hasTable('cms_modules')) {
                CmsModule::query()->where('identifier', 'captcha')->update(['is_active' => false]);
            }
        } catch (\Throwable) {
            // Database not available for this test.
        }
    }
}
