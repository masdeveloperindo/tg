<?php

// Runs solely inside a disposable candidate tree against tg_test. Never print configuration.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$directory = $argv[1] ?? '';
if (! is_dir($directory) || ! str_contains(basename($directory), 'release-check-')) {
    exit(2);
}
require $directory.'/vendor/autoload.php';
$app = require $directory.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$db = DB::selectOne('SELECT DATABASE() AS name, VERSION() AS version');
if (! $app->environment('release-check') || $db->name !== 'tg_test' || ! str_starts_with($db->version, '8.4.')) {
    fwrite(STDERR, "Isolated release-check/MySQL8.4 tg_test required.\n");
    exit(2);
}
if (($argv[2] ?? '') === 'guard') {
    echo "Dedicated test schema verified.\n";
    exit(0);
}
if (config('app.debug') !== false || config('tradergaruda.license_sales_enabled') || config('tradergaruda.indicator_sales_enabled') || config('filesystems.disks.local.serve')) {
    throw new RuntimeException('Unsafe candidate runtime configuration.');
}
foreach (['users', 'orders', 'licenses', 'withdrawals', 'rebate_entries', 'testimonials', 'articles'] as $table) {
    if (DB::table($table)->count() !== 0) {
        throw new RuntimeException('Candidate smoke requires an empty synthetic test schema.');
    }
}
if (! Schema::hasColumn('license_flags', 'review_note')) {
    throw new RuntimeException('Candidate schema is incomplete.');
}
$http = $app->make(Illuminate\Contracts\Http\Kernel::class);
$checks = [];
foreach ([['GET', '/', 200], ['GET', '/produk', 200], ['GET', '/edukasi', 200], ['GET', '/sitemap.xml', 200], ['GET', '/robots.txt', 200], ['GET', '/member', 302], ['GET', '/admin', 302], ['GET', '/storage/private/example.ex5', 404], ['POST', '/api/v1/licenses/deactivate', 404], ['GET', '/health', 404]] as [$method,$uri,$status]) {
    $request = Request::create('https://release-check.example.invalid'.$uri, $method);
    $response = $http->handle($request);
    if ($response->getStatusCode() !== $status) {
        throw new RuntimeException('Candidate route smoke failed: '.$method.' '.$uri);
    }
    $checks[] = ['method' => $method, 'path' => $uri, 'status' => $status];
    $http->terminate($request, $response);
}
$request = Request::create('https://release-check.example.invalid/health', 'GET', server: ['HTTP_AUTHORIZATION' => 'Bearer '.config('tradergaruda.health_token')]);
$response = $http->handle($request);
if ($response->getStatusCode() !== 200 || json_decode($response->getContent(), true) !== ['status' => 'ok']) {
    throw new RuntimeException('Candidate authenticated health smoke failed.');
}$http->terminate($request, $response);
echo json_encode(['environment' => 'isolated release-check tg_test, not deployment', 'debug_off' => true, 'sales_closed' => true, 'business_rows' => 0, 'review_note_present' => true, 'routes' => $checks, 'authenticated_health' => 200, 'framework_tables' => count(Schema::getTableListing(DB::connection()->getDatabaseName(), false))], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
