<?php

namespace App\Livewire\Auth;

use App\Actions\Auth\Authentication;
use App\Enums\UserRole;
use App\Models\User;
use App\Security\Access;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ChangePassword extends Component
{
    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function submit(Authentication $authentication): void
    {
        try {
            $user = Auth::user();
            abort_unless($user instanceof User, 403);
            $authentication->changePassword($this->current_password, $this->password, $this->password_confirmation);
        } finally {
            $this->reset('current_password', 'password', 'password_confirmation');
        }

        session()->flash('status', 'Password berhasil diperbarui. Sesi lama telah diputus.');
        $this->redirectRoute($user->role === UserRole::Admin ? 'admin.2fa.challenge' : 'member');
    }

    public function render(): View
    {
        $user = Auth::user();
        $usePortal = $user instanceof User && ! $user->must_change_password
            && ($user->role === UserRole::Client || Access::isVerifiedAdmin($user));

        return view('livewire.auth.change-password', ['usePortal' => $usePortal])->layout(
            $usePortal ? 'components.layouts.portal' : 'components.layouts.app',
            $usePortal ? ['area' => $user->role === UserRole::Admin ? 'admin' : 'member', 'title' => 'Ganti password'] : ['title' => 'Ganti password — Trader Garuda', 'noindex' => true],
        );
    }
}
