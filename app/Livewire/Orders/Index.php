<?php

namespace App\Livewire\Orders;

use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public function render()
    {
        Gate::authorize('viewAny', Order::class);

        return view('livewire.orders.index', ['orders' => Order::where('user_id', Auth::id())->with(['product', 'checkoutQuote'])->latest('id')->paginate(20)])->layout('components.layouts.portal', ['area' => 'member', 'title' => 'Pesanan saya']);
    }
}
