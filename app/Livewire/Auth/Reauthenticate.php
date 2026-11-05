<?php

namespace App\Livewire\Auth;

use App\Actions\Auth\AdminTwoFactor;
use App\Models\User;
use App\Security\Access;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Reauthenticate extends Component
{
    public string $password = '';

    public string $otp = '';

    public function submit(AdminTwoFactor $twoFactor): void
    {
        try {
            $user = Auth::user();
            abort_unless($user instanceof User, 403);
            $twoFactor->reauth($user, $this->password, $this->otp);
        } finally {
            $this->reset('password', 'otp');
        }

        $this->redirectRoute('admin.clients');
    }

    public function render(): View
    {
        $usePortal = Auth::user() instanceof User && Access::isVerifiedAdmin(Auth::user());

        return view('livewire.auth.reauthenticate', ['usePortal' => $usePortal])->layout(
            $usePortal ? 'components.layouts.portal' : 'components.layouts.app',
            $usePortal ? ['area' => 'admin', 'title' => 'Verifikasi ulang'] : ['title' => 'Verifikasi ulang — Trader Garuda', 'noindex' => true],
        );
    }
}
