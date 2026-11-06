<?php

namespace App\Livewire\Rebates;

class AdminAccounts extends Accounts
{
    protected function adminArea(): bool
    {
        return true;
    }
}
