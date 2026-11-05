<?php

use App\Actions\Orders\Checkout;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Support\CheckoutRecords;

// Independent PHP worker used solely for a MySQL concurrency test. No route or production override.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$db = DB::selectOne('SELECT DATABASE() AS name, VERSION() AS version');
if (! $app->environment('testing') || $db->name !== 'tg_test' || ! str_starts_with($db->version, '8.4.')) {
    exit(2);
}
config(['tradergaruda.license_sales_enabled' => true, 'session.driver' => 'array']);
$session = app('session')->driver();
$session->start();
request()->setLaravelSession($session);
Auth::loginUsingId((int) $argv[1]);
fwrite(STDOUT, "READY\n");
fflush(STDOUT);
$until = microtime(true) + 10;
while (! is_file($argv[3])) {
    if (microtime(true) > $until) {
        exit(3);
    }usleep(10000);
}
$order = app(Checkout::class)->create($argv[2], CheckoutRecords::input());
echo json_encode(['order_id' => $order->id, 'connection' => (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id], JSON_THROW_ON_ERROR).PHP_EOL;
