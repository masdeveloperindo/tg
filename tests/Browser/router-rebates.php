<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Test-only HTTP router; never placed in public/ and never used by the application server.
$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$db = DB::selectOne('SELECT DATABASE() AS name, VERSION() AS version');
if (! $app->environment('e2e') || $db->name !== 'tg_test' || ! str_starts_with($db->version, '8.4.')) {
    http_response_code(503);
    echo 'Isolated rebate fixtures required.';

    return;
}
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = realpath($root.'/public/'.$path);
if ($file && str_starts_with($file, $root.'/public/') && is_file($file) && strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'php') {
    return false;
}
$app->handleRequest(Request::capture());
