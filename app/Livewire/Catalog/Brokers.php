<?php

namespace App\Livewire\Catalog;

use App\Models\Broker;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Brokers extends Component
{
    public string $brokerId = '';

    public string $lots = '';

    #[Locked]
    public ?string $estimate = null;

    public function updated(): void
    {
        $this->estimate = null;
    }

    public function calculate(): void
    {
        $this->estimate = null;
        $budget = 'public:rebate-estimate:'.hash('sha256', (string) request()->ip());
        if (RateLimiter::tooManyAttempts($budget, 30)) {
            throw ValidationException::withMessages(['lots' => 'Terlalu banyak perhitungan. Tunggu satu menit sebelum mencoba kembali.']);
        }
        RateLimiter::hit($budget, 60);
        $data = $this->validate(['brokerId' => 'required|integer', 'lots' => ['required', 'string', 'regex:/^\d{1,6}(?:\.\d{1,4})?$/']]);
        $broker = $this->available()->whereKey($data['brokerId'])->first();
        if (! $broker) {
            throw ValidationException::withMessages(['brokerId' => 'Pilih broker yang tersedia.']);
        }
        $this->estimate = bcmul($data['lots'], $broker->rate_usd_per_lot, 4);
    }

    private function available()
    {
        return Broker::where('is_active', true)->where('valid_from', '<=', now('Asia/Jakarta')->toDateString());
    }

    public function render(): View
    {
        return view('livewire.catalog.brokers', ['brokers' => $this->available()->orderBy('name')->get()])->layout('components.layouts.app', ['title' => 'Broker & informasi rebate — Trader Garuda', 'description' => 'Informasi kemitraan broker dan estimasi rebate. Program IB bersifat opsional dan tidak menjadi syarat membeli EA.', 'canonical' => '/broker']);
    }
}
