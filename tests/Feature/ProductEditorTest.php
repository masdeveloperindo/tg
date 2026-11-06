<?php

namespace Tests\Feature;

use App\Actions\Products\ManageProducts;
use App\Livewire\Products\Editor;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\User;
use App\Support\SafeHtml;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Support\DomainRecords;
use Tests\TestCase;

class ProductEditorTest extends TestCase
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

    }

    private function admin(): User
    {
        $admin = User::findOrFail($this->records->user(['role' => 'admin', 'totp_secret_enc' => Crypt::encryptString('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ'), 'totp_enabled_at' => now()]));
        $this->actingAs($admin);
        session()->put(['auth.totp_user_id' => $admin->id, 'auth.totp_verified_at' => now()->timestamp, 'auth.admin_last_activity' => now()->timestamp, 'auth.password_confirmed_at' => now()->timestamp, 'auth.reauthenticated_at' => now()->timestamp]);

        return $admin;
    }

    public function test_price_mutation_rechecks_reauthentication_after_editor_mount(): void
    {
        $this->admin();
        $product = $this->product();
        $editor = Livewire::test(Editor::class, ['id' => $product->id]);
        $before = DB::table('activity_logs')->count();
        session()->put('auth.reauthenticated_at', now()->subSeconds(601)->timestamp);
        $editor->set('form.base_price_idr', '120.00')->call('save')->assertForbidden();
        $this->assertSame('100.10', $product->fresh()->base_price_idr);
        $this->assertSame($before, DB::table('activity_logs')->count());
        session()->put('auth.reauthenticated_at', now()->timestamp);
        Livewire::test(Editor::class, ['id' => $product->id])->set('form.base_price_idr', '120.00')->call('save')->assertHasNoErrors();
        $this->assertSame('120.00', $product->fresh()->base_price_idr);
    }

    public function test_new_product_price_requires_reauthentication_and_cannot_be_submitted_without_it(): void
    {
        $this->admin();
        session()->forget('auth.reauthenticated_at');
        Livewire::test(Editor::class)->set('form', $this->info())->call('save')->assertForbidden();
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_nonfinancial_content_save_does_not_need_an_unnecessary_price_reauthentication(): void
    {
        $this->admin();
        $product = $this->product();
        session()->forget('auth.reauthenticated_at');
        $saved = app(ManageProducts::class)->save($product->id, $this->info(['name' => 'Updated synthetic information']));
        $this->assertSame('Updated synthetic information', $saved->name);
        $this->assertSame('100.10', $saved->base_price_idr);
    }

    private function info(array $changes = []): array
    {
        return array_replace(['name' => 'Synthetic EA', 'slug' => 'synthetic-ea', 'type' => 'ea', 'description' => '<p>Real information must be supplied by owner.</p>', 'base_price_idr' => '100.10', 'minimum_price_idr' => '10.00', 'seo_title' => '', 'seo_description' => ''], $changes);
    }

    private function product(): Product
    {
        return app(ManageProducts::class)->save(null, $this->info());
    }

    private function performance(array $changes = []): array
    {
        return array_replace(['test_type' => 'backtest', 'account_environment' => 'demo', 'starts_on' => '2026-01-01', 'ends_on' => '2026-01-31', 'symbol' => 'TEST', 'timeframe' => 'H1', 'methodology_note' => 'Synthetic test fixture only', 'net_profit_pct' => '-1.1250', 'win_rate_pct' => '', 'initial_capital' => ''], $changes);
    }

    private function invalid(\Closure $work, string $key): void
    {
        try {
            $work();
            $this->fail('Expected validation rejection.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey($key, $error->errors());
        }
    }

    public function test_product_creation_sanitizes_description_keeps_decimals_and_ignores_sale_status_injection(): void
    {
        $this->admin();
        $product = app(ManageProducts::class)->save(null, $this->info(['sale_enabled' => true, 'status' => 'published', 'description' => '<p onclick="x()">Text</p><script>secret()</script><a href="javascript:alert(1)">link</a>']));
        $this->assertSame('draft', $product->status->value);
        $this->assertFalse($product->sale_enabled);
        $this->assertSame('100.10', $product->base_price_idr);
        $this->assertSame('<p>Text</p><a>link</a>', $product->description);
        $this->assertDatabaseHas('activity_logs', ['action' => 'product.created', 'subject_id' => $product->id, 'subject_type' => Product::class]);
    }

    public function test_product_prices_slug_and_published_slug_are_validated(): void
    {
        $this->admin();
        $service = app(ManageProducts::class);
        foreach ([['slug' => '../bad'], ['minimum_price_idr' => '101'], ['base_price_idr' => '1e3'], ['base_price_idr' => 0.1]] as $changes) {
            $this->invalid(fn () => $service->save(null, $this->info($changes)), array_key_first($changes));
        }
        $product = $this->product();
        $service->status($product->id, 'published');
        $this->invalid(fn () => $service->save($product->id, $this->info(['slug' => 'new-slug'])), 'slug');
        $service->status($product->id, 'archived');
        $this->assertDatabaseHas('products', ['id' => $product->id, 'status' => 'archived', 'sale_enabled' => false]);
    }

    public function test_empty_description_cannot_be_published_and_unknown_status_is_rejected(): void
    {
        $this->admin();
        $product = app(ManageProducts::class)->save(null, $this->info(['description' => '<script>x()</script>']));
        $this->invalid(fn () => app(ManageProducts::class)->status($product->id, 'published'), 'product');
        $this->assertSame('draft', $product->fresh()->status->value);
    }

    public function test_guest_client_unverified_and_disabled_admin_cannot_open_editor_or_mutate(): void
    {
        $this->get(route('admin.produk'))->assertRedirect(route('login'));
        $client = User::findOrFail($this->records->user());
        $this->actingAs($client)->get(route('admin.produk.create'))->assertForbidden();
        Livewire::actingAs($client)->test(Editor::class)->assertForbidden();
        $admin = $this->admin();
        session()->forget('auth.totp_user_id');
        $this->get(route('admin.produk.create'))->assertRedirect(route('admin.2fa.challenge'));
        $admin->forceFill(['status' => 'disabled'])->save();
        Livewire::actingAs($admin)->test(Editor::class)->assertForbidden();
        $this->assertDatabaseCount('products', 0);
    }

    public function test_livewire_editor_creates_product_and_locked_id_cannot_be_forged(): void
    {
        $this->admin();
        Livewire::test(Editor::class)->set('form', $this->info())->call('save')->assertHasNoErrors()->assertRedirect(route('admin.produk.id.edit', Product::sole()->id));
        $this->assertFalse(Product::sole()->sale_enabled);
        $this->get(route('admin.produk.id.edit', Product::sole()->id))->assertOk()->assertSee('role="tablist"', false)->assertSee('panel-performance', false);
        $this->expectException(CannotUpdateLockedPropertyException::class);
        Livewire::test(Editor::class, ['id' => Product::sole()->id])->set('productId', 999999);
    }

    public function test_livewire_actions_reauthorize_after_admin_session_expires(): void
    {
        $this->admin();
        $product = $this->product();
        $component = Livewire::test(Editor::class, ['id' => $product->id]);
        session()->put('auth.admin_last_activity', now()->subMinutes(31)->timestamp);
        $component->call('save')->assertForbidden();
    }

    public function test_image_upload_is_reencoded_private_and_client_filename_cannot_select_path(): void
    {
        $this->admin();
        $product = $this->product();
        app(ManageProducts::class)->media($product->id, ['section' => 'preview', 'alt_text' => 'Synthetic screenshot', 'sort_order' => 0], UploadedFile::fake()->image('private-name.png', 32, 20));
        $media = $product->media()->sole();
        $this->assertStringEndsWith('.webp', $media->disk_path);
        $this->assertStringNotContainsString('private-name', $media->disk_path);
        $bytes = Storage::disk('local')->get($media->disk_path);
        $this->assertSame('image/webp', getimagesizefromstring($bytes)['mime']);
        $this->get(route('admin.produk.media', $media->id))->assertOk()->assertHeader('Content-Type', 'image/webp');
        $client = User::findOrFail($this->records->user());
        $this->actingAs($client)->get(route('admin.produk.media', $media->id))->assertForbidden();
    }

    public function test_disguised_image_svg_and_malicious_video_hosts_are_rejected(): void
    {
        $this->admin();
        $product = $this->product();
        $service = app(ManageProducts::class);
        $meta = ['section' => 'preview', 'alt_text' => 'Test', 'sort_order' => 0];
        $this->invalid(fn () => $service->media($product->id, $meta, UploadedFile::fake()->createWithContent('fake.png', '<?php echo "bad";')), 'image');
        $this->invalid(fn () => $service->media($product->id, $meta, UploadedFile::fake()->createWithContent('image.svg', '<svg/>')), 'image');
        foreach (['https://youtube.com.evil.invalid/watch', 'https://user@youtube.com/watch', 'https://127.0.0.1/video', 'https://youtube.com:444/watch'] as $url) {
            $this->invalid(fn () => $service->media($product->id, [...$meta, 'external_url' => $url], null), 'external_url');
        }
        $service->media($product->id, [...$meta, 'external_url' => 'https://www.youtube.com/watch?v=fixture'], null);
        $this->assertSame(1, $product->media()->count());
    }

    public function test_ex5_upload_checksum_and_only_one_active_version_are_enforced(): void
    {
        $this->admin();
        $product = $this->product();
        $service = app(ManageProducts::class);
        $bytes = "EX5\0".str_repeat("\0\xff", 100);
        foreach (['1.0', '2.0'] as $version) {
            $service->version($product->id, UploadedFile::fake()->createWithContent('fixture.ex5', $bytes), ['version' => $version]);
        }
        $versions = $product->versions()->orderBy('id')->get();
        $this->assertSame(hash('sha256', $bytes), $versions[0]->sha256);
        $this->assertSame(strlen($bytes), $versions[0]->bytes);
        $service->activateVersion($product->id, $versions[0]->id);
        $service->activateVersion($product->id, $versions[1]->id);
        $this->assertSame(1, $product->versions()->where('is_active', true)->count());
        $this->assertTrue($versions[1]->fresh()->is_active);
        $this->assertFalse($product->fresh()->sale_enabled);
        $this->get(route('admin.produk.version', $versions[0]->id))->assertOk()->assertDownload('1.0.ex5');
        $this->invalid(fn () => $service->version($product->id, UploadedFile::fake()->createWithContent('fixture.ex5', $bytes), ['version' => '1.0']), 'version');
    }

    public function test_script_disguised_as_ex5_is_rejected_and_database_failure_removes_new_file(): void
    {
        $this->admin();
        $product = $this->product();
        $service = app(ManageProducts::class);
        $this->invalid(fn () => $service->version($product->id, UploadedFile::fake()->createWithContent('evil.ex5', '<?php '.str_repeat('x', 100)), ['version' => '1.0']), 'versionFile');
        $this->assertSame([], Storage::disk('local')->allFiles());
        ProductMedia::creating(fn () => throw new \RuntimeException('Synthetic persistence failure'));
        try {
            $service->media($product->id, ['section' => 'preview', 'alt_text' => 'Test', 'sort_order' => 0], UploadedFile::fake()->image('fixture.png'));
            $this->fail();
        } catch (\RuntimeException $error) {
            $this->assertSame('Synthetic persistence failure', $error->getMessage());
        } finally {
            ProductMedia::flushEventListeners();
        }
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertDatabaseCount('product_media', 0);
    }

    public function test_faq_and_tutorial_are_sanitized_and_can_be_updated_without_duplicate_rows(): void
    {
        $this->admin();
        $product = $this->product();
        $service = app(ManageProducts::class);
        $service->faq($product->id, ['question' => 'Question', 'answer' => '<p>Answer</p><script>bad()</script>', 'sort_order' => 1]);
        $faq = $product->faqs()->sole();
        $service->faq($product->id, ['id' => $faq->id, 'question' => 'Updated', 'answer' => '<p>Safe</p>', 'sort_order' => 0]);
        $this->assertSame(1, $product->faqs()->count());
        $this->assertSame('<p>Safe</p>', $faq->fresh()->answer);
        $service->tutorial($product->id, [['body' => '<p>Install</p><iframe src="https://evil.invalid"></iframe>', 'media_id' => null]]);
        $this->assertSame('<p>Install</p>', $product->guides()->sole()->steps()->sole()->body);
        $service->tutorial($product->id, [['body' => '<p>Updated step</p>', 'media_id' => null]]);
        $this->assertSame(1, $product->guides()->count());
        $this->assertSame(1, $product->guides()->sole()->steps()->count());
    }

    public function test_performance_preserves_optional_null_values_and_records_editor_and_change_hashes(): void
    {
        $admin = $this->admin();
        $product = $this->product();
        $service = app(ManageProducts::class);
        $service->performance($product->id, $this->performance());
        $performance = $product->performances()->sole();
        $this->assertNull($performance->win_rate_pct);
        $this->assertNull($performance->initial_capital);
        $this->assertSame('-1.1250', $performance->net_profit_pct);
        $this->assertSame($admin->id, $performance->updated_by);
        $service->performance($product->id, $this->performance(['id' => $performance->id, 'net_profit_pct' => '1.2500']));
        $this->assertSame(1, $product->performances()->count());
        $audit = DB::table('activity_logs')->where('action', 'product.performance_changed')->latest('id')->first();
        $meta = json_decode($audit->summary_json, true);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $meta['after_sha256']);
        $this->assertNotSame($meta['before_sha256'], $meta['after_sha256']);
        $this->assertStringNotContainsString('Synthetic test fixture', $audit->summary_json);
        $this->invalid(fn () => $service->performance($product->id, $this->performance(['ends_on' => '2025-01-01'])), 'ends_on');
        $this->invalid(fn () => $service->performance($product->id, $this->performance(['win_rate_pct' => '101'])), 'win_rate_pct');
    }

    public function test_cross_product_resource_ids_cannot_be_changed_activated_or_removed(): void
    {
        $this->admin();
        $product = $this->product();
        $other = app(ManageProducts::class)->save(null, $this->info(['slug' => 'other-product']));
        $service = app(ManageProducts::class);
        $service->faq($other->id, ['question' => 'Other', 'answer' => '<p>Other</p>', 'sort_order' => 0]);
        $faq = $other->faqs()->sole();
        $this->invalid(fn () => $service->faq($product->id, ['id' => $faq->id, 'question' => 'Forged', 'answer' => 'bad', 'sort_order' => 0]), 'id');
        $this->expectException(ModelNotFoundException::class);
        $service->remove($product->id, 'faqs', $faq->id);
    }

    public function test_media_and_version_metadata_can_be_edited_without_replacing_the_source(): void
    {
        $this->admin();
        $product = $this->product();
        $service = app(ManageProducts::class);
        $service->media($product->id, ['section' => 'preview', 'alt_text' => 'Before', 'sort_order' => 0], UploadedFile::fake()->image('fixture.png'));
        $media = $product->media()->sole();
        $path = $media->disk_path;
        $service->mediaDetails($product->id, $media->id, ['section' => 'tutorial', 'alt_text' => 'After', 'caption' => 'Caption', 'sort_order' => 2]);
        $this->assertSame('After', $media->fresh()->alt_text);
        $this->assertSame($path, $media->fresh()->disk_path);
        $service->version($product->id, UploadedFile::fake()->createWithContent('fixture.ex5', "EX5\0".str_repeat("\0\xff", 100)), ['version' => '1.0']);
        $version = $product->versions()->sole();
        $service->versionDetails($product->id, $version->id, ['changelog' => 'Updated description', 'min_supported_version' => '1.0']);
        $this->assertSame('Updated description', $version->fresh()->changelog);
        $this->assertSame($version->path, $version->fresh()->path);
        Livewire::test(Editor::class, ['id' => $product->id])->call('editMedia', $media->id)->assertSet('mediaForm.id', $media->id)->assertHasNoErrors();
        Livewire::test(Editor::class, ['id' => $product->id])->call('editVersion', $version->id)->assertSet('versionForm.id', $version->id)->assertHasNoErrors();
    }

    public function test_performance_pdf_and_equity_are_private_and_media_removal_is_scoped_and_audited(): void
    {
        $this->admin();
        $product = $this->product();
        $service = app(ManageProducts::class);
        $service->performance($product->id, $this->performance(), UploadedFile::fake()->createWithContent('proof.pdf', "%PDF-1.4\n% synthetic fixture\n%%EOF"), UploadedFile::fake()->image('equity.png'));
        $performance = $product->performances()->sole();
        $this->assertNotNull($performance->report_path);
        $this->assertNotNull($performance->equity_image_path);
        $this->get(route('admin.produk.report', $performance->id))->assertOk()->assertDownload('laporan-'.$performance->id.'.pdf');
        $service->remove($product->id, 'performances', $performance->id);
        $this->assertSame(0, $product->performances()->count());
        Storage::disk('local')->assertExists($performance->report_path);
        $this->assertDatabaseHas('activity_logs', ['action' => 'product.content_removed', 'subject_id' => $product->id]);
    }

    public function test_temporary_upload_and_preview_endpoints_require_admin_authentication(): void
    {
        $this->assertContains('private.response', config('livewire.temporary_file_upload.middleware'));
        $this->assertContains('max:20480', config('livewire.temporary_file_upload.rules'));
        $this->post(route('livewire.upload-file'), [])->assertRedirect(route('login'));
        $this->get(route('livewire.preview-file', ['filename' => 'untrusted.png']))->assertRedirect(route('login'));
    }

    public function test_sanitizer_removes_xss_namespaces_comments_styles_and_event_handlers(): void
    {
        $html = SafeHtml::clean('<svg><script>x()</script></svg><math>bad</math><!-- secret --><p style="color:red" onmouseover="x()">Safe <strong>bold</strong></p><a href="https://example.invalid" target="x">Good</a>');
        $this->assertStringNotContainsString('script', $html);
        $this->assertStringNotContainsString('bad', $html);
        $this->assertStringNotContainsString('secret', $html);
        $this->assertStringNotContainsString('style', $html);
        $this->assertStringContainsString('<strong>bold</strong>', $html);
        $this->assertStringContainsString('rel="nofollow noopener noreferrer"', $html);
    }
}
