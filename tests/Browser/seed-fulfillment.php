<?php

use App\Actions\Orders\Checkout;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Support\CheckoutRecords;

require __DIR__.'/seed-checkout.php';
$session = app('session')->driver();
$session->start();
request()->setLaravelSession($session);
Auth::loginUsingId(DB::table('users')->where('email', 'layout-client@example.invalid')->value('id'));
config(['tradergaruda.license_sales_enabled' => true]);
$product = Product::where('slug', 'synthetic-checkout-ea')->firstOrFail();
$quote = app(Checkout::class)->quote($product);
app(Checkout::class)->create($quote->id, CheckoutRecords::input());
echo "Synthetic pending order prepared in e2e tg_test only.\n";
