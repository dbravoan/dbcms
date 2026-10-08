<?php

declare(strict_types=1);
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/Support/McpIdentity.php';

/*
|--------------------------------------------------------------------------
| Test Case Bindings
|--------------------------------------------------------------------------
*/

uses(
    TestCase::class,
    RefreshDatabase::class,
)->in('Feature', 'Unit');

// Architecture tests boot the app (to inspect Artisan/console metadata) but do
// not touch the database.
uses(TestCase::class)->in('Architecture');

/*
|--------------------------------------------------------------------------
| Custom Pest Expectations & Helpers
|--------------------------------------------------------------------------
*/

expect()->extend('toBeValidJsonLd', function () {
    return $this->toBeArray()
        ->toHaveKey('@context')
        ->and($this->value['@context'])->toBe('https://schema.org');
});
