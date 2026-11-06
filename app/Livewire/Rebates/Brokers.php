<?php

namespace App\Livewire\Rebates;

use App\Actions\Rebates\Finance;
use App\Actions\Rebates\ManageBrokers;
use App\Models\ActivityLog;
use App\Models\Broker;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class Brokers extends Component
{
    use WithFileUploads, WithPagination;

    #[Locked]
    public ?int $brokerId = null;

    public bool $editing = false;

    public array $form = ['name' => '', 'ib_link' => '', 'rate_usd_per_lot' => '', 'valid_from' => '', 'terms' => '', 'is_active' => false];

    public $logo;

    public function open(?int $id = null): void
    {
        Finance::admin();
        $this->brokerId = $id;
        $this->resetValidation();
        $this->reset('logo', 'form');
        if ($id) {
            $b = Broker::findOrFail($id);
            $this->form = ['name' => $b->name, 'ib_link' => $b->ib_link, 'rate_usd_per_lot' => $b->rate_usd_per_lot, 'valid_from' => $b->valid_from->format('Y-m-d'), 'terms' => $b->terms ?? '', 'is_active' => $b->is_active];
        }
        $this->editing = true;
    }

    public function cancel(): void
    {
        Finance::admin(false);
        $this->reset('editing', 'brokerId', 'logo');
    }

    public function save(ManageBrokers $service): void
    {
        $service->save($this->brokerId, $this->form, $this->logo);
        $this->cancel();
        session()->flash('status', 'Informasi broker tersimpan. Riwayat tarif tetap dicatat.');
    }

    public function render()
    {
        Finance::admin(false);

        return view('livewire.rebates.brokers', ['brokers' => Broker::orderBy('name')->paginate(20), 'changes' => ActivityLog::where('action', 'broker.saved')->latest('id')->limit(10)->get()])->layout('components.layouts.portal', ['area' => 'admin', 'title' => 'Broker & promosi IB']);
    }
}
