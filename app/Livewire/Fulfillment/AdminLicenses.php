<?php

namespace App\Livewire\Fulfillment;

use App\Actions\Licenses\ChangeLicenseStatus;
use App\Models\License;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class AdminLicenses extends Component
{
    use WithPagination;

    #[Locked]
    public ?int $licenseId = null;

    #[Locked]
    public ?string $intent = null;

    public ?string $target = null;

    public string $reason = '';

    public function mount(?int $id = null): void
    {
        Gate::authorize('viewAny', User::class);
        $this->licenseId = $id;
        if ($id) {
            License::findOrFail($id);
        }
    }

    public function open(string $target): void
    {
        $license = License::findOrFail($this->licenseId);
        Gate::authorize('manage', $license);
        abort_unless(in_array($target, ['active', 'suspended', 'revoked'], true), 422);
        $this->target = $target;
        $this->intent = (string) Str::uuid();
        $this->reason = '';
        $this->resetValidation();
    }

    public function cancel(): void
    {
        Gate::authorize('viewAny', User::class);
        $this->reset('target', 'intent', 'reason');
    }

    public function execute(ChangeLicenseStatus $service): void
    {
        abort_unless($this->licenseId && $this->intent && $this->target, 422);
        $service->change($this->licenseId, $this->target, $this->reason, $this->intent);
        $this->reset('target', 'intent', 'reason');
        session()->flash('status', 'Status lisensi telah diperiksa oleh server.');
    }

    #[On('license-account-updated')]
    public function refreshAccount(): void
    {
        Gate::authorize('viewAny', User::class);
    }

    public function render()
    {
        Gate::authorize('viewAny', User::class);

        return view('livewire.fulfillment.licenses', ['license' => $this->licenseId ? License::with('product')->findOrFail($this->licenseId) : null, 'licenses' => $this->licenseId ? null : License::with('product')->latest('id')->paginate(20)])->layout('components.layouts.portal', ['area' => 'admin', 'title' => 'Kelola lisensi']);
    }
}
