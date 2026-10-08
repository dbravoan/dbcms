<?php

declare(strict_types=1);

use App\Models\Market;
use Illuminate\Support\Facades\DB;
use Modules\Shop\Models\Order;
use Modules\Shop\Models\OrderItem;
use Modules\Shop\Models\Product;
use Symfony\Component\Process\Process;
use Tests\Concurrency\ConcurrencyTestCase;

uses(ConcurrencyTestCase::class);

it('serializes two real checkout processes competing for the last unit', function (): void {
    $suffix = bin2hex(random_bytes(5));
    $sku = 'checkout-race-'.$suffix;
    $directory = sys_get_temp_dir().'/dbcms-checkout-'.$suffix;
    mkdir($directory, 0700);
    $processes = [];
    $product = null;
    $market = null;
    $blocker = null;
    try {
        // Each insert commits before workers start, so both independent connections see it.
        $market = Market::create(['code' => $suffix, 'name' => 'Concurrency fixture', 'currency' => 'EUR', 'default_locale' => 'es', 'is_active' => true]);
        $product = Product::create(['sku' => $sku, 'price' => 10, 'stock_quantity' => 1, 'track_stock' => true, 'is_active' => true]);
        $product->translations()->create(['locale' => 'es', 'name' => 'Concurrency fixture', 'slug' => $sku]);
        $product->markets()->attach($market, ['is_active' => true]);
        $connection = config('database.connections.mysql');
        config(['database.connections.checkout_barrier' => $connection]);
        $blocker = DB::connection('checkout_barrier');
        $blocker->beginTransaction();
        $blocker->table('products')->where('id', $product->id)->lockForUpdate()->first();
        $environment = [
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'testing',
            'APP_CONFIG_CACHE' => $directory.'/no-cached-config.php', 'DB_URL' => false,
            'DB_HOST' => (string) $connection['host'], 'DB_PORT' => (string) $connection['port'],
            'DB_USERNAME' => (string) $connection['username'], 'DB_PASSWORD' => (string) $connection['password'],
            'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array',
        ];
        foreach ([1, 2] as $worker) {
            $process = new Process([PHP_BINARY, base_path('tests/Support/ConcurrentShopCheckout.php'), $sku, $market->code, $directory, (string) $worker], base_path(), $environment, timeout: 30);
            $process->start();
            $processes[] = $process;
        }
        $deadline = microtime(true) + 15;
        while (! is_file($directory.'/ready-1.json') || ! is_file($directory.'/ready-2.json')) {
            foreach ($processes as $process) {
                if (! $process->isRunning()) {
                    throw new RuntimeException('Checkout worker stopped before the barrier: '.$process->getErrorOutput());
                }
            }
            if (microtime(true) > $deadline) {
                throw new RuntimeException('Both checkout workers must become ready.');
            }
            usleep(10000);
        }
        $first = json_decode(file_get_contents($directory.'/ready-1.json'), true, flags: JSON_THROW_ON_ERROR);
        $second = json_decode(file_get_contents($directory.'/ready-2.json'), true, flags: JSON_THROW_ON_ERROR);
        expect($first['pid'])->not->toBe($second['pid'])
            ->and($first['connection_id'])->not->toBe($second['connection_id']);
        file_put_contents($directory.'/go', 'go');
        $deadline = microtime(true) + 10;
        $waiting = [];
        while (microtime(true) < $deadline) {
            $waiting = [];
            foreach (DB::select('SHOW FULL PROCESSLIST') as $thread) {
                $sql = strtolower((string) $thread->Info);
                if (in_array((int) $thread->Id, [$first['connection_id'], $second['connection_id']], true)
                    && str_contains($sql, 'products') && str_contains($sql, 'for update')) {
                    $waiting[] = (int) $thread->Id;
                }
            }
            if (count($waiting) === 2) {
                break;
            }
            usleep(10000);
        }
        // Both actual service SELECTs are concurrently waiting on the same InnoDB row.
        expect($waiting)->toHaveCount(2);
        $blocker->commit();
        $results = [];
        foreach ($processes as $process) {
            expect($process->wait())->toBe(0);
            $results[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR)['status'];
        }
        sort($results);
        expect($results)->toBe(['created', 'rejected'])
            ->and($product->fresh()->stock_quantity)->toBe(0)
            ->and(Order::where('customer_email', $sku.'@example.test')->count())->toBe(1)
            ->and((int) OrderItem::where('product_id', $product->id)->sum('quantity'))->toBe(1);
    } finally {
        // Stop workers before releasing/cleaning their fixtures, even on assertion failure.
        foreach ($processes as $process) {
            if ($process->isRunning()) {
                $process->stop(1);
            }
        }
        if ($blocker !== null && $blocker->transactionLevel() > 0) {
            $blocker->rollBack();
        }
        Order::where('customer_email', $sku.'@example.test')->delete();
        $product?->delete();
        $market?->delete();
        DB::purge('checkout_barrier');
        foreach (glob($directory.'/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($directory);
    }
});
