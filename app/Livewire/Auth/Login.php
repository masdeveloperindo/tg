<?php

namespace App\Livewire\Auth;

use App\Actions\Auth\Authentication;
use App\Enums\UserRole;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public function submit(Authentication $authentication): void
    {
        try {
            $user = $authentication->login($this->email, $this->password);
        } finally {
            $this->reset('password');
        }

        $this->redirectRoute($user->role === UserRole::Admin
            ? ($user->totp_enabled_at ? 'admin.2fa.challenge' : 'admin.2fa.setup')
            : ($user->must_change_password ? 'password.change' : 'member'));
    }

    public function render(): View
    {
        return view('livewire.auth.login')->layout('components.layouts.app', ['title' => 'Masuk — Trader Garuda', 'noindex' => true]);
    }
}
