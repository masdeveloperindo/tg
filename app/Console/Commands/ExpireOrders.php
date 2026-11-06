<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Support\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireOrders extends Command
{
    protected $signature = 'tg:expire-orders';

    protected $description = 'Expire only unpaid orders whose payment deadline has elapsed';

    public function handle(): int
    {
        $count = 0;
        Order::where('status', 'waiting')->where('expires_at', '<=', now())->orderBy('id')->chunkById(100, function ($orders) use (&$count) {
            foreach ($orders as $item) {
                $count += DB::transaction(function () use ($item) {
                    $order = Order::whereKey($item->id)->lockForUpdate()->firstOrFail();
                    if ($order->status->value !== 'waiting' || $order->expires_at->isFuture()) {
                        return 0;
                    }$order->forceFill(['status' => 'expired'])->save();
                    Audit::system('order.expired', $order);

                    return 1;
                }, 3);
            }
        });
        $this->info('Expired '.$count.' unpaid orders.');

        return self::SUCCESS;
    }
}
