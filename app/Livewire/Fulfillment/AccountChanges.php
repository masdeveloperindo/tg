<?php

namespace App\Livewire\Fulfillment;

use App\Actions\Licenses\ChangeAccount;
use App\Models\License;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

class AccountChanges extends Component
{
    #[Locked]
    public int $licenseId;

    #[Locked]
    public ?int $requestId = null;

    #[Locked]
    public ?string $decision = null;

    public string $reason = '';

    public function mount(int $licenseId): void
    {
        Gate::authorize('viewAny', User::class);
        $this->licenseId = $licenseId;
    }

    public function open(int $requestId, string $decision): void
    {
        $license = License::findOrFail($this->licenseId);
        Gate::authorize('manage', $license);
        abort_unless(in_array($decision, ['approved', 'rejected'], true), 422);
        $license->accountChangeRequests()->findOrFail($requestId);
        $this->resetValidation();
        $this->reason = '';
        $this->requestId = $requestId;
        $this->decision = $decision;
    }

    public function cancel(): void
    {
        Gate::authorize('viewAny', User::class);
        $this->reset('requestId', 'decision', 'reason');
    }

    public function execute(ChangeAccount $service): void
    {
        abort_unless($this->requestId && $this->decision, 422);
        $service->decide($this->licenseId, $this->requestId, $this->decision, $this->reason);
        $this->cancel();
        $this->dispatch('license-account-updated');
        session()->flash('status', 'Keputusan perubahan akun tersimpan.');
    }

    public function render()
    {
        Gate::authorize('viewAny', User::class);
        $license = License::findOrFail($this->licenseId);

        return view('livewire.fulfillment.account-changes', ['license' => $license, 'changes' => $license->accountChangeRequests()->latest('id')->limit(20)->get(), 'selected' => $this->requestId ? $license->accountChangeRequests()->findOrFail($this->requestId) : null]);
    }
}
