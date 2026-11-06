<?php

namespace Tests\Support;

use App\Actions\Licenses\FulfillOrder;
use App\Actions\Orders\Checkout;
use App\Models\License;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

trait LicenseWorkflow
{
    private User $buyer;

    private function prepareWorkflow(): void
    {
        $this->withoutVite();
        Storage::fake('local');
        $session = app('session')->driver();
        $session->start();
        request()->setLaravelSession($session);
        app()->rebinding('request', static function ($app, Request $request): void {
            if (! $request->hasSession()) {
                $request->setLaravelSession($app['session']->driver());
            }
        });
        config(['tradergaruda.license_sales_enabled' => true]);
        CheckoutRecords::configure();
        $this->buyer = User::findOrFail((new DomainRecords)->user());
        $this->actingAs($this->buyer);
    }

    private function admin(): User
    {
        $admin = User::findOrFail((new DomainRecords)->user(['role' => 'admin', 'totp_secret_enc' => Crypt::encryptString('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ'), 'totp_enabled_at' => now()]));
        $this->actingAs($admin);
        session()->put(['auth.totp_user_id' => $admin->id, 'auth.totp_verified_at' => now()->timestamp, 'auth.admin_last_activity' => now()->timestamp, 'auth.password_confirmed_at' => now()->timestamp, 'auth.reauthenticated_at' => now()->timestamp]);

        return $admin;
    }

    private function issue(): License
    {
        $this->actingAs($this->buyer);
        $product = CheckoutRecords::product();
        $product->versions()->first()->forceFill(['version' => '1.1.0', 'min_supported_version' => '1.0.0'])->save();
        $quote = app(Checkout::class)->quote($product);
        $order = app(Checkout::class)->create($quote->id, CheckoutRecords::input());
        $this->admin();

        return app(FulfillOrder::class)->confirm($order->id, ['funds_checked' => true, 'reference' => 'SYNTHETIC', 'note' => 'Synthetic funds checked']);
    }
}
