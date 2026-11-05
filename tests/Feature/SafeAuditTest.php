<?php

namespace Tests\Feature;

use App\Logging\SafeLog;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Monolog\Handler\TestHandler;
use Tests\Support\DomainRecords;
use Tests\TestCase;

class SafeAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_ids_are_server_generated_and_correlate_with_safe_logs(): void
    {
        $this->withoutVite();
        $handler = new TestHandler;
        Log::channel()->getLogger()->setHandlers([$handler]);
        $incoming = (string) Str::uuid();
        $response = $this->withHeader('X-Request-ID', $incoming)->get('/')->assertOk();
        $id = $response->headers->get('X-Request-ID');
        $this->assertTrue(Str::isUuid($id));
        $this->assertNotSame($incoming, $id);
        $records = $handler->getRecords();
        $this->assertSame($id, end($records)->context['request_id']);
        $this->assertSame(200, end($records)->context['status']);
    }

    public function test_configured_logger_discards_messages_nested_objects_and_secret_values(): void
    {
        $handler = new TestHandler;
        $logger = Log::channel('single')->getLogger();
        $logger->setHandlers([$handler]);
        $id = (string) Str::uuid();
        $logger->error('SECRET-password-key-bank', [
            'request_id' => $id, 'actor_id' => 12, 'status' => 500,
            'exception' => new \RuntimeException('SECRET-password-key-bank'),
            'nested' => ['password' => 'SECRET-password-key-bank'],
            'subject_id' => 'SECRET-password-key-bank',
        ]);
        $record = $handler->getRecords()[0];
        $this->assertSame('application.event', $record->message);
        $this->assertSame(['request_id' => $id, 'actor_id' => 12, 'status' => 500], $record->context);
        $this->assertSame([], $record->extra);
        foreach (config('logging.channels') as $channel) {
            $this->assertContains(SafeLog::class, $channel['tap']);
        }
    }

    public function test_debug_errors_are_generic_in_html_and_json_and_keep_http_status(): void
    {
        $this->withoutVite();
        config(['app.debug' => true]);
        Route::middleware('web')->get('/__test/error', fn () => throw new \RuntimeException('SECRET SQL credential bank key'));
        $html = $this->get('/__test/error')->assertStatus(500)->assertDontSee('SECRET')->assertSee('Referensi permintaan');
        $this->assertTrue(Str::isUuid($html->headers->get('X-Request-ID')));
        $html->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertTrue($html->headers->hasCacheControlDirective('no-store'));
        $json = $this->getJson('/__test/error')->assertStatus(500)->assertJsonPath('code', 'HTTP_500')->assertDontSee('SECRET');
        $json->assertJsonPath('request_id', $json->headers->get('X-Request-ID'));
        foreach ([403, 404, 419, 429, 503] as $status) {
            Route::middleware('web')->get('/__test/status-'.$status, fn () => abort($status, 'SECRET server internals', ['Retry-After' => '60']));
            $this->getJson('/__test/status-'.$status)->assertStatus($status)->assertDontSee('SECRET')->assertJsonPath('code', 'HTTP_'.$status);
        }
    }

    public function test_audit_only_persists_typed_approved_metadata_and_stable_correlation(): void
    {
        $records = new DomainRecords;
        $actor = User::findOrFail($records->user());
        Audit::record($actor, 'auth.registered', $actor, ['role' => 'client', 'status' => 'active', 'password' => 'SECRET', 'nested' => ['key' => 'SECRET']]);
        Audit::record($actor, 'auth.password_changed', $actor, ['sessions_revoked' => 'SECRET', 'must_change_password' => false]);
        $rows = DB::table('activity_logs')->orderBy('id')->get();
        $this->assertSame($rows[0]->request_id, $rows[1]->request_id);
        $this->assertEquals(['result' => 'succeeded', 'role' => 'client', 'status' => 'active'], json_decode($rows[0]->summary_json, true));
        $this->assertStringNotContainsString('SECRET', $rows->toJson());
        $this->assertSame($actor->id, $rows[0]->actor_id);
    }

    public function test_audit_failure_rolls_back_with_the_business_transaction(): void
    {
        $records = new DomainRecords;
        $actor = User::findOrFail($records->user());
        try {
            DB::transaction(function () use ($actor): void {
                $actor->forceFill(['name' => 'Changed'])->save();
                Audit::record($actor, 'unsupported.action', $actor, []);
            });
            $this->fail('Unknown audit action must fail closed.');
        } catch (\InvalidArgumentException) {
            $this->assertNotSame('Changed', $actor->fresh()->name);
            $this->assertSame(0, DB::table('activity_logs')->count());
        }
    }

    public function test_retention_is_dry_run_by_default_and_preserves_audit_and_boundary_rows(): void
    {
        $this->travelTo(now()->startOfSecond());
        $records = new DomainRecords;
        $audit = $records->create('activity_logs', ['created_at' => now()->subYears(6)]);
        $old = $records->create('license_logs', ['created_at' => now()->subDays(91), 'ip_enc' => 'encrypted-fixture']);
        $ip = $records->create('license_logs', ['created_at' => now()->subDays(31), 'ip_enc' => 'encrypted-fixture']);
        $boundary = $records->create('license_logs', ['created_at' => now()->subDays(30), 'ip_enc' => 'encrypted-fixture']);
        $this->artisan('tg:prune-license-logs')->assertSuccessful();
        $this->assertSame(3, DB::table('license_logs')->count());
        $this->artisan('tg:prune-license-logs --apply')->assertSuccessful();
        $this->assertDatabaseMissing('license_logs', ['id' => $old]);
        $this->assertDatabaseHas('license_logs', ['id' => $ip, 'ip_enc' => null]);
        $this->assertDatabaseHas('license_logs', ['id' => $boundary, 'ip_enc' => 'encrypted-fixture']);
        $this->assertDatabaseHas('activity_logs', ['id' => $audit]);
        $this->artisan('tg:prune-license-logs --apply')->assertSuccessful();
        $this->assertSame(2, DB::table('license_logs')->count());
    }
}
