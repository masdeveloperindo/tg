<?php

namespace App\Actions\Operations;

use App\Models\License;
use App\Models\LicenseFlag;
use App\Models\Order;
use App\Models\TradingAccount;
use App\Models\Withdrawal;

final class Tasks
{
    public static function orders(string $task = '')
    {
        $q = Order::query();
        if ($task === 'waiting_payment') {
            $q->where('status', 'waiting')->where('expires_at', '>', now());
        } elseif ($task === 'missing_license') {
            $q->where('status', 'paid')->where(function ($q) {
                $q->where(fn ($n) => $n->where('order_type', 'new')->whereNotExists(fn ($s) => $s->selectRaw('1')->from('licenses')->whereColumn('licenses.issued_order_id', 'orders.id')))->orWhere(fn ($n) => $n->where('order_type', 'renewal')->whereNull('renewal_license_id'));
            });
        } elseif ($task !== '') {
            abort(422);
        }

        return $q;
    }

    public static function licenses(string $task = '')
    {
        $q = License::query();
        if ($task === 'expiring') {
            $q->where('status', 'active')->where('expires_at', '>', now())->where('expires_at', '<=', now()->addDays(30));
        } elseif ($task !== '') {
            abort(422);
        }

        return $q;
    }

    public static function cards(): array
    {
        return [
            ['label' => 'Menunggu pemeriksaan transfer', 'count' => self::orders('waiting_payment')->count(), 'url' => route('admin.pesanan', ['task' => 'waiting_payment'])],
            ['label' => 'Akun IB menunggu', 'count' => TradingAccount::where('status', 'pending')->count(), 'url' => route('admin.rebate.akun', ['status' => 'pending'])],
            ['label' => 'Pencairan diajukan', 'count' => Withdrawal::where('status', 'requested')->count(), 'url' => route('admin.penarikan', ['status' => 'requested'])],
            ['label' => 'Pencairan diproses', 'count' => Withdrawal::where('status', 'processing')->count(), 'url' => route('admin.penarikan', ['status' => 'processing'])],
            ['label' => 'Lisensi berakhir dalam 30 hari', 'count' => self::licenses('expiring')->count(), 'url' => route('admin.lisensi', ['task' => 'expiring'])],
            ['label' => 'Anomali baru', 'count' => LicenseFlag::where('status', 'new')->count(), 'url' => route('admin.anomali', ['status' => 'new'])],
            ['label' => 'Lunas tanpa lisensi', 'count' => self::orders('missing_license')->count(), 'url' => route('admin.pesanan', ['task' => 'missing_license'])],
        ];
    }
}
