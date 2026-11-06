<?php

use App\Actions\Rebates\Payouts;
use App\Actions\Rebates\RebateLedger;
use Illuminate\Contracts\Console\Kernel;
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
config(['session.driver' => 'array']);
$session = app('session')->driver();
$session->start();
request()->setLaravelSession($session);
Auth::loginUsingId((int) $argv[2]);
session()->put(['auth.totp_user_id' => (int) $argv[2], 'auth.totp_verified_at' => time(), 'auth.admin_last_activity' => time(), 'auth.password_confirmed_at' => time(), 'auth.reauthenticated_at' => time()]);
// Retry the entire outer test transaction, matching Laravel's top-level production retry.
// A nested action cannot retry a transaction whose outer snapshot MySQL aborted.
$attempts = 0;
$result = DB::transaction(function () use (&$attempts, $argv): array {
    $attempts++;
    // Deliberately establish an old repeatable-read snapshot BEFORE the wallet lock.
    DB::table('users')->where('id', (int) $argv[2])->value('id');
    if ($attempts === 1) {
        fwrite(STDOUT, "READY\n");
        fflush(STDOUT);
    }
    $until = microtime(true) + 10;
    while (! is_file($argv[4])) {
        if (microtime(true) > $until) {
            throw new RuntimeException('Worker barrier timed out.');
        }
        usleep(10000);
    }
    try {
        $input = json_decode(getenv('TG_REBATE_INPUT') ?: '{}', true, 512, JSON_THROW_ON_ERROR);
        $row = match ($argv[1]) {
            'credit' => app(RebateLedger::class)->credit((int) $argv[3], $input),
            'correct' => app(RebateLedger::class)->correct((int) $argv[3], $input),
            'request' => app(Payouts::class)->request(getenv('TG_REBATE_QUOTE')),
            'cancel' => app(Payouts::class)->cancel((int) $argv[3]),
            'processing','paid','rejected' => app(Payouts::class)->decide((int) $argv[3], $argv[1], $input),
            default => throw new LogicException('Unknown worker mode'),
        };

        return ['status' => 200, 'id' => $row->id];
    } catch (ValidationException) {
        return ['status' => 422, 'id' => null];
    }
}, 3);
echo json_encode([...$result, 'attempts' => $attempts, 'connection' => (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id], JSON_THROW_ON_ERROR).PHP_EOL;
