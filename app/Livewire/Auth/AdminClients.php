<?php

namespace App\Livewire\Auth;

use App\Actions\Auth\Authentication;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class AdminClients extends Component
{
    public string $search = '';

    public ?int $targetId = null;

    public string $reason = '';

    public bool $contactChecked = false;

    public bool $identityChecked = false;

    public function mount(): void
    {
        Gate::authorize('viewAny', User::class);
    }

    public function openReset(int $id): void
    {
        Gate::authorize('viewAny', User::class);
        $client = User::query()->findOrFail($id);
        Gate::authorize('resetPassword', $client);

        $this->resetValidation();
        $this->reset('reason', 'contactChecked', 'identityChecked');
        $this->targetId = $client->getKey();
        $this->dispatch('reset-modal-opened');
    }

    public function cancelReset(): void
    {
        Gate::authorize('viewAny', User::class);
        if ($this->targetId !== null) {
            Gate::authorize('resetPassword', User::query()->findOrFail($this->targetId));
        }

        $this->reset('targetId', 'reason', 'contactChecked', 'identityChecked');
        $this->resetValidation();
        $this->dispatch('reset-modal-closed');
    }

    public function resetPassword(Authentication $authentication): void
    {
        Gate::authorize('viewAny', User::class);
        abort_if($this->targetId === null, 404);
        $client = User::query()->findOrFail($this->targetId);
        Gate::authorize('resetPassword', $client);

        $temporaryPassword = $authentication->manualReset($client, $this->reason, $this->contactChecked, $this->identityChecked);
        session()->flash('auth.temporary_password_once', $temporaryPassword);
        session()->flash('auth.temporary_password_user_id', $client->getKey());
        $this->reset('targetId', 'reason', 'contactChecked', 'identityChecked');
        $this->redirectRoute('admin.clients.password-result');
    }

    public function render(): View
    {
        Gate::authorize('viewAny', User::class);
        $search = mb_substr(trim($this->search), 0, 120);
        $clients = User::query()->where('role', UserRole::Client)
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%');
            }))
            ->orderByDesc('id')->limit(20)->get(['id', 'name', 'email', 'status', 'must_change_password']);
        $target = null;

        if ($this->targetId !== null) {
            $target = User::query()->findOrFail($this->targetId);
            Gate::authorize('resetPassword', $target);
        }

        return view('livewire.auth.admin-clients', ['clients' => $clients, 'target' => $target])
            ->layout('components.layouts.portal', ['area' => 'admin', 'title' => 'Kelola akses client']);
    }
}
