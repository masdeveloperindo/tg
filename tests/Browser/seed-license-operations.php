<?php

use App\Actions\Licenses\FulfillOrder;
use App\Models\LicenseLog;
use App\Models\Order;
use App\Models\ProductVersion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require __DIR__.'/seed-fulfillment.php';
$adminId = DB::table('users')->where('email', 'layout-admin@example.invalid')->value('id');
Auth::loginUsingId($adminId);
session()->put(['auth.totp_user_id' => $adminId, 'auth.totp_verified_at' => time(), 'auth.admin_last_activity' => time(), 'auth.password_confirmed_at' => time(), 'auth.reauthenticated_at' => time()]);
ProductVersion::where('is_active', true)->update(['version' => '1.1.0', 'min_supported_version' => '1.0.0']);
$license = app(FulfillOrder::class)->confirm(Order::sole()->id, ['funds_checked' => true, 'reference' => 'SYNTHETIC', 'note' => 'Synthetic browser funds checked']);
for ($i = 0; $i < 55; $i++) {
    (new LicenseLog)->forceFill(['license_id' => $license->id, 'event' => 'verify', 'result' => 'valid', 'product_version' => '1.0.0', 'request_id' => (string) Str::uuid()])->save();
}
echo "Synthetic paid license and telemetry prepared only in e2e tg_test.\n";
