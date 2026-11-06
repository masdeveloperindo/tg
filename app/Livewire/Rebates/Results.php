<?php

namespace App\Livewire\Rebates;

use App\Actions\Rebates\Finance;
use App\Actions\Rebates\WalletTotals;
use App\Models\RebateEntry;
use App\Models\TradingAccount;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class Results extends Component
{
    use WithPagination;

    public string $period = '';

    public string $brokerId = '';

    public string $accountId = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $owner = Finance::owner();
        $this->validate(['period' => 'nullable|date_format:Y-m', 'brokerId' => 'nullable|integer|min:1', 'accountId' => 'nullable|integer|min:1']);
        $data = DB::transaction(function () use ($owner) {
            $balance = WalletTotals::read($owner->id);
            $query = RebateEntry::whereHas('tradingAccount', function ($q) use ($owner) {
                $q->where('user_id', $owner->id);
                if ($this->brokerId !== '') {
                    $q->where('broker_id', (int) $this->brokerId);
                } if ($this->accountId !== '') {
                    $q->whereKey((int) $this->accountId);
                }
            });
            if ($this->period !== '') {
                $query->where('period', $this->period);
            }
            $groups = (clone $query)->selectRaw('trading_account_id, period, SUM(lots) AS total_lots, SUM(amount_usd) AS total_usd')->groupBy('trading_account_id', 'period')->with('tradingAccount.broker')->orderByDesc('period')->orderBy('trading_account_id')->paginate(20);

            return ['balance' => $balance, 'groups' => $groups, 'accounts' => TradingAccount::where('user_id', $owner->id)->with('broker')->get(), 'entries' => $query->with('tradingAccount.broker')->latest('id')->limit(50)->get()];
        });

        return view('livewire.rebates.results', $data)->layout('components.layouts.portal', ['title' => 'Hasil Rebate']);
    }
}
