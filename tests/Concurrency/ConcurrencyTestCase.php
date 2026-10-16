<?php

declare(strict_types=1);

namespace Tests\Concurrency;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\Support\IsolatedTestDatabase;
use Tests\Support\ShopComposition;
use Tests\TestCase;

/** Dedicated committed fixtures: never uses RefreshDatabase or test transactions. */
abstract class ConcurrencyTestCase extends TestCase
{
    use DatabaseMigrations;
    use ShopComposition;

    protected function setUpTraits()
    {
        IsolatedTestDatabase::assertConnection();

        return parent::setUpTraits();
    }
}
