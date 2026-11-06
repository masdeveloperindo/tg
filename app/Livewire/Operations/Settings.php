<?php

namespace App\Livewire\Operations;

use App\Actions\Operations\Configuration;
use App\Actions\Rebates\Finance;
use App\Models\Setting;
use Livewire\Component;

class Settings extends Component
{
    public array $pricing = ['monthly_discount_pct' => '', 'rounding_increment_idr' => '', 'rounding_mode' => 'nearest', 'quote_minutes' => '', 'payment_minutes' => ''];

    public array $methods = [];

    public string $telegram = '';

    public array $anomaly = [];

    public array $site = ['title' => '', 'description' => ''];

    public bool $confirming = false;

    public function mount(): void
    {
        Finance::admin();
        $this->pricing = array_replace($this->pricing, Configuration::json('checkout.pricing'));
        $this->methods = Configuration::json('checkout.payment_methods');
        $this->telegram = (string) Setting::where('key', 'telegram_admin_username')->first()?->value_encrypted;
        $this->anomaly = Configuration::anomaly();
        $this->site = array_replace($this->site, Configuration::json('public.site'));
    }

    public function addMethod(): void
    {
        Finance::admin();
        abort_if(count($this->methods) >= 10, 422);
        $this->methods[] = ['id' => '', 'label' => '', 'account_holder' => '', 'account_number' => '', 'instructions' => ''];
    }

    public function removeMethod(int $i): void
    {
        Finance::admin();
        unset($this->methods[$i]);
        $this->methods = array_values($this->methods);
    }

    public function preview(): void
    {
        Finance::admin();
        $this->confirming = true;
        $this->resetValidation();
    }

    public function close(): void
    {
        Finance::admin();
        $this->confirming = false;
    }

    public function save(Configuration $service): void
    {
        $service->save($this->pricing, $this->methods, $this->telegram, $this->anomaly, $this->site);
        $this->confirming = false;
        session()->flash('status', 'Pengaturan tersimpan. Pesanan dan penarikan lama tetap memakai snapshot semula.');
    }

    public function render()
    {
        Finance::admin();

        return view('livewire.operations.settings')->layout('components.layouts.portal', ['area' => 'admin', 'title' => 'Pengaturan']);
    }
}
