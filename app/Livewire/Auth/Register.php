<?php

namespace App\Livewire\Auth;

use App\Actions\Auth\Authentication;
use App\Enums\UserRole;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Register extends Component
{
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $contact_channel = 'telegram';

    public string $contact_value = '';

    public function submit(Authentication $authentication): void
    {
        try {
            $user = $authentication->register([
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
                'contact_channel' => $this->contact_channel,
                'contact_value' => $this->contact_value,
            ]);
        } finally {
            $this->reset('password', 'password_confirmation');
        }

        if ($user->role === UserRole::Client) {
            $this->redirectIntended(route('member'));

            return;
        }

        $this->redirectRoute($user->role === UserRole::Admin
            ? ($user->totp_enabled_at ? 'admin.2fa.challenge' : 'admin.2fa.setup')
            : 'member');
    }

    public function render(): View
    {
        return view('livewire.auth.register')->layout('components.layouts.app', ['title' => 'Daftar — Trader Garuda', 'noindex' => true]);
    }
}
