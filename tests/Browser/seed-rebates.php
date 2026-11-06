<?php

use App\Actions\Rebates\ManageBrokers;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// Existing seed verifies e2e + actual MySQL8.4 tg_test before any reset.
require __DIR__.'/seed.php';
$session = app('session')->driver();
$session->start();
request()->setLaravelSession($session);
$id = (int) DB::table('users')->where('email', 'layout-admin@example.invalid')->value('id');
Auth::loginUsingId($id);
session()->put(['auth.totp_user_id' => $id, 'auth.totp_verified_at' => time(), 'auth.admin_last_activity' => time(), 'auth.password_confirmed_at' => time(), 'auth.reauthenticated_at' => time()]);
config(['tradergaruda.broker_referral_hosts' => ['broker.example.invalid']]);
foreach (['A', 'B'] as $name) {
    app(ManageBrokers::class)->save(null, ['name' => 'Synthetic Phase3 Broker '.$name, 'ib_link' => 'https://broker.example.invalid/ref/'.$name, 'rate_usd_per_lot' => '2.5000', 'valid_from' => now('Asia/Jakarta')->subMonth()->format('Y-m-d'), 'terms' => 'Synthetic broker fixture only. No real trading or transfers.', 'is_active' => true]);
}
$image = imagecreatetruecolor(160, 80);
imagefill($image, 0, 0, imagecolorallocate($image, 20, 66, 48));
imagestring($image, 4, 20, 30, 'SYNTHETIC', imagecolorallocate($image, 220, 255, 100));
imagepng($image, storage_path('framework/testing/phase3-logo.png'));
imagedestroy($image);
echo "Synthetic Phase3 brokers only; no funded wallets, payout rules, EA activation or transfers.\n";
