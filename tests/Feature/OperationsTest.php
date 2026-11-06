<?php

namespace Tests\Feature;

use App\Actions\Operations\Configuration;
use App\Actions\Operations\DetectAnomalies;
use App\Actions\Operations\ReviewAnomaly;
use App\Actions\Operations\Tasks;
use App\Livewire\Fulfillment\AdminOrders;
use App\Livewire\Operations\Anomalies;
use App\Models\ActivityLog;
use App\Models\License;
use App\Models\LicenseFlag;
use App\Models\LicenseLog;
use App\Models\Order;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\CheckoutRecords;
use Tests\Support\DomainRecords;
use Tests\Support\RebateWorkflow;
use Tests\TestCase;

class OperationsTest extends TestCase
{
    use RebateWorkflow,RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareRebates();
    }

    private function license(): License
    {
        $r = new DomainRecords;
        $l = License::findOrFail($r->create('licenses', ['user_id' => $this->buyer->id]));
        $r->create('product_versions', ['product_id' => $l->product_id, 'version' => '1.0.0', 'released_at' => now()]);

        return $l;
    }

    private function logs(License $l, int $number = 6, string $result = 'account_mismatch', string $version = '1.0.0'): void
    {
        for ($i = 0; $i < $number; $i++) {
            (new LicenseLog)->forceFill(['license_id' => $l->id, 'event' => 'verify', 'result' => $result, 'product_version' => $version, 'ip_hash' => hash('sha256', 'synthetic-ip'), 'request_id' => (string) Str::uuid()])->save();
        }
    }

    public function test_anomaly_threshold_dedup_cooldown_and_unknown_key_never_automatically_suspend_a_license(): void
    {
        $l = $this->license();
        $this->logs($l, 5);
        $scanner = app(DetectAnomalies::class);
        $this->assertSame(0, $scanner->run());
        $this->logs($l, 1);
        $this->assertSame(1, $scanner->run());
        $this->assertSame(0, $scanner->run());
        $this->assertSame('active', $l->fresh()->status->value);
        $f = LicenseFlag::sole();
        $this->assertSame('account_mismatch', $f->rule);
        $this->assertStringNotContainsString('synthetic-ip', $f->evidence_summary);
        app(ReviewAnomaly::class)->decide($f->id, 'ignored', 'Synthetic mobile/VPS context checked');
        $scanner->run();
        $this->assertSame('ignored', $f->fresh()->status->value);
        $this->travel(2)->hours();
        $this->logs($l);
        $this->assertSame(1, $scanner->run());
        $this->assertDatabaseCount('license_flags', 2);
        $this->assertSame('active', $l->fresh()->status->value);
        (new LicenseLog)->forceFill(['license_id' => null, 'event' => 'verify', 'result' => 'invalid', 'request_id' => (string) Str::uuid()])->save();
        $this->assertSame(0, $scanner->run());
    }

    public function test_unknown_version_and_many_ips_flag_counts_without_exposing_raw_ip(): void
    {
        $l = $this->license();
        for ($i = 0; $i < 6; $i++) {
            (new LicenseLog)->forceFill(['license_id' => $l->id, 'event' => 'verify', 'result' => 'valid', 'product_version' => '9.9.9', 'ip_hash' => hash('sha256', 'different-'.$i), 'request_id' => (string) Str::uuid()])->save();
        }
        $this->assertSame(2, app(DetectAnomalies::class)->run());
        $this->assertEqualsCanonicalizing(['unknown_version', 'multiple_ips'], LicenseFlag::pluck('rule')->all());
        $this->get('/admin/anomali')->assertOk()->assertDontSee(hash('sha256', 'different-1'));
    }

    public function test_manual_review_and_suspend_require_reauthentication_and_reason(): void
    {
        $l = $this->license();
        $this->logs($l);
        app(DetectAnomalies::class)->run();
        $f = LicenseFlag::sole();
        $service = app(ReviewAnomaly::class);
        try {
            $service->decide($f->id, 'reviewed', '');
            $this->fail();
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('note', $e->errors());
        }
        $service->decide($f->id, 'reviewed', 'Synthetic review pending');
        $this->assertSame('active', $l->fresh()->status->value);
        $service->decide($f->id, 'suspended', 'Synthetic manual suspension decision');
        $service->decide($f->id, 'suspended', 'Retry same terminal result');
        $this->assertSame('suspended', $l->fresh()->status->value);
        $this->assertSame(2, ActivityLog::where('action', 'anomaly.reviewed')->count());
        $this->actingAs($this->buyer);
        Livewire::test(Anomalies::class)->assertForbidden();
        $this->get('/admin/anomali/'.$f->id)->assertForbidden();
        $this->admin();
        session()->put('auth.reauthenticated_at', now()->subMinutes(11)->timestamp);
        Livewire::test(Anomalies::class, ['id' => $f->id])->call('open', 'ignored')->assertForbidden();
    }

    public function test_expiry_scheduler_is_idempotent_audited_and_preserves_paid_or_future_orders(): void
    {
        $r = new DomainRecords;
        $past = Order::findOrFail($r->create('orders', ['expires_at' => now()->subSecond()]));
        $future = Order::findOrFail($r->create('orders', ['expires_at' => now()->addHour()]));
        $paid = Order::findOrFail($r->create('orders', ['status' => 'paid', 'expires_at' => now()->subSecond()]));
        $this->artisan('tg:expire-orders')->assertSuccessful();
        $this->artisan('tg:expire-orders')->assertSuccessful();
        $this->assertSame('expired', $past->fresh()->status->value);
        $this->assertSame('waiting', $future->fresh()->status->value);
        $this->assertSame('paid', $paid->fresh()->status->value);
        $audit = ActivityLog::where('action', 'order.expired')->sole();
        $this->assertNull($audit->actor_id);
        $this->assertSame($past->id, $audit->subject_id);
    }

    public function test_health_requires_bearer_returns_minimal_dependencies_and_never_starts_browser_session(): void
    {
        config(['tradergaruda.health_token' => str_repeat('T', 40)]);
        $this->get('/health')->assertNotFound();
        $this->get('/health?token='.str_repeat('T', 40))->assertNotFound();
        $this->withHeader('Authorization', 'Bearer wrong')->get('/health')->assertNotFound();
        $response = $this->withHeader('Authorization', 'Bearer '.str_repeat('T', 40))->get('/health')->assertOk()->assertExactJson(['status' => 'ok'])->assertHeader('Cache-Control', 'no-store, private');
        $this->assertFalse($response->headers->has('Set-Cookie'));
        Cache::shouldReceive('put')->once()->andThrow(new \RuntimeException('Synthetic private dependency details'));
        Cache::shouldReceive('forget')->once()->andReturn(true);
        $this->get('/health')->assertStatus(503)->assertExactJson(['status' => 'unavailable'])->assertDontSee('Synthetic private dependency details');
    }

    public function test_scheduler_jobs_registered_with_overlap_protection_without_host_operations(): void
    {
        $schedule = app(Schedule::class);
        $events = collect($schedule->events());
        $this->assertCount(5, $events);
        $this->assertEqualsCanonicalizing(['expire-unpaid-orders', 'license-anomalies', 'prune-api-nonces', 'prune-license-telemetry', 'reconcile-rebates'], $events->pluck('description')->all());
        foreach ($events as $event) {
            $this->assertTrue($event->withoutOverlapping);
            $this->assertTrue($event->onOneServer);
        }
        $this->artisan('schedule:list')->assertSuccessful();
        $this->artisan('tg:detect-license-anomalies')->assertSuccessful();
        $this->artisan('tg:prune-api-nonces', ['--apply' => true])->assertSuccessful();
        $this->artisan('tg:prune-license-logs', ['--apply' => true])->assertSuccessful();
    }

    public function test_task_dashboard_counts_link_to_server_filtered_jobs_including_paid_without_license(): void
    {
        $r = new DomainRecords;
        $pending = Order::findOrFail($r->create('orders', ['expires_at' => now()->addHour()]));
        $r->create('orders', ['status' => 'paid']);
        $r->create('orders', ['expires_at' => now()->subHour()]);
        $cards = Tasks::cards();
        $this->assertSame(1, $cards[0]['count']);
        $this->assertSame(1, $cards[6]['count']);
        $this->get('/admin')->assertOk()->assertSee('Tugas Hari Ini')->assertSee('Lunas tanpa lisensi');
        Livewire::test(AdminOrders::class)->set('task', 'waiting_payment')->assertViewHas('orders', fn ($o) => $o->count() === 1 && $o->first()->id === $pending->id);
        Livewire::test(AdminOrders::class)->set('task', 'missing_license')->assertViewHas('orders', fn ($o) => $o->count() === 1);
    }

    public function test_configuration_is_encrypted_validated_and_does_not_enable_ea_sales(): void
    {
        $pricing = CheckoutRecords::rules();
        $methods = CheckoutRecords::methods();
        app(Configuration::class)->save($pricing, $methods, 'fixture_admin', Configuration::ANOMALY, ['title' => 'Synthetic Site', 'description' => 'Synthetic SEO description']);
        $raw = DB::table('settings')->where('key','checkout.payment_methods')->value('value_encrypted');
        $this->assertStringNotContainsString('SYNTHETIC-NOT-A-BANK',$raw);
        $this->assertFalse(config('tradergaruda.license_sales_enabled'));
        $this->assertSame($pricing,Configuration::json('checkout.pricing'));
        $this->assertStringNotContainsString('SYNTHETIC-NOT-A-BANK',DB::table('activity_logs')->get()->toJson());
    }
}
