<?php

namespace App\Livewire\Operations;

use App\Actions\Rebates\Finance;
use App\Models\ActivityLog;
use Livewire\Component;
use Livewire\WithPagination;

class AuditLog extends Component
{
    use WithPagination;

    public function render()
    {
        Finance::admin(false);

        return view('livewire.operations.audit', ['logs' => ActivityLog::latest('id')->paginate(30)])->layout('components.layouts.portal', ['area' => 'admin', 'title' => 'Jejak audit']);
    }
}
