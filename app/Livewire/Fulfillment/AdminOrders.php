<?php

namespace App\Livewire\Fulfillment;

use App\Actions\Licenses\FulfillOrder;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class AdminOrders extends Component
{
    use WithPagination;

    #[Locked]
    public ?int $orderId = null;

    public ?string $action = null;

    public bool $fundsChecked = false;

    public string $reference = '';

    public string $note = '';

    public function mount(?int $id = null): void
    {
        Gate::authorize('viewAny', User::class);
        $this->orderId = $id;
        if ($id) {
            Order::findOrFail($id);
        }
    }

    public function open(string $action): void
    {
        $order = Order::findOrFail($this->orderId);
        abort_unless(in_array($action, ['confirm', 'reject'], true), 422);
        Gate::authorize($action === 'confirm' ? 'confirmPayment' : 'reject', $order);
        $this->resetValidation();
        $this->reset('fundsChecked', 'reference', 'note');
        $this->action = $action;
    }

    public function cancel(): void
    {
        Gate::authorize('viewAny', User::class);
        $this->reset('action', 'fundsChecked', 'reference', 'note');
    }

    public function execute(FulfillOrder $service): void
    {
        abort_if($this->orderId === null, 404);
        if ($this->action === 'confirm') {
            $service->confirm($this->orderId, ['funds_checked' => $this->fundsChecked, 'reference' => $this->reference, 'note' => $this->note]);
        } elseif ($this->action === 'reject') {
            $service->reject($this->orderId, $this->note);
        } else {
            abort(422);
        }
        $this->reset('action', 'fundsChecked', 'reference', 'note');
        session()->flash('status', 'Status pesanan telah diperiksa dan disimpan oleh server.');
    }

    public function render()
    {
        Gate::authorize('viewAny', User::class);

        return view('livewire.fulfillment.orders', ['order' => $this->orderId ? Order::with(['product', 'user', 'checkoutQuote'])->findOrFail($this->orderId) : null, 'orders' => $this->orderId ? null : Order::with('product')->latest('id')->paginate(20)])->layout('components.layouts.portal', ['area' => 'admin', 'title' => 'Pesanan & pembayaran']);
    }
}
