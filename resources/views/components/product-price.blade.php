@props(['product'])
@php($sale = app(\App\Actions\Orders\SaleInfo::class)->current($product))
@if($sale)<p><strong>{{ \App\Support\OrderDisplay::money($sale['amount_idr']) }}</strong> · Akses sampai 31 Desember {{ $sale['coverage_year'] }}</p>@endif
@if($sale && $sale['available'])<a class="button button-lime" href="{{ route('checkout.slug', $product->slug) }}">Sewa</a>@else<p class="auth-hint">Penjualan belum dibuka.</p>@endif
