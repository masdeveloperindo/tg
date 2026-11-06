<?php

namespace App\Livewire\Operations;

use App\Actions\Operations\ReviewAnomaly;
use App\Actions\Rebates\Finance;
use App\Models\LicenseFlag;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Anomalies extends Component
{
    use WithPagination;

    #[Locked]
    public ?int $flagId = null;

    #[Locked]
    public ?string $target = null;

    #[Url]
    public string $status = '';

    public string $note = '';

    public function mount(?int $id = null): void
    {
        Finance::admin(false);
        $this->flagId = $id;
        if ($id) {
            LicenseFlag::findOrFail($id);
        }
    }

    public function open(string $target): void
    {
        Finance::admin();
        abort_unless(in_array($target, ['reviewed', 'ignored', 'suspended'], true), 422);
        $this->target = $target;
        $this->note = '';
        $this->resetValidation();
    }

    public function close(): void
    {
        Finance::admin(false);
        $this->reset('target', 'note');
    }

    public function decide(ReviewAnomaly $service): void
    {
        abort_unless($this->flagId && $this->target, 422);
        $service->decide($this->flagId, $this->target, $this->note);
        $this->close();
        session()->flash('status', 'Keputusan flag disimpan.');
    }

    public function render()
    {
        Finance::admin(false);
        abort_unless(in_array($this->status, ['', 'new', 'reviewed', 'ignored', 'suspended'], true), 422);

        return view('livewire.operations.anomalies', ['flag' => $this->flagId ? LicenseFlag::with('license.product')->findOrFail($this->flagId) : null, 'flags' => $this->flagId ? null : LicenseFlag::with('license.product')->when($this->status, fn ($q) => $q->where('status', $this->status))->latest('last_seen_at')->paginate(20)])->layout('components.layouts.portal', ['area' => 'admin', 'title' => 'Review anomali']);
    }
}
