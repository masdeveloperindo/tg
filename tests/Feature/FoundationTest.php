<?php

namespace Tests\Feature;

use App\Livewire\Home;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_home_displays_an_honest_empty_state(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Trader Garuda')
            ->assertSeeInOrder(['Pemesanan', 'belum dibuka.'])
            ->assertSee('Belum ada produk tersedia');
    }

    public function test_home_is_a_renderable_livewire_component(): void
    {
        Livewire::test(Home::class)
            ->assertOk()
            ->assertSeeInOrder(['Pemesanan', 'belum dibuka.'])
            ->assertSee('Belum ada produk tersedia');
    }

    public function test_foundation_uses_the_required_safe_runtime_configuration(): void
    {
        $this->assertSame('UTC', config('app.timezone'));
        $this->assertSame('Asia/Jakarta', config('tradergaruda.display_timezone'));
        $this->assertFalse(config('tradergaruda.license_sales_enabled'));
        $this->assertFalse(config('tradergaruda.indicator_sales_enabled'));
        $this->assertSame('private', config('filesystems.disks.local.visibility'));
        $this->assertFalse(config('filesystems.disks.local.serve'));
        $this->assertSame('sync', config('queue.default'));
        $this->assertSame('argon2id', config('hashing.driver'));
    }

    #[DataProvider('unimplementedEndpoints')]
    public function test_unimplemented_sensitive_endpoints_are_not_exposed(string $method, string $uri): void
    {
        $this->call($method, $uri)->assertNotFound();
    }

    public static function unimplementedEndpoints(): array
    {
        return [
            'license verification' => ['POST', '/api/v1/licenses/verify'],
            'license deactivation' => ['POST', '/api/v1/licenses/deactivate'],
            'health details' => ['GET', '/health'],
            'default framework health' => ['GET', '/up'],
            'private file' => ['GET', '/storage/private/example.ex5'],
            'Telegram webhook' => ['POST', '/telegram/webhook'],
        ];
    }
}
