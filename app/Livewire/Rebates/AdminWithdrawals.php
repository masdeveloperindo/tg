<?php

namespace App\Livewire\Rebates;

use App\Actions\Rebates\Finance;
use App\Actions\Rebates\PayoutRules;
use App\Actions\Rebates\Payouts;
use App\Actions\Rebates\WalletTotals;
use App\Models\Withdrawal;
use App\Security\Access;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class AdminWithdrawals extends Component
{
    use WithPagination;

    #[Locked]
    public ?int $withdrawalId = null;

    #[Locked]
    public ?string $action = null;

    public bool $transferred = false;

    public string $reference = '';

    public string $reason = '';

    public string $paidAt = '';

    public array $settings = ['minimum_usd' => '', 'fx_rate_idr' => '', 'enabled' => false];

    public function mount(?int $id = null): void
    {
        Finance::admin(false);
        $this->withdrawalId = $id;
        if ($id) {
            Withdrawal::findOrFail($id);
        } $this->settings = app(PayoutRules::class)->get() ?? $this->settings;
    }

    public function open(string $target): void
    {
        Finance::admin();
        abort_unless(in_array($target, ['processing', 'paid', 'rejected', 'settings'], true), 422);
        if ($target !== 'settings') {
            Withdrawal::findOrFail($this->withdrawalId);
        } $this->action = $target;
        $this->reset('transferred', 'reference', 'reason');
        $this->paidAt = now('Asia/Jakarta')->format('Y-m-d\TH:i');
        $this->resetValidation();
    }

    public function execute(Payouts $service, PayoutRules $rules): void
    {
        abort_unless($this->action, 422);
        if ($this->action === 'settings') {
            $rules->save($this->settings);
        } else {
            abort_unless($this->withdrawalId, 422);
            $service->decide($this->withdrawalId, $this->action, ['transferred' => $this->transferred, 'reference' => $this->reference, 'reason' => $this->reason, 'paid_at' => $this->paidAt]);
        } $this->close();
        session()->flash('status', 'Status server tersimpan. Tidak ada transfer otomatis.');
    }

    public function close(): void
    {
        Finance::admin(false);
        $this->reset('action', 'transferred', 'reference', 'reason', 'paidAt');
    }

    public function render()
    {
        $admin = Finance::admin(false);
        $row = $this->withdrawalId ? Withdrawal::with('user')->findOrFail($this->withdrawalId) : null;
        $reauth = Access::hasRecentReauthentication($admin);

        return view('livewire.rebates.admin-withdrawals', ['withdrawal' => $row, 'withdrawals' => $row ? null : Withdrawal::with('user')->latest('id')->paginate(20), 'destination' => $row && $reauth ? json_decode($row->destination_enc, true) : null, 'balance' => $row ? WalletTotals::read($row->user_id) : null, 'reauth' => $reauth, 'rules' => app(PayoutRules::class)->get()])->layout('components.layouts.portal', ['area' => 'admin', 'title' => 'Kelola pencairan']);
    }
}
