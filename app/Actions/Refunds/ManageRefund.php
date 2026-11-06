<?php

namespace App\Actions\Refunds;

use App\Models\Order;
use App\Models\Refund;
use App\Security\Access;
use App\Support\Audit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class ManageRefund
{
    private function admin()
    {
        $admin = Auth::user()?->fresh();
        abort_unless($admin && Access::hasRecentReauthentication($admin), 403);

        return $admin;
    }

    public function initiate(int $orderId, string $amount, string $reason, string $intent): Refund
    {
        Validator::make(compact('amount', 'reason', 'intent'), ['amount' => ['required', 'string', 'regex:/\A(?:0|[1-9][0-9]{0,17})(?:\.[0-9]{1,2})?\z/'], 'reason' => 'required|string|min:5|max:500', 'intent' => 'required|uuid'])->validate();

        return DB::transaction(function () use ($orderId, $amount, $reason, $intent) {
            $order = Order::whereKey($orderId)->lockForUpdate()->firstOrFail();
            $admin = $this->admin();
            $prior = Refund::where('order_id', $orderId)->where('idempotency_key', $intent)->first();
            if ($prior) {
                if (bccomp($prior->amount_idr, $amount, 2) !== 0 || $prior->reason !== $reason) {
                    throw ValidationException::withMessages(['refund' => 'Data permintaan lama tidak dapat diubah. Muat ulang status.']);
                }

                return $prior;
            }
            $reserved = Refund::where('order_id', $orderId)->whereIn('status', ['initiated', 'completed'])->sum('amount_idr');
            if ($order->status->value !== 'paid' || bccomp($amount, '0', 2) <= 0 || bccomp(bcadd((string) $reserved, $amount, 2), $order->price_locked_idr, 2) > 0) {
                throw ValidationException::withMessages(['refund' => 'Nominal melebihi sisa dana pesanan lunas atau tidak valid.']);
            }
            $refund = new Refund;
            $refund->forceFill(['order_id' => $orderId, 'amount_idr' => $amount, 'status' => 'initiated', 'method' => 'manual_bank', 'reason' => $reason, 'actor_id' => $admin->id, 'idempotency_key' => $intent])->save();
            Audit::record($admin, 'refund.initiated', $refund, ['resource_id' => $orderId, 'reason_provided' => true]);

            return $refund;
        }, 3);
    }

    public function decide(int $orderId, int $refundId, string $decision, bool $transferred, string $reference): Refund
    {
        Validator::make(compact('decision', 'reference'), ['decision' => 'required|in:completed,rejected', 'reference' => 'nullable|string|max:120'])->validate();

        return DB::transaction(function () use ($orderId, $refundId, $decision, $transferred, $reference) {
            Order::whereKey($orderId)->lockForUpdate()->firstOrFail();
            $admin = $this->admin();
            $refund = Refund::where('order_id', $orderId)->whereKey($refundId)->lockForUpdate()->firstOrFail();
            if ($refund->status->value !== 'initiated') {
                if ($refund->status->value !== $decision) {
                    throw ValidationException::withMessages(['refund' => 'Refund sudah diputuskan.']);
                }

                return $refund;
            }
            if ($decision === 'completed' && (! $transferred || trim($reference) === '')) {
                throw ValidationException::withMessages(['refund' => 'Periksa transfer manual yang sudah berhasil dan isi referensinya.']);
            }
            $refund->forceFill(['status' => $decision, 'actor_id' => $admin->id, 'bank_reference' => $decision === 'completed' ? $reference : null, 'refunded_at' => $decision === 'completed' ? now() : null])->save();
            Audit::record($admin, 'refund.decided', $refund, ['completed' => $decision === 'completed', 'reference_provided' => $decision === 'completed']);

            return $refund;
        }, 3);
    }
}
