<?php

namespace App\Livewire;

use App\Actions\Content\Visibility;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Home extends Component
{
    public function render(): View
    {
        return view('livewire.home', ['testimonials' => Visibility::testimonials()->whereNull('product_id')->orderBy('sort_order')->limit(6)->get(), 'products' => Product::where('status', 'published')->with(['media' => fn ($query) => $query->where('media_type', 'image')->where('section', 'preview')->orderBy('sort_order')])->latest('id')->limit(6)->get()])
            ->layout('components.layouts.app', ['title' => 'Trader Garuda — Perangkat MetaTrader 5', 'canonical' => '/']);
    }
}
