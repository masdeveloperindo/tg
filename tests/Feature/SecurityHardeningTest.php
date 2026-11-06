<?php

namespace Tests\Feature;

use App\Actions\Products\ProductAssets;
use App\Models\ProductMedia;
use App\Models\User;
use App\Support\PrivateStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\DomainRecords;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function assertSecurityHeaders($response): void
    {
        $response->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'none'; object-src 'none'; base-uri 'self'")
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }

    public function test_public_private_error_and_api_responses_reject_framing_and_referrer_leaks(): void
    {
        $this->withoutVite();
        foreach (['/', '/login', '/register', '/member', '/health', '/missing-security-fixture'] as $path) {
            $this->assertSecurityHeaders($this->get($path));
        }
        $this->assertSecurityHeaders($this->postJson('/api/v1/licenses/verify', []));
        Route::get('/security-fixture-error', fn () => throw new \RuntimeException('SYNTHETIC-PRIVATE-DETAIL'));
        $response = $this->get('/security-fixture-error')->assertStatus(500)->assertDontSee('SYNTHETIC-PRIVATE-DETAIL');
        $this->assertSecurityHeaders($response);
        $this->app->instance('env', 'local');
        try {
            $this->assertSecurityHeaders($this->post('/logout')->assertStatus(419));
        } finally {
            $this->app->instance('env', 'testing');
        }
    }

    public function test_real_livewire_update_carries_the_same_security_headers(): void
    {
        $this->withoutVite();
        $response = $this->get('/register')->assertOk();
        preg_match('/wire:snapshot="([^"]+)"/', $response->getContent(), $matches);
        $snapshot = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $update = $this->withHeader('X-Livewire', 'true')->postJson(Livewire::getUpdateUri(), [
            'components' => [['snapshot' => $snapshot, 'updates' => ['name' => 'Synthetic name'], 'calls' => []]],
        ])->assertOk();
        $this->assertSecurityHeaders($update);
    }

    public function test_private_storage_cannot_be_a_configured_public_link_target_even_before_link_creation(): void
    {
        Storage::fake('local');
        $root = PrivateStorage::root();
        config(['filesystems.links' => [public_path('synthetic-not-created') => $root]]);
        $this->expectException(ValidationException::class);
        PrivateStorage::root();
    }

    public function test_actual_public_symlink_exposing_part_of_private_storage_is_rejected(): void
    {
        Storage::fake('local');
        Storage::disk('local')->makeDirectory('nested');
        $link = public_path('security-link-'.Str::uuid());
        symlink(Storage::disk('local')->path('nested'), $link);
        try {
            $this->expectException(ValidationException::class);
            PrivateStorage::root();
        } finally {
            unlink($link);
        }
    }

    public function test_public_disk_is_not_accepted_as_private_storage(): void
    {
        $root = storage_path('app/public/security-fixture-'.Str::uuid());
        config(['filesystems.disks.local.root' => $root]);
        Storage::purge('local');
        try {
            $this->expectException(ValidationException::class);
            PrivateStorage::root();
        } finally {
            File::deleteDirectory($root);
        }
    }

    public function test_public_and_admin_image_routes_reject_symlink_escape_and_accept_normal_private_images(): void
    {
        $this->withoutVite();
        Storage::fake('local');
        $records = new DomainRecords;
        $product = $records->product(['status' => 'published']);
        $path = app(ProductAssets::class)->image(UploadedFile::fake()->image('synthetic.png'));
        $media = ProductMedia::findOrFail($records->create('product_media', ['product_id' => $product, 'disk_path' => $path]));
        $this->get(route('produk.media', $media))->assertOk()->assertHeader('Content-Type', 'image/webp');
        $admin = User::findOrFail($records->user(['role' => 'admin', 'totp_secret_enc' => Crypt::encryptString('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ'), 'totp_enabled_at' => now()]));
        $this->actingAs($admin)->withSession(['auth' => ['totp_user_id' => $admin->id, 'totp_verified_at' => now()->timestamp, 'admin_last_activity' => now()->timestamp, 'reauthenticated_at' => now()->timestamp]]);
        $this->get(route('admin.produk.media', $media))->assertOk();
        $outside = storage_path('framework/testing/security-outside-'.Str::uuid().'.txt');
        file_put_contents($outside, 'SYNTHETIC-PRIVATE-BYTES');
        Storage::disk('local')->delete($path);
        symlink($outside, Storage::disk('local')->path($path));
        try {
            foreach (['produk.media', 'admin.produk.media'] as $route) {
                $this->get(route($route, $media))->assertNotFound()->assertDontSee('SYNTHETIC-PRIVATE-BYTES');
            }
            $media->forceFill(['disk_path' => '../security-outside.txt'])->save();
            $this->get(route('admin.produk.media', $media))->assertNotFound();
        } finally {
            unlink(Storage::disk('local')->path($path));
            unlink($outside);
        }
    }
}
