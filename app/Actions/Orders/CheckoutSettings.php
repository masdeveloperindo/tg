<?php

namespace App\Actions\Orders;

use App\Models\Setting;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class CheckoutSettings
{
    public function value(string $key): ?string
    {
        return Setting::where('key', $key)->first()?->value_encrypted;
    }

    public function pricing(): array
    {
        return app(CalendarPricing::class)->rules($this->json('checkout.pricing'));
    }

    public function methods(): array
    {
        $data = Validator::make(['methods' => $this->json('checkout.payment_methods')], [
            'methods' => 'required|array|min:1|max:10', 'methods.*.id' => ['required', 'string', 'distinct', 'regex:/^[a-z0-9-]{1,64}$/'],
            'methods.*.label' => 'required|string|max:100', 'methods.*.account_holder' => 'required|string|max:120', 'methods.*.account_number' => ['required', 'string', 'regex:/^[A-Za-z0-9 -]{3,64}$/'],
            'methods.*.instructions' => 'nullable|string|max:500',
        ])->validate();

        return array_map(fn ($row) => array_intersect_key($row, array_flip(['id', 'label', 'account_holder', 'account_number', 'instructions'])), $data['methods']);
    }

    public function telegram(): ?string
    {
        $value = trim((string) ($this->value('telegram_admin_username') ?? config('tradergaruda.telegram_username')));
        if (str_starts_with($value, 'https://t.me/')) {
            if (parse_url($value, PHP_URL_QUERY) || parse_url($value, PHP_URL_FRAGMENT) || parse_url($value, PHP_URL_USER) || parse_url($value, PHP_URL_PORT)) {
                return null;
            }$value = substr($value, 13);
        }
        $value = ltrim($value, '@');

        return preg_match('/^[A-Za-z0-9_]{5,32}$/D', $value) ? 'https://t.me/'.$value : null;
    }

    private function json(string $key): array
    {
        try {
            $value = json_decode((string) $this->value($key), true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $value = null;
        }
        if (! is_array($value)) {
            throw ValidationException::withMessages(['checkout' => 'Pemesanan belum tersedia. Silakan kembali setelah informasi pembayaran siap.']);
        }

        return $value;
    }
}
