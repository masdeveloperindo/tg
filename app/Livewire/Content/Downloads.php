<?php

namespace App\Livewire\Content;

use App\Actions\Rebates\Finance;
use App\Models\License;
use Livewire\Component;
use Livewire\WithPagination;

class Downloads extends Component
{
    use WithPagination;

    public function render()
    {
        $owner = Finance::owner();

        return view('livewire.content.downloads', ['licenses' => License::where('user_id', $owner->id)->with(['product.versions' => fn ($q) => $q->whereNotNull('released_at')->where('released_at', '<=', now())->orderByDesc('released_at')])->latest('id')->paginate(20)])->layout('components.layouts.portal', ['title' => 'Unduhan Saya']);
    }
}
