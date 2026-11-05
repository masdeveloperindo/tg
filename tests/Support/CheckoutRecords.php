<?php

namespace Tests\Support;

use App\Models\Product;
use App\Models\Setting;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CheckoutRecords
{
    public static function rules(array $changes = []): array
    {
        return array_replace(['monthly_discount_pct' => '5', 'rounding_increment_idr' => '1.00', 'rounding_mode' => 'nearest', 'quote_minutes' => 15, 'payment_minutes' => 1440], $changes);
    }

    public static function methods(): array
    {
        return [['id' => 'fixture-bank', 'label' => 'Synthetic bank', 'account_holder' => 'Synthetic owner', 'account_number' => 'SYNTHETIC-NOT-A-BANK', 'instructions' => 'Synthetic fixture only; do not transfer.']];
    }

    public static function setting(string $key, mixed $value): void
    {
        $row = Setting::where('key', $key)->first() ?? new Setting;
        $row->forceFill(['key' => $key, 'value_encrypted' => is_array($value) ? json_encode($value, JSON_THROW_ON_ERROR) : $value, 'is_secret' => true])->save();
    }

    public static function configure(): void
    {
        self::setting('checkout.pricing', self::rules());
        self::setting('checkout.payment_methods', self::methods());
        self::setting('telegram_admin_username', '@tg_fixture_contact');
    }

    public static function product(array $changes = []): Product
    {
        $records = new DomainRecords;
        $product = Product::findOrFail($records->product(array_replace(['name' => 'Synthetic checkout EA', 'slug' => 'checkout-'.strtolower(Str::random(10)), 'type' => 'ea', 'status' => 'published', 'sale_enabled' => true, 'base_price_idr' => '250000.00', 'minimum_price_idr' => '100000.00'], $changes)));
        $path = 'products/versions/'.Str::uuid().'.ex5';
        $bytes = "EX5\0".str_repeat("\0\xff", 100);
        Storage::disk('local')->put($path, $bytes);
        $records->create('product_versions', ['product_id' => $product->id, 'version' => '1.0', 'is_active' => true, 'path' => $path, 'bytes' => strlen($bytes), 'sha256' => hash('sha256', $bytes), 'released_at' => now()]);

        return $product;
    }

    public static function input(array $changes = []): array
    {
        return array_replace(['mt5_account' => '12345678', 'mt5_server' => 'Fixture-MT5-Demo', 'account_type' => 'demo', 'payment_method_id' => 'fixture-bank'], $changes);
    }
}
