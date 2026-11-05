<?php

namespace Tests\Feature;

use App\Actions\Orders\CalendarPricing;
use App\Actions\Orders\Checkout;
use App\Actions\Orders\CheckoutSettings;
use App\Livewire\Auth\Register;
use App\Livewire\Orders\CheckoutPage;
use App\Models\License;
use App\Models\Order;
use App\Models\User;
use App\Support\OrderDisplay;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CheckoutRecords as Fixtures;
use Tests\Support\DomainRecords;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private DomainRecords $records;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->records = new DomainRecords;
        Storage::fake('local');
        $session = app('session')->driver();
        $session->start();
        request()->setLaravelSession($session);
        app()->rebinding('request', static function ($app, Request $request): void {
            if (! $request->hasSession()) {
                $request->setLaravelSession($app['session']->driver());
            }
        });
        config(['tradergaruda.license_sales_enabled' => true, 'tradergaruda.indicator_sales_enabled' => false]);
        Fixtures::configure();
        $this->travelTo(CarbonImmutable::parse('2026-11-05 10:00:00', 'Asia/Jakarta'));
    }

    private function client(): User
    {
        $user = User::findOrFail($this->records->user());
        $this->actingAs($user);

        return $user;
    }

    private function invalid(\Closure $work, string $field): void
    {
        try {
            $work();
            $this->fail('Expected validation rejection.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey($field, $error->errors());
        }
    }

    public static function months(): array
    {
        return [[1, '250000.00'], [2, '237500.00'], [6, '187500.00'], [12, '112500.00']];
    }

    #[DataProvider('months')]
    public function test_calendar_price_is_linear_and_ends_exclusively_at_new_year_wib(int $month, string $expected): void
    {
        $product = Fixtures::product();
        $quote = app(CalendarPricing::class)->calculate($product, Fixtures::rules(), CarbonImmutable::create(2026, $month, 1, 0, 0, 0, 'Asia/Jakarta'));
        $this->assertSame($expected, $quote['amount_idr']);
        $this->assertSame('2026-12-31 17:00:00', $quote['coverage_expires_at']->format('Y-m-d H:i:s'));
        $this->assertSame('2027-01-01 00:00:00', $quote['coverage_expires_at']->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'));
    }

    public function test_rounding_half_up_modes_floor_and_large_decimal_values_use_no_float(): void
    {
        $product = Fixtures::product(['base_price_idr' => '250500.00', 'minimum_price_idr' => '0.00']);
        $engine = app(CalendarPricing::class);
        $at = CarbonImmutable::parse('2026-01-01', 'Asia/Jakarta');
        foreach (['nearest' => '251000.00', 'up' => '251000.00', 'down' => '250000.00'] as $mode => $expected) {
            $this->assertSame($expected, $engine->calculate($product, Fixtures::rules(['rounding_increment_idr' => '1000.00', 'rounding_mode' => $mode]), $at)['amount_idr']);
        }
        $product->forceFill(['minimum_price_idr' => '150001.01'])->save();
        $this->assertSame('151000.00', $engine->calculate($product, Fixtures::rules(['rounding_increment_idr' => '1000', 'rounding_mode' => 'down']), CarbonImmutable::parse('2026-12-01', 'Asia/Jakarta'))['amount_idr']);
        $product->forceFill(['base_price_idr' => '999999999999999999.99', 'minimum_price_idr' => '0.00'])->save();
        $this->assertSame('999999999999999999.99', $engine->calculate($product, Fixtures::rules(['rounding_increment_idr' => '0.01']), $at)['amount_idr']);
        $this->invalid(fn () => $engine->calculate($product, Fixtures::rules(['rounding_increment_idr' => '1000.00']), $at), 'pricing');
    }

    public function test_h30_active_renewal_targets_next_year_at_base_price_and_expired_renewal_targets_current_month(): void
    {
        $user = $this->client();
        $product = Fixtures::product();
        $license = License::findOrFail($this->records->create('licenses', ['user_id' => $user->id, 'product_id' => $product->id, 'coverage_year' => 2026, 'expires_at' => CalendarPricing::end(2026), 'status' => 'active']));
        $engine = app(CalendarPricing::class);
        $this->invalid(fn () => $engine->calculate($product, Fixtures::rules(), CarbonImmutable::parse('2026-11-30 23:59:59', 'Asia/Jakarta'), $license), 'renewal');
        $active = $engine->calculate($product, Fixtures::rules(), CarbonImmutable::parse('2026-12-01', 'Asia/Jakarta'), $license);
        $this->assertSame(2027, $active['coverage_year']);
        $this->assertSame('250000.00', $active['amount_idr']);
        $expired = $engine->calculate($product, Fixtures::rules(), CarbonImmutable::parse('2027-02-01', 'Asia/Jakarta'), $license);
        $this->assertSame(2027, $expired['coverage_year']);
        $this->assertSame('237500.00', $expired['amount_idr']);
        $license->forceFill(['status' => 'revoked'])->save();
        $this->invalid(fn () => $engine->calculate($product, Fixtures::rules(), CarbonImmutable::parse('2027-02-01', 'Asia/Jakarta'), $license), 'renewal');
    }

    public function test_month_and_year_boundaries_use_jakarta_and_cap_quote_lifetime(): void
    {
        $this->client();
        $product = Fixtures::product();
        $this->travelTo(CarbonImmutable::parse('2026-12-31 16:59:00', 'UTC'));
        $quote = app(Checkout::class)->quote($product);
        $this->assertSame(2026, $quote->coverage_year);
        $this->assertSame('112500.00', $quote->amount_idr);
        $this->assertSame('2026-12-31 17:00:00', $quote->expires_at->format('Y-m-d H:i:s'));
        $this->travelTo(CarbonImmutable::parse('2026-12-31 17:00:00', 'UTC'));
        $this->invalid(fn () => app(Checkout::class)->create($quote->id, Fixtures::input()), 'checkout');
        $new = app(Checkout::class)->quote($product);
        $this->assertSame(2027, $new->coverage_year);
        $this->assertSame('250000.00', $new->amount_idr);
    }

    public function test_quote_snapshot_is_immutable_encrypted_and_order_ignores_client_price_and_status(): void
    {
        $user = $this->client();
        $product = Fixtures::product();
        $service = app(Checkout::class);
        $quote = $service->quote($product);
        $locked = $quote->amount_idr;
        $product->forceFill(['base_price_idr' => '900000.00'])->save();
        Fixtures::setting('checkout.payment_methods', [...Fixtures::methods(), ['id' => 'other', 'label' => 'Other', 'account_holder' => 'Other', 'account_number' => 'OTHER-NOT-A-BANK']]);
        $order = $service->create($quote->id, Fixtures::input(['price_locked_idr' => '0.01', 'status' => 'paid', 'coverage_year' => 2099, 'user_id' => 999, 'product_id' => 999]));
        $this->assertSame($locked, $order->price_locked_idr);
        $this->assertSame('waiting', $order->status->value);
        $this->assertSame($user->id, $order->user_id);
        $this->assertSame(2026, $order->coverage_year);
        $this->assertStringNotContainsString('SYNTHETIC-NOT-A-BANK', DB::table('checkout_quotes')->where('id', $quote->id)->value('payment_details'));
        $this->assertStringNotContainsString('payment_details', $quote->toJson());
        $this->assertDatabaseCount('licenses', 0);
        $this->expectException(\LogicException::class);
        $quote->forceFill(['amount_idr' => '0.01'])->save();
    }

    public function test_same_intent_retries_return_same_order_even_after_expiry_and_product_disabled(): void
    {
        $this->client();
        $product = Fixtures::product();
        $service = app(Checkout::class);
        $quote = $service->quote($product);
        $first = $service->create($quote->id, Fixtures::input());
        $this->travel(2)->days();
        $product->forceFill(['sale_enabled' => false])->save();
        $retry = $service->create($quote->id, Fixtures::input(['mt5_account' => '999']));
        $this->assertSame($first->id, $retry->id);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('activity_logs', 1);
        $this->assertSame('expired', OrderDisplay::status($retry));
    }

    public function test_quote_owner_and_order_owner_cannot_be_forged(): void
    {
        $owner = $this->client();
        $product = Fixtures::product();
        $service = app(Checkout::class);
        $quote = $service->quote($product);
        $order = $service->create($quote->id, Fixtures::input());
        $this->client();
        $this->get(route('pesanan.kode', $order->code))->assertNotFound();
        $this->get(route('member.pesanan'))->assertOk()->assertDontSee($order->code);
        $this->expectException(ModelNotFoundException::class);
        $service->create($quote->id, Fixtures::input());
    }

    public function test_waiting_order_contains_no_key_and_clipboard_and_chat_cannot_mark_paid(): void
    {
        $this->client();
        $product = Fixtures::product();
        $quote = app(Checkout::class)->quote($product);
        $order = app(Checkout::class)->create($quote->id, Fixtures::input());
        $this->get(route('pesanan.kode', $order->code))->assertOk()->assertSee('Menunggu Pembayaran')->assertSee('SYNTHETIC-NOT-A-BANK')->assertSee('https://t.me/tg_fixture_contact', false)->assertDontSee('?text=', false)->assertDontSee('Salin Key')->assertDontSee('key_ciphertext');
        $text = OrderDisplay::clipboard($order);
        $this->assertStringContainsString($order->code, $text);
        $this->assertStringContainsString('31 Desember 2026', $text);
        $this->assertStringContainsString('akun MT5 12345678', $text);
        $this->assertSame('waiting', $order->fresh()->status->value);
        $this->assertDatabaseCount('licenses', 0);
    }

    public function test_status_check_resumes_original_intent_without_minting_a_new_quote_after_lost_response(): void
    {
        $this->client();
        $product = Fixtures::product();
        $service = app(Checkout::class);
        $quote = $service->quote($product);
        $url = route('checkout.slug', ['slug' => $product->slug, 'intent' => $quote->id]);
        $this->get($url)->assertOk()->assertSee('Buat Pesanan');
        $this->assertDatabaseCount('checkout_quotes', 1);
        $order = $service->create($quote->id, Fixtures::input());
        $product->forceFill(['status' => 'archived'])->save();
        $this->travel(2)->days();
        $this->get($url)->assertRedirect(route('pesanan.kode', $order->code));
        $this->assertDatabaseCount('checkout_quotes', 1);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_member_renewal_links_only_show_eligible_owned_licenses(): void
    {
        $user = $this->client();
        $product = Fixtures::product();
        $this->travelTo(CarbonImmutable::parse('2026-12-01', 'Asia/Jakarta'));
        $id = $this->records->create('licenses', ['user_id' => $user->id, 'product_id' => $product->id, 'status' => 'active', 'coverage_year' => 2026, 'expires_at' => CalendarPricing::end(2026)]);
        $this->get('/member')->assertOk()->assertSee('Perpanjang')->assertSee('renewal='.$id, false);
        $this->client();
        $this->get('/member')->assertOk()->assertDontSee('renewal='.$id, false);
    }

    public function test_telegram_accepts_only_username_or_exact_personal_url_without_query_payload(): void
    {
        foreach (['@valid_fixture', 'https://t.me/valid_fixture'] as $value) {
            Fixtures::setting('telegram_admin_username', $value);
            $this->assertSame('https://t.me/valid_fixture', app(CheckoutSettings::class)->telegram());
        }
        foreach (['https://evil.invalid/valid_fixture', 'https://t.me/valid_fixture?text=send', 'https://t.me/+invite', 'abc', 'user/name', 'valid_fixture?start=bot'] as $value) {
            Fixtures::setting('telegram_admin_username', $value);
            $this->assertNull(app(CheckoutSettings::class)->telegram());
        }
    }

    public function test_renewal_uses_stored_account_keeps_key_and_rejects_forged_account(): void
    {
        $user = $this->client();
        $product = Fixtures::product();
        $this->travelTo(CarbonImmutable::parse('2026-12-05', 'Asia/Jakarta'));
        $license = License::findOrFail($this->records->create('licenses', ['user_id' => $user->id, 'product_id' => $product->id, 'status' => 'active', 'coverage_year' => 2026, 'expires_at' => CalendarPricing::end(2026)]));
        $before = $license->getRawOriginal('key_ciphertext');
        $quote = app(Checkout::class)->quote($product, $license->id);
        $this->invalid(fn () => app(Checkout::class)->create($quote->id, Fixtures::input()), 'mt5_account');
        $order = app(Checkout::class)->create($quote->id, Fixtures::input(['mt5_account' => $license->mt5_account, 'mt5_server' => $license->mt5_server, 'account_type' => $license->account_type->value]));
        $this->assertSame('renewal', $order->order_type->value);
        $this->assertSame($license->id, $order->renewal_license_id);
        $this->assertSame(2027, $order->coverage_year);
        $this->assertSame($before, $license->fresh()->getRawOriginal('key_ciphertext'));
    }

    public function test_default_poc_gate_missing_owner_settings_and_missing_file_fail_closed(): void
    {
        $this->client();
        $product = Fixtures::product();
        config(['tradergaruda.license_sales_enabled' => false]);
        $this->get(route('checkout.slug', $product->slug))->assertNotFound();
        config(['tradergaruda.license_sales_enabled' => true]);
        DB::table('settings')->delete();
        $this->get(route('checkout.slug', $product->slug))->assertOk()->assertSee('Pemesanan belum tersedia')->assertDontSee('Buat Pesanan');
        $product->versions()->update(['is_active' => false]);
        $this->get(route('checkout.slug', $product->slug))->assertNotFound();
    }

    public function test_checkout_livewire_submission_and_private_headers(): void
    {
        $this->client();
        $product = Fixtures::product();
        $quote = app(Checkout::class)->quote($product);
        $component = Livewire::withQueryParams(['intent' => $quote->id])->test(CheckoutPage::class, ['slug' => $product->slug]);
        foreach (Fixtures::input() as $key => $value) {
            $component->set($key, $value);
        }
        $component->call('submit')->assertHasNoErrors()->assertRedirect(route('pesanan.kode', Order::sole()->code));
        $response = $this->get(route('pesanan.kode', Order::sole()->code))->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
    }

    public function test_new_registration_returns_to_the_original_checkout_without_reentering_profile(): void
    {
        $product = Fixtures::product();
        $this->get(route('checkout.slug', $product->slug))->assertRedirect(route('login'));
        Livewire::test(Register::class)->set('name', 'Synthetic new client')->set('email', 'new-checkout@example.invalid')->set('password', 'test-only-new-password')->set('password_confirmation', 'test-only-new-password')->set('contact_channel', 'telegram')->set('contact_value', 'synthetic_client')->call('submit')->assertHasNoErrors()->assertRedirect(route('checkout.slug', $product->slug));
    }

    public function test_expired_quote_rejects_order_and_invalid_method_never_mutates_database(): void
    {
        $this->client();
        $product = Fixtures::product();
        $quote = app(Checkout::class)->quote($product);
        $this->invalid(fn () => app(Checkout::class)->create($quote->id, Fixtures::input(['payment_method_id' => 'forged'])), 'payment_method_id');
        $this->travelTo(CarbonImmutable::instance($quote->expires_at));
        $this->invalid(fn () => app(Checkout::class)->create($quote->id, Fixtures::input()), 'checkout');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('activity_logs', 0);
    }
}
