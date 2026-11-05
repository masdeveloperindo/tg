<?php

namespace App\Livewire\Orders;

use App\Actions\Orders\CheckoutSettings;
use App\Models\Order;
use App\Support\OrderDisplay;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Detail extends Component
{
    #[Locked]
    public int $orderId;

    public function mount(string $kode): void
    {
        $order = Order::where('user_id', Auth::id())->where('code', $kode)->firstOrFail();
        Gate::authorize('view', $order);
        $this->orderId = $order->id;
    }

    public function render()
    {
        $order = Order::where('user_id', Auth::id())->with(['product', 'checkoutQuote'])->findOrFail($this->orderId);
        Gate::authorize('view', $order);
        $payment = OrderDisplay::payment($order);
        $clipboard = OrderDisplay::clipboard($order);
        $telegram = app(CheckoutSettings::class)->telegram();

        return view('livewire.orders.detail', compact('order', 'payment', 'clipboard', 'telegram'))->layout('components.layouts.portal', ['area' => 'member', 'title' => 'Rincian pesanan']);
    }
}
