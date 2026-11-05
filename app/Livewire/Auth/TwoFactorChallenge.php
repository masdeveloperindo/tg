<?php

namespace App\Livewire\Auth;

use App\Actions\Auth\AdminTwoFactor;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class TwoFactorChallenge extends Component
{
    public string $otp = '';

    public function submit(AdminTwoFactor $twoFactor): void
    {
        try {
            $user = Auth::user();
            abort_unless($user instanceof User, 403);
            $twoFactor->challenge($user, $this->otp);
        } finally {
            $this->reset('otp');
        }

        $this->redirectRoute($user->must_change_password ? 'password.change' : 'admin');
    }

    public function render(): View
    {
        return view('livewire.auth.two-factor-challenge')->layout('components.layouts.app', ['title' => 'Verifikasi authenticator — Trader Garuda', 'noindex' => true]);
    }
}
