<?php

namespace App\Livewire\Rebates;

use App\Actions\Rebates\Finance;
use App\Actions\Rebates\RebateLedger;
use App\Models\RebateEntry;
use App\Models\TradingAccount;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class Ledger extends Component
{
    use WithPagination;

    #[Locked]
    public ?int $correctionId = null;

    public bool $editing = false;

    public string $accountId = '';

    public array $form = ['period' => '', 'lots' => '', 'amount' => '', 'reference' => '', 'memo' => ''];

    public function open(?int $id = null): void
    {
        Finance::admin();
        $this->reset('form', 'accountId');
        $this->resetValidation();
        $this->correctionId = $id;
        $this->form['period'] = now('Asia/Jakarta')->format('Y-m');
        if ($id) {
            $row = RebateEntry::findOrFail($id);
            $this->accountId = (string) $row->trading_account_id;
            $this->form['period'] = $row->period;
            $this->form['lots'] = $row->lots;
            $this->form['amount'] = $row->amount_usd;
        }
        $this->editing = true;
    }

    public function save(RebateLedger $service): void
    {
        if ($this->correctionId) {
            $service->correct($this->correctionId, $this->form);
        } else {
            $service->credit((int) $this->accountId, $this->form);
        } $this->cancel();
        session()->flash('status', 'Rebate tersimpan. Saldo dihitung dari catatan server.');
    }

    public function cancel(): void
    {
        Finance::admin(false);
        $this->reset('editing', 'correctionId');
    }

    public function render()
    {
        Finance::admin(false);

        return view('livewire.rebates.ledger', ['entries' => RebateEntry::with(['tradingAccount.broker', 'tradingAccount.user'])->latest('id')->paginate(25), 'accounts' => TradingAccount::where('status', 'approved')->with(['broker', 'user'])->orderBy('id')->get()])->layout('components.layouts.portal', ['area' => 'admin', 'title' => 'Input & koreksi rebate']);
    }
}
