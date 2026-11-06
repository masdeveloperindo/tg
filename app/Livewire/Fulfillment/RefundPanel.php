<?php

namespace App\Livewire\Fulfillment;

use App\Actions\Refunds\ManageRefund;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

class RefundPanel extends Component
{
    #[Locked]
    public int $orderId;

    #[Locked]
    public ?string $intent = null;

    #[Locked]
    public ?int $refundId = null;

    #[Locked]
    public ?string $action = null;

    public string $amount = '';

    public string $reason = '';

    public string $reference = '';

    public bool $transferred = false;

    public function mount(int $orderId): void
    {
        Gate::authorize('viewAny', User::class);
        $this->orderId = $orderId;
    }

    public function open(string $action, ?int $refundId = null): void
    {
        $order = Order::findOrFail($this->orderId);
        Gate::authorize('confirmPayment', $order);
        abort_unless(in_array($action, ['initiate', 'completed', 'rejected'], true), 422);
        if ($refundId) {
            $order->refunds()->findOrFail($refundId);
        }
        $this->resetValidation();
        $this->reset('amount', 'reason', 'reference', 'transferred');
        $this->action = $action;
        $this->refundId = $refundId;
        $this->intent = (string) Str::uuid();
    }

    public function cancel(): void
    {
        Gate::authorize('viewAny', User::class);
        $this->reset('action', 'intent', 'refundId', 'reference', 'transferred', 'amount', 'reason');
    }

    public function execute(ManageRefund $service): void
    {
        abort_unless($this->intent && $this->action, 422);
        if ($this->action === 'initiate') {
            $service->initiate($this->orderId, $this->amount, $this->reason, $this->intent);
        } else {
            abort_unless($this->refundId !== null, 422);
            $service->decide($this->orderId, $this->refundId, $this->action, $this->transferred, $this->reference);
        }
        $this->cancel();
        session()->flash('status', 'Catatan refund disimpan. Status lisensi tidak diubah.');
    }

    public function render()
    {
        Gate::authorize('viewAny', User::class);
        $order = Order::findOrFail($this->orderId);

        return view('livewire.fulfillment.refund-panel', ['order' => $order, 'refunds' => $order->refunds()->latest('id')->get()]);
    }
}
