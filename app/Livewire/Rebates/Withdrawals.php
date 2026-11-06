<?php

namespace App\Livewire\Rebates;

use App\Actions\Rebates\Finance;
use App\Actions\Rebates\PayoutRules;
use App\Actions\Rebates\Payouts;
use App\Actions\Rebates\WalletTotals;
use App\Models\Withdrawal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class Withdrawals extends Component
{
    use WithPagination;

    public string $amount = '';

    public array $destination = ['method' => 'bank', 'provider' => '', 'holder' => '', 'number' => ''];

    #[Locked]
    public ?string $quoteToken = null;

    #[Locked]
    public array $review = [];

    #[Locked]
    public ?int $cancelId = null;

    public function preview(Payouts $service): void
    {
        Finance::owner();
        $this->resetValidation();
        $quote = $service->quote($this->amount, $this->destination, (string) Str::uuid());
        $this->quoteToken = $quote['token'];
        unset($quote['token']);
        $this->review = $quote;
        $this->cancelId = null;
    }

    public function submit(Payouts $service): void
    {
        abort_unless($this->quoteToken, 422);
        $service->request($this->quoteToken);
        $this->close();
        $this->reset('amount', 'destination');
        session()->flash('status', 'Pengajuan tersimpan. Saldo ditahan sampai pengajuan selesai.');
    }

    public function openCancel(int $id): void
    {
        $owner = Finance::owner();
        Withdrawal::where('user_id', $owner->id)->findOrFail($id);
        $this->cancelId = $id;
        $this->quoteToken = null;
        $this->review = [];
    }

    public function cancelWithdrawal(Payouts $service): void
    {
        abort_unless($this->cancelId, 422);
        $service->cancel($this->cancelId);
        $this->close();
        session()->flash('status', 'Pengajuan dibatalkan. Saldo tersedia sudah diperbarui.');
    }

    public function close(): void
    {
        Finance::owner();
        $this->reset('quoteToken', 'review', 'cancelId');
    }

    public function render()
    {
        $owner = Finance::owner();
        $data = DB::transaction(fn () => ['balance' => WalletTotals::read($owner->id), 'rules' => app(PayoutRules::class)->get(), 'withdrawals' => Withdrawal::where('user_id', $owner->id)->latest('id')->paginate(20)]);

        return view('livewire.rebates.withdrawals', $data)->layout('components.layouts.portal', ['title' => 'Pencairan Rebate']);
    }
}
