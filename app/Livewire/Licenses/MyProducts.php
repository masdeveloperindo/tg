<?php

namespace App\Livewire\Licenses;

use App\Actions\Licenses\ChangeAccount;
use App\Models\License;
use App\Security\Access;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class MyProducts extends Component
{
    use WithPagination;

    #[Locked]
    public ?int $licenseId = null;

    #[Locked]
    public ?string $intent = null;

    public bool $changing = false;

    public string $account = '';

    public string $server = '';

    private function owner(): int
    {
        $user = Auth::user()?->fresh();
        abort_unless($user && Access::isOwner($user, $user->id), 403);

        return $user->id;
    }

    private function license(): License
    {
        return License::where('user_id', $this->owner())->with('product')->findOrFail($this->licenseId);
    }

    public function mount(?int $licenseId = null): void
    {
        $this->owner();
        $this->licenseId = $licenseId;
        if ($licenseId) {
            $this->license();
        }
    }

    public function openChange(): void
    {
        $license = $this->license();
        abort_unless($license->status->value === 'active' && $license->expires_at->isFuture(), 403);
        $this->reset('account', 'server');
        $this->resetValidation();
        $this->intent = (string) Str::uuid();
        $this->changing = true;
    }

    public function cancel(): void
    {
        $this->owner();
        $this->reset('changing', 'account', 'server', 'intent');
    }

    public function requestChange(ChangeAccount $service): void
    {
        $license = $this->license();
        abort_unless($this->intent !== null, 422);
        $service->request($license->id, $this->account, $this->server, $this->intent);
        $this->cancel();
        session()->flash('status', 'Permintaan akun telah dikirim untuk pemeriksaan admin. Akun lama tetap berlaku sampai disetujui.');
    }

    public function render()
    {
        $id = $this->owner();
        $license = $this->licenseId ? $this->license() : null;

        return view('livewire.licenses.my-products', [
            'license' => $license,
            'licenses' => $license ? null : License::where('user_id', $id)->with('product')->latest('id')->paginate(20),
            'remainingChanges' => $license ? ChangeAccount::remaining($license) : 0,
            'lastSeen' => $license ? $license->activations()->whereNotNull('last_seen_at')->latest('last_seen_at')->first()?->last_seen_at : null,
            'logs' => $license ? $license->logs()->orderByDesc('created_at')->orderByDesc('id')->limit(50)->get() : [],
            'changes' => $license ? $license->accountChangeRequests()->latest('id')->limit(10)->get() : [],
        ])->layout('components.layouts.portal', ['title' => 'Produk Saya']);
    }
}
