<?php

namespace App\Livewire\Products;

use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        Gate::authorize('viewAny', Product::class);
        $search = mb_substr(trim($this->search), 0, 120);

        return view('livewire.products.index', ['products' => Product::query()->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))->latest('id')->paginate(20)])
            ->layout('components.layouts.portal', ['area' => 'admin', 'title' => 'Produk']);
    }
}
