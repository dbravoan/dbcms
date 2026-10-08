<?php

declare(strict_types=1);

namespace Tests\Concurrency;

use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use LogicException;

/** Dedicated committed fixtures: never uses RefreshDatabase or test transactions. */
abstract class ConcurrencyTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (! app()->environment('testing') || DB::connection()->getDriverName() !== 'mysql'
            || DB::connection()->getDatabaseName() !== 'testing' || DB::transactionLevel() !== 0) {
            throw new LogicException('Concurrency tests require the isolated MySQL testing database and no enclosing transaction.');
        }
        if (DB::selectOne('SELECT DATABASE() AS name')->name !== 'testing') {
            throw new LogicException('The actual MySQL connection must use testing.');
        }
    }
}
