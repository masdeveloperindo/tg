<?php

namespace App\Actions\Licenses;

use App\Actions\Orders\CalendarPricing;
use App\Models\License;
use App\Models\Order;
use App\Models\User;
use App\Security\Access;
use App\Support\Audit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class FulfillOrder
{
    public function confirm(int $id, array $input): License
    {
        $data = Validator::make($input, ['funds_checked' => 'required|accepted', 'reference' => 'required|string|max:120', 'note' => 'required|string|min:5|max:500'])->validate();

        return DB::transaction(function () use ($id, $data): License {
            $order = Order::whereKey($id)->lockForUpdate()->firstOrFail();
            Gate::authorize('confirmPayment', $order);
            $admin = $this->admin();
            if ($order->status->value === 'paid') {
                return $this->receipt($order);
            }
            $this->waiting($order);
            if (! config('tradergaruda.license_sales_enabled') || ($order->product->type->value === 'indicator' && ! config('tradergaruda.indicator_sales_enabled'))) {
                throw ValidationException::withMessages(['payment' => 'Penerbitan lisensi belum dibuka. Tangani pembayaran secara manual sebelum melanjutkan.']);
            }
            $buyer = $order->user;
            if ($buyer->role->value !== 'client' || $buyer->status->value !== 'active') {
                throw ValidationException::withMessages(['payment' => 'Akun client perlu diperiksa admin.']);
            }
            $version = $order->product->versions()->where('is_active', true)->whereNotNull('released_at')->where('released_at', '<=', now())->first();
            if (! $version) {
                throw ValidationException::withMessages(['payment' => 'Versi produk belum siap.']);
            }$handle = app(LicenseFiles::class)->open($version);
            fclose($handle);
            $end = CalendarPricing::end($order->coverage_year);
            if (! $end->isFuture() || bccomp($order->price_locked_idr, '0', 2) <= 0) {
                throw ValidationException::withMessages(['payment' => 'Masa akses atau nominal tidak valid.']);
            }
            if ($order->order_type->value === 'renewal') {
                $license = License::whereKey($order->renewal_license_id)->lockForUpdate()->firstOrFail();
                if ($license->user_id !== $order->user_id || $license->product_id !== $order->product_id || $license->mt5_account !== $order->mt5_account || $license->mt5_server !== $order->mt5_server || $license->account_type !== $order->account_type || $license->status->value !== 'active' || $end->lte($license->expires_at)) {
                    throw ValidationException::withMessages(['payment' => 'Data perpanjangan berubah atau sudah diproses. Periksa lisensi sebelum melanjutkan.']);
                }
                $license->forceFill(['coverage_year' => $order->coverage_year, 'expires_at' => $end])->save();
                $event = 'license.renewed';
            } else {
                if (License::where('user_id', $order->user_id)->where('product_id', $order->product_id)->where('mt5_account', $order->mt5_account)->lockForUpdate()->exists()) {
                    throw ValidationException::withMessages(['payment' => 'Akun sudah memiliki lisensi. Gunakan prosedur perpanjangan.']);
                }
                $license = $this->issue($order, $end);
                $event = 'license.issued';
            }
            $order->forceFill(['status' => 'paid', 'confirmed_at' => now(), 'confirmed_by' => $admin->id, 'payment_checked_at' => now(), 'payment_reference' => $data['reference'], 'payment_note' => $data['note']])->save();
            Audit::record($admin, 'order.payment_confirmed', $order, ['funds_checked' => true, 'reference_provided' => true, 'note_provided' => true, 'resource_id' => $license->id]);
            Audit::record($admin, $event, $license, ['coverage_year' => $license->coverage_year, 'resource_id' => $order->id]);
            DB::afterCommit(fn () => Cache::forget('license:state:'.$license->id));

            return $license;
        }, 3);
    }

    public function reject(int $id, string $reason): void
    {
        Validator::make(['reason' => $reason], ['reason' => 'required|string|min:5|max:500'])->validate();
        DB::transaction(function () use ($id, $reason): void {
            $order = Order::whereKey($id)->lockForUpdate()->firstOrFail();
            Gate::authorize('reject', $order);
            $admin = $this->admin();
            if ($order->status->value === 'rejected') {
                return;
            }$this->waiting($order);
            $order->forceFill(['status' => 'rejected', 'payment_note' => $reason])->save();
            Audit::record($admin, 'order.rejected', $order, ['reason_provided' => true]);
        }, 3);
    }

    private function admin(): User
    {
        $user = Auth::user()?->fresh();
        abort_unless($user instanceof User && Access::hasRecentReauthentication($user), 403);

        return $user;
    }

    private function waiting(Order $order): void
    {
        if ($order->status->value !== 'waiting' || now()->gte($order->expires_at)) {
            throw ValidationException::withMessages(['payment' => 'Pesanan bukan menunggu aktif. Pembayaran lewat batas waktu harus ditangani manual.']);
        }
    }

    private function receipt(Order $order): License
    {
        return $order->renewal_license_id ? License::whereKey($order->renewal_license_id)->where('user_id', $order->user_id)->where('product_id', $order->product_id)->firstOrFail() : License::where('issued_order_id', $order->id)->where('user_id', $order->user_id)->firstOrFail();
    }

    private function issue(Order $order, $end): License
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $key = implode('-', str_split(strtoupper(bin2hex(random_bytes(16))), 4));
            $hash = hash('sha256', $key);
            if (License::where('key_hash', $hash)->exists()) {
                continue;
            }
            try {
                $license = new License;
                $license->forceFill(['user_id' => $order->user_id, 'product_id' => $order->product_id, 'key_hash' => $hash, 'key_ciphertext' => $key, 'mt5_account' => $order->mt5_account, 'mt5_server' => $order->mt5_server, 'account_type' => $order->account_type, 'status' => 'active', 'coverage_year' => $order->coverage_year, 'expires_at' => $end, 'issued_order_id' => $order->id])->save();

                return $license;
            } catch (UniqueConstraintViolationException $error) {
                if (! str_contains($error->errorInfo[2] ?? '', 'key_hash_unique')) {
                    throw $error;
                }
            }
        }
        throw ValidationException::withMessages(['payment' => 'Penerbitan key belum berhasil. Periksa status sebelum mencoba kembali.']);
    }
}
