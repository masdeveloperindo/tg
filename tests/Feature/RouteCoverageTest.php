<?php

namespace Tests\Feature;

use App\Actions\Content\Types;
use App\Actions\Operations\Configuration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Support\CheckoutRecords;
use Tests\Support\RebateWorkflow;
use Tests\TestCase;

class RouteCoverageTest extends TestCase
{
    use RebateWorkflow,RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareRebates();
    }

    public function test_every_prd_path_has_a_concrete_handler_and_server_access_rules(): void
    {
        $handle = fopen(base_path('technical/ROUTES_MATRIX.csv'), 'r');
        $header = fgetcsv($handle, escape: '');
        $count = 0;
        while (($row = fgetcsv($handle, escape: '')) !== false) {
            $expected = array_combine($header, $row);
            $found = collect(Route::getRoutes())->first(fn ($r) => '/'.ltrim($r->uri(), '/') === $expected['path'] && in_array($expected['method'], $r->methods(), true));
            $this->assertNotNull($found, $expected['path']);
            if ($expected['access'] === 'admin') {
                $this->assertContains('admin.2fa', $found->gatherMiddleware(), $expected['path']);
            }if ($expected['access'] === 'client_or_owner') {
                $this->assertContains('client', $found->gatherMiddleware(), $expected['path']);
            }$count++;
        }fclose($handle);
        $this->assertSame(71, $count);
    }

    public function test_cms_create_and_list_routes_are_operational_not_placeholder_pages(): void
    {
        foreach (array_keys(Types::MODELS) as $kind) {
            $this->get('/admin/'.$kind)->assertOk()->assertSee('Kelola '.$kind);
            if ($kind !== 'halaman') {
                $this->get('/admin/'.$kind.'/create')->assertOk()->assertSee('Editor konten');
            }
        }
        foreach (['/admin/anomali', '/admin/log', '/admin/pengaturan', '/admin/akun-trading'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->actingAs($this->buyer);
        foreach (['/admin/edukasi', '/admin/panduan', '/admin/log', '/admin/pengaturan', '/admin/akun-trading'] as $url) {
            $this->get($url)->assertForbidden();
        }
    }

    public function test_sitemap_xml_is_valid_canonical_and_robots_is_dynamic_not_a_static_bypass(): void
    {
        config(['app.url' => 'https://canonical.example.invalid']);
        $response = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $dom = new \DOMDocument;
        $this->assertTrue($dom->loadXML($response->getContent()));
        $this->assertStringContainsString('https://canonical.example.invalid/edukasi', $response->getContent());
        $this->assertStringNotContainsString('/admin', $response->getContent());
        $this->assertFalse(is_file(public_path('robots.txt')));
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /admin')->assertSee('https://canonical.example.invalid/sitemap.xml');
    }

    public function test_outbound_routes_never_accept_a_caller_destination_or_send_messages(): void
    {
        $this->get('/go/telegram?url=https://evil.example.invalid')->assertNotFound();
        CheckoutRecords::setting('telegram_admin_username', 'fixture_admin');
        $this->get('/go/telegram?text=ignored&url=https://evil.example.invalid')->assertRedirect('https://t.me/fixture_admin');
        $broker = $this->broker();
        $this->get('/go/broker/'.$broker->id.'?url=https://evil.example.invalid')->assertRedirect($broker->ib_link);
        $this->assertDatabaseCount('outbound_clicks', 2);
    }

    public function test_unreadable_optional_seo_configuration_falls_back_without_breaking_safe_error_layouts(): void
    {
        DB::table('settings')->insert(['key' => 'public.site', 'value_encrypted' => 'SYNTHETIC-INVALID-CIPHERTEXT', 'is_secret' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->assertSame([], Configuration::json('public.site'));
        $this->get('/')->assertOk()->assertDontSee('SYNTHETIC-INVALID-CIPHERTEXT');
        $this->get('/missing-safe-error')->assertNotFound()->assertDontSee('SYNTHETIC-INVALID-CIPHERTEXT')->assertDontSee('DecryptException');
    }
}
