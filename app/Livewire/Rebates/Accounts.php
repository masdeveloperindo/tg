<?php

namespace App\Livewire\Rebates;

use App\Actions\Rebates\Finance;
use App\Actions\Rebates\IbAccounts;
use App\Models\Broker;
use App\Models\TradingAccount;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Accounts extends Component
{
    use WithPagination;

    #[Url]
    public string $status = '';

    public string $brokerId = '';

    public string $account = '';

    public string $proof = '';

    public bool $attaching = false;

    public bool $checked = false;

    public string $note = '';

    #[Locked]
    public ?int $accountId = null;

    #[Locked]
    public ?string $decision = null;

    protected function adminArea(): bool
    {
        return false;
    }

    private function authorizePage(): void
    {
        $this->adminArea() ? Finance::admin(false) : Finance::owner();
    }

    public function openAttach(): void
    {
        Finance::owner();
        $this->attaching = true;
        $this->resetValidation();
    }

    public function attach(IbAccounts $service): void
    {
        $service->attach((int) $this->brokerId, $this->account, $this->proof);
        $this->cancel();
        $this->reset('brokerId', 'account', 'proof');
        session()->flash('status', 'Pengajuan tersimpan. Tunggu pemeriksaan admin.');
    }

    public function openDecision(int $id, string $decision): void
    {
        Finance::admin();
        abort_unless(in_array($decision, ['approved', 'rejected'], true), 422);
        TradingAccount::findOrFail($id);
        $this->accountId = $id;
        $this->decision = $decision;
        $this->reset('note', 'checked');
        $this->resetValidation();
    }

    public function decide(IbAccounts $service): void
    {
        abort_unless($this->accountId && $this->decision, 422);
        $service->decide($this->accountId, $this->decision, $this->note, $this->checked);
        $this->cancel();
        session()->flash('status', 'Keputusan verifikasi akun tersimpan.');
    }

    public function cancel(): void
    {
        $this->authorizePage();
        $this->reset('attaching', 'accountId', 'decision', 'note', 'checked');
    }

    public function render()
    {
        $this->authorizePage();
        abort_unless(in_array($this->status, ['', 'pending', 'approved', 'rejected'], true), 422);
        $query = TradingAccount::when($this->status, fn ($q) => $q->where('status', $this->status))->with(['broker', 'user'])->withSum('rebateEntries as total_rebate', 'amount_usd');
        if (! $this->adminArea()) {
            $query->where('user_id', auth()->id());
        }

        return view('livewire.rebates.accounts', ['accounts' => $query->latest('id')->paginate(20), 'adminArea' => $this->adminArea(), 'brokers' => Broker::where('is_active', true)->where('valid_from', '<=', now('Asia/Jakarta')->toDateString())->orderBy('name')->get(), 'selected' => $this->accountId ? TradingAccount::with(['broker', 'user'])->findOrFail($this->accountId) : null])->layout('components.layouts.portal', ['area' => $this->adminArea() ? 'admin' : 'member', 'title' => $this->adminArea() ? 'Verifikasi akun IB' : 'Broker IB Saya']);
    }
}
