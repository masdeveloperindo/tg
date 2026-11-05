<?php

namespace App\Livewire\Orders;

use App\Actions\Orders\Checkout;
use App\Models\CheckoutQuote;
use App\Models\License;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

class CheckoutPage extends Component
{
    #[Locked]
    public int $productId;

    #[Locked]
    public ?string $quoteId = null;

    #[Locked]
    public ?int $renewalId = null;

    public string $mt5_account = '';

    public string $mt5_server = '';

    public string $account_type = '';

    public string $payment_method_id = '';

    public ?string $unavailable = null;

    public function mount(string $slug): void
    {
        $this->productId = Product::where('slug', $slug)->firstOrFail()->id;
        $intent = request()->query('intent');
        if ($intent !== null) {
            abort_unless(is_string($intent) && Str::isUuid($intent), 404);
            $quote = CheckoutQuote::where('user_id', Auth::id())->where('product_id', $this->productId)->findOrFail($intent);
            $this->quoteId = $quote->id;
            $this->renewalId = $quote->renewal_license_id;
            $existing = Order::where('user_id', Auth::id())->where('idempotency_key', $quote->id)->first();
            if ($existing) {
                $this->redirectRoute('pesanan.kode', ['kode' => $existing->code]);

                return;
            }
            abort_unless(app(Checkout::class)->available(Product::findOrFail($this->productId)), 404);
            if ($this->renewalId) {
                $license = License::where('user_id', Auth::id())->findOrFail($this->renewalId);
                $this->mt5_account = $license->mt5_account;
                $this->mt5_server = $license->mt5_server;
                $this->account_type = $license->account_type->value;
            }

            return;
        }
        $id = request()->query('renewal');
        abort_if($id !== null && (! is_string($id) || ! ctype_digit($id)), 404);
        $this->renewalId = $id === null ? null : (int) $id;
        if ($this->renewalId) {
            $license = License::where('user_id', Auth::id())->where('product_id', $this->productId)->findOrFail($this->renewalId);
            $this->mt5_account = $license->mt5_account;
            $this->mt5_server = $license->mt5_server;
            $this->account_type = $license->account_type->value;
        }
        $this->refreshQuote();
    }

    public function refreshQuote(): void
    {
        $this->resetValidation();
        $this->quoteId = null;
        $this->payment_method_id = '';
        try {
            $quote = app(Checkout::class)->quote(Product::findOrFail($this->productId), $this->renewalId);
            $this->quoteId = $quote->id;
            $this->unavailable = null;
            $this->redirectRoute('checkout.slug', ['slug' => $quote->product_id === $this->productId ? Product::findOrFail($this->productId)->slug : abort(404), 'intent' => $quote->id]);
        } catch (ValidationException) {
            $this->unavailable = 'Pemesanan belum tersedia atau perpanjangan belum dapat dilakukan. Hubungi admin untuk informasi lebih lanjut.';
        }
    }

    public function submit(Checkout $checkout): void
    {
        abort_if($this->quoteId === null, 404);
        $order = $checkout->create($this->quoteId, ['mt5_account' => $this->mt5_account, 'mt5_server' => $this->mt5_server, 'account_type' => $this->account_type, 'payment_method_id' => $this->payment_method_id]);
        $this->redirectRoute('pesanan.kode', ['kode' => $order->code]);
    }

    public function render()
    {
        $product = Product::findOrFail($this->productId);
        $quote = $this->quoteId ? CheckoutQuote::where('user_id', Auth::id())->findOrFail($this->quoteId) : null;

        return view('livewire.orders.checkout', compact('product', 'quote'))->layout('components.layouts.app', ['title' => 'Checkout — Trader Garuda', 'noindex' => true]);
    }
}
