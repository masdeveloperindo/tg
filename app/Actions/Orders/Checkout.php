<?php

namespace App\Actions\Orders;

use App\Models\CheckoutQuote;
use App\Models\License;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Security\Access;
use App\Support\Audit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class Checkout
{
    private function client(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User && Access::isOwner($user, $user->id), 403);

        return $user;
    }

    public function available(Product $product): bool
    {
        return $product->status->value === 'published' && $product->sale_enabled && config('tradergaruda.license_sales_enabled')
            && ($product->type->value === 'ea' || config('tradergaruda.indicator_sales_enabled'))
            && $product->versions()->where('is_active', true)->whereNotNull('released_at')->where('released_at', '<=', now())->get()->contains(fn ($version) => $version->bytes > 0 && strlen($version->sha256) === 64 && Storage::disk('local')->exists($version->path) && Storage::disk('local')->size($version->path) === $version->bytes && hash_equals($version->sha256, hash_file('sha256', Storage::disk('local')->path($version->path))));
    }

    public function quote(Product $product, ?int $renewalId = null): CheckoutQuote
    {
        $client = $this->client();
        $budget = 'checkout:quote:'.$client->id;
        if (RateLimiter::tooManyAttempts($budget, 20)) {
            throw ValidationException::withMessages(['checkout' => 'Terlalu banyak permintaan harga. Tunggu sebelum mencoba kembali.']);
        }
        RateLimiter::hit($budget, 900);

        return DB::transaction(function () use ($product, $renewalId): CheckoutQuote {
            $actor = $this->client();
            $user = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            abort_unless(Access::isOwner($user, $user->id), 403);
            $product = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            abort_unless($this->available($product), 404);
            $license = $renewalId ? License::where('user_id', $user->id)->where('product_id', $product->id)->findOrFail($renewalId) : null;
            $settings = app(CheckoutSettings::class);
            $rules = $settings->pricing();
            $methods = $settings->methods();
            if (! $settings->telegram()) {
                throw ValidationException::withMessages(['checkout' => 'Layanan pemesanan belum tersedia.']);
            }
            $price = app(CalendarPricing::class)->calculate($product, $rules, renewal: $license);
            if (bccomp($price['amount_idr'], '0', 2) <= 0) {
                throw ValidationException::withMessages(['checkout' => 'Harga pemesanan belum tersedia.']);
            }
            $now = CarbonImmutable::now();
            $limit = $now->setTimezone('Asia/Jakarta')->startOfMonth()->addMonth()->utc();
            $expires = $now->addMinutes($rules['quote_minutes'])->min($limit);
            $quote = new CheckoutQuote;
            $quote->forceFill([
                'id' => (string) Str::uuid(), 'user_id' => $user->id, 'product_id' => $product->id, 'renewal_license_id' => $license?->id,
                'amount_idr' => $price['amount_idr'], 'coverage_year' => $price['coverage_year'], 'coverage_expires_at' => $price['coverage_expires_at'], 'expires_at' => $expires, 'payment_minutes' => $rules['payment_minutes'],
                'pricing_snapshot' => ['month' => $price['pricing_month'], 'rules' => $rules, 'base_price_idr' => $product->base_price_idr, 'minimum_price_idr' => $product->minimum_price_idr, 'product_name' => $product->name, 'license_fingerprint' => $license ? $this->fingerprint($license) : null],
                'payment_details' => $methods,
            ])->save();

            return $quote;
        }, 3);
    }

    public function create(string $quoteId, array $input): Order
    {
        $actor = $this->client();
        Validator::make(['quote' => $quoteId], ['quote' => 'required|uuid'])->validate();

        return DB::transaction(function () use ($actor, $quoteId, $input): Order {
            $user = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            abort_unless(Access::isOwner($user, $user->id), 403);
            $existing = Order::where('user_id', $user->id)->where('idempotency_key', $quoteId)->first();
            if ($existing) {
                return $existing;
            }
            $quote = CheckoutQuote::where('user_id', $user->id)->whereKey($quoteId)->lockForUpdate()->firstOrFail();
            if (now()->gte($quote->expires_at)) {
                throw ValidationException::withMessages(['checkout' => 'Kuotasi kedaluwarsa. Muat harga terbaru sebelum membuat pesanan.']);
            }
            $product = Product::whereKey($quote->product_id)->lockForUpdate()->firstOrFail();
            abort_unless($this->available($product), 404);
            $methods = $quote->payment_details;
            $ids = array_column($methods, 'id');
            if (isset($input['mt5_server']) && is_string($input['mt5_server'])) {
                $input['mt5_server'] = trim($input['mt5_server']);
            }
            $data = Validator::make($input, ['mt5_account' => ['required', 'string', 'regex:/^[1-9][0-9]{0,23}$/D'], 'mt5_server' => ['required', 'string', 'max:100', 'regex:/^[^\x00-\x1f\x7f]+$/u'], 'account_type' => ['required', Rule::in(['real', 'demo'])], 'payment_method_id' => ['required', Rule::in($ids)]])->validate();
            if ($quote->renewal_license_id) {
                $license = License::where('user_id', $user->id)->where('product_id', $product->id)->whereKey($quote->renewal_license_id)->lockForUpdate()->firstOrFail();
                if ($quote->pricing_snapshot['license_fingerprint'] !== $this->fingerprint($license) || $license->status->value !== 'active') {
                    throw ValidationException::withMessages(['renewal' => 'Lisensi berubah. Muat ulang rincian perpanjangan.']);
                }
                if ($data['mt5_account'] !== $license->mt5_account || $data['mt5_server'] !== $license->mt5_server || $data['account_type'] !== $license->account_type->value) {
                    throw ValidationException::withMessages(['mt5_account' => 'Perpanjangan wajib memakai akun lisensi yang sama.']);
                }
            } elseif (License::where('user_id', $user->id)->where('product_id', $product->id)->where('mt5_account', $data['mt5_account'])->exists()) {
                throw ValidationException::withMessages(['mt5_account' => 'Akun ini sudah memiliki lisensi. Gunakan perpanjangan atau hubungi admin.']);
            }
            if (Order::where('user_id', $user->id)->where('product_id', $product->id)->where('mt5_account', $data['mt5_account'])->where('coverage_year', $quote->coverage_year)->where('status', 'waiting')->where('expires_at', '>', now())->exists()) {
                throw ValidationException::withMessages(['checkout' => 'Ada pesanan menunggu untuk akun ini. Periksa daftar pesanan terlebih dahulu.']);
            }
            $order = new Order;
            $order->forceFill([...$data, 'code' => 'TG-'.strtoupper(Str::random(20)), 'user_id' => $user->id, 'product_id' => $product->id, 'renewal_license_id' => $quote->renewal_license_id, 'order_type' => $quote->renewal_license_id ? 'renewal' : 'new', 'coverage_year' => $quote->coverage_year, 'price_locked_idr' => $quote->amount_idr, 'status' => 'waiting', 'idempotency_key' => $quote->id, 'checkout_quote_id' => $quote->id, 'expires_at' => CarbonImmutable::now()->addMinutes($quote->payment_minutes)->min($quote->coverage_expires_at)])->save();
            Audit::record($user, 'order.created', $order, ['coverage_year' => $quote->coverage_year]);

            return $order;
        }, 3);
    }

    private function fingerprint(License $license): string
    {
        return hash('sha256', json_encode([$license->id, $license->mt5_account, $license->mt5_server, $license->account_type->value, $license->coverage_year, $license->expires_at->format('c')], JSON_THROW_ON_ERROR));
    }
}
