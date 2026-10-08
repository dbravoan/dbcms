<?php

declare(strict_types=1);

use App\Contracts\Services\ModuleNotificationServiceInterface;
use App\Contracts\Services\ModuleSettingsServiceInterface;
use App\Models\Market;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Shop\Models\Product;
use Modules\Shop\Services\ShopDomainService;

require __DIR__.'/../../vendor/autoload.php';

try {
    [$script, $sku, $marketCode, $barrierDirectory, $worker] = $argv;
    $app = require __DIR__.'/../../bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    if (! $app->environment('testing') || DB::connection()->getDriverName() !== 'mysql'
        || DB::connection()->getDatabaseName() !== 'testing' || DB::selectOne('SELECT DATABASE() AS name')->name !== 'testing') {
        throw new LogicException('Worker refuses any database other than MySQL testing.');
    }
    // Exercise the actual checkout service with stock enabled and no outbound notifications.
    $app->offsetUnset(ModuleSettingsServiceInterface::class);
    $app->offsetUnset(ModuleNotificationServiceInterface::class);
    $product = Product::with(['translations', 'markets'])->where('sku', $sku)->firstOrFail();
    $market = Market::where('code', $marketCode)->firstOrFail();
    $connectionId = (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
    file_put_contents($barrierDirectory.'/ready-'.$worker.'.json', json_encode(['connection_id' => $connectionId, 'pid' => getmypid()], JSON_THROW_ON_ERROR));
    $deadline = microtime(true) + 20;
    while (! is_file($barrierDirectory.'/go')) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Checkout start barrier timed out.');
        }
        usleep(10000);
    }
    $order = $app->make(ShopDomainService::class)->createOrder(
        ['customer_name' => 'Concurrent fixture', 'customer_email' => $sku.'@example.test'],
        [['product' => $product, 'quantity' => 1, 'unit_price' => 10.0, 'subtotal' => 10.0]],
        $market,
        'bank_transfer',
    );
    echo json_encode(['status' => 'created', 'order_id' => $order->id, 'connection_id' => $connectionId], JSON_THROW_ON_ERROR);
} catch (ValidationException) {
    echo json_encode(['status' => 'rejected'], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    // Diagnostics do not print SQL, database credentials or application secrets.
    fwrite(STDERR, $exception::class);
    exit(2);
}
