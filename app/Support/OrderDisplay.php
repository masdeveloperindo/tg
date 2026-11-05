<?php

namespace App\Support;

use App\Models\Order;

final class OrderDisplay
{
    public static function money(string $value): string
    {
        [$whole,$fraction] = explode('.', bcadd($value, '0', 2));

        return 'Rp'.preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $whole).','.$fraction;
    }

    public static function status(Order $order): string
    {
        return $order->status->value === 'waiting' && now()->gte($order->expires_at) ? 'expired' : $order->status->value;
    }

    public static function label(Order $order): string
    {
        return ['waiting' => 'Menunggu Pembayaran', 'paid' => 'Lunas', 'rejected' => 'Ditolak', 'expired' => 'Kedaluwarsa'][self::status($order)];
    }

    public static function payment(Order $order): ?array
    {
        foreach ($order->checkoutQuote?->payment_details ?? [] as $method) {
            if ($method['id'] === $order->payment_method_id) {
                return $method;
            }
        }

        return null;
    }

    public static function clipboard(Order $order): string
    {
        $payment = self::payment($order);
        $product = $order->checkoutQuote?->pricing_snapshot['product_name'] ?? $order->product->name;
        $text = "Halo Admin Trader Garuda. Saya ingin mengonfirmasi pembayaran pesanan {$order->code} untuk produk {$product}, nominal ".self::money($order->price_locked_idr).", akun MT5 {$order->mt5_account} ({$order->mt5_server}, {$order->account_type->value}). Akses sampai 31 Desember {$order->coverage_year}.";
        if ($payment) {
            $text .= " Tujuan: {$payment['label']} {$payment['account_number']} a.n. {$payment['account_holder']}.";
        }

        return $text.' Status: '.self::label($order).'. Batas bayar: '.$order->expires_at->timezone('Asia/Jakarta')->format('d M Y H:i').' WIB. Saya akan melampirkan bukti transfer di chat ini. Mohon dicek. Terima kasih.';
    }
}
