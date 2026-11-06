<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$db = DB::selectOne('SELECT DATABASE() AS name, VERSION() AS version');
if (! $app->environment('e2e') || $db->name !== 'tg_test' || ! str_starts_with($db->version, '8.4.')) {
    http_response_code(503);
    echo 'Isolated e2e MySQL8.4 tg_test required.';

    return;
}
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = realpath($root.'/public/'.$path);
if ($file && str_starts_with($file, $root.'/public/') && is_file($file) && strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'php') {
    return false;
}
// Fixture override is never loaded by the application server or put in public/.
if (getenv('QA_ENABLE_SALES_FIXTURES') === '1') {
    config(['tradergaruda.license_sales_enabled' => true, 'tradergaruda.indicator_sales_enabled' => false]);
}
$app->handleRequest(Request::capture());
