<?php

namespace App\Livewire\Catalog;

use App\Models\Product;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class Products extends Component
{
    use WithPagination;

    public string $search = '';

    public string $type = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedType(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $search = mb_substr(trim($this->search), 0, 80);
        $products = Product::where('status', 'published')->with(['media' => fn ($query) => $query->where('media_type', 'image')->where('section', 'preview')->orderBy('sort_order')])
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->when(in_array($this->type, ['ea', 'indicator'], true), fn ($query) => $query->where('type', $this->type))->orderBy('name')->paginate(12);

        return view('livewire.catalog.products', compact('products'))->layout('components.layouts.app', ['title' => 'Produk MT5 — Trader Garuda', 'description' => 'Kenali EA dan indikator MetaTrader 5, cara pakai, hasil pengujian yang tersedia dan risiko penggunaannya.', 'canonical' => '/produk']);
    }
}
