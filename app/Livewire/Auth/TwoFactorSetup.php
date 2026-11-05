<?php

namespace App\Livewire\Auth;

use App\Actions\Auth\AdminTwoFactor;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class TwoFactorSetup extends Component
{
    public string $otp = '';

    public function mount(AdminTwoFactor $twoFactor): void
    {
        $twoFactor->beginSetup($this->actor());
    }

    public function submit(AdminTwoFactor $twoFactor): void
    {
        try {
            $codes = $twoFactor->confirmSetup($this->actor(), $this->otp);
        } finally {
            $this->reset('otp');
        }

        session()->flash('auth.recovery_codes_once', $codes);
        $this->redirectRoute('admin.2fa.recovery');
    }

    public function render(): View
    {
        $user = $this->actor();
        $startedAt = session('auth.totp_setup_started_at');
        $confirmedAt = session('auth.password_confirmed_at');
        $now = now()->timestamp;
        $pendingIsCurrent = (string) session('auth.totp_setup_user_id') === (string) $user->getKey()
            && is_int($startedAt) && $startedAt >= $now - 600 && $startedAt <= $now
            && is_int($confirmedAt) && $confirmedAt >= $now - 600 && $confirmedAt <= $now;

        return view('livewire.auth.two-factor-setup', [
            'setupSecret' => $pendingIsCurrent ? session('auth.totp_setup_secret') : null,
            'accountEmail' => $user->email,
        ])->layout('components.layouts.app', ['title' => 'Setup authenticator — Trader Garuda', 'noindex' => true]);
    }

    private function actor(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->role === UserRole::Admin && $user->status === UserStatus::Active, 403);

        return $user;
    }
}
