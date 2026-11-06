<?php

use App\Actions\Licenses\ChangeAccount;
use App\Actions\Refunds\ManageRefund;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$db = DB::selectOne('SELECT DATABASE() AS name, VERSION() AS version');
if (! $app->environment('testing') || $db->name !== 'tg_test' || ! str_starts_with($db->version, '8.4.')) {
    exit(2);
}
config(['session.driver' => 'array', 'app.url' => 'https://example.invalid', 'tradergaruda.license_signing_kid' => 'tg-test-only']);
$session = app('session')->driver();
$session->start();
request()->setLaravelSession($session);
Auth::loginUsingId((int) $argv[2]);
session()->put(['auth.totp_user_id' => (int) $argv[2], 'auth.totp_verified_at' => time(), 'auth.admin_last_activity' => time(), 'auth.password_confirmed_at' => time(), 'auth.reauthenticated_at' => time()]);
fwrite(STDOUT, "READY\n");
fflush(STDOUT);
$until = microtime(true) + 10;
while (! is_file($argv[4])) {
    if (microtime(true) > $until) {
        exit(3);
    } usleep(10000);
}
$status = 200;
$id = null;
try {
    if ($argv[1] === 'refund') {
        $result = app(ManageRefund::class)->initiate((int) $argv[3], getenv('TG_TEST_AMOUNT'), 'Synthetic concurrency refund', getenv('TG_TEST_INTENT'));
        $id = $result->id;
    } elseif ($argv[1] === 'account') {
        $result = app(ChangeAccount::class)->request((int) $argv[3], '87654321', 'Synthetic-New-Server', getenv('TG_TEST_INTENT'));
        $id = $result->id;
    } elseif ($argv[1] === 'api') {
        $request = Request::create('/api/v1/licenses/verify', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], getenv('TG_TEST_PAYLOAD'));
        $response = app(Illuminate\Contracts\Http\Kernel::class)->handle($request);
        $status = $response->getStatusCode();
    } else {
        exit(4);
    }
} catch (ValidationException) {
    $status = 422;
}
echo json_encode(['status' => $status, 'id' => $id, 'connection' => (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id], JSON_THROW_ON_ERROR).PHP_EOL;
