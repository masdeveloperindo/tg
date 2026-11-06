<?php

namespace Tests\Feature;

use App\Actions\Content\PublishContent;
use App\Livewire\Content\Editor;
use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\RebateWorkflow;
use Tests\TestCase;

class ContentManagementTest extends TestCase
{
    use RebateWorkflow,RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareRebates();
    }

    private function article(array $changes = []): Article
    {
        return app(PublishContent::class)->save('edukasi', null, array_replace(['title' => 'Synthetic education', 'slug' => 'synthetic-education', 'body' => '<h2>Lesson</h2><p>Learn safely.</p>', 'category' => 'cara_trading', 'visibility' => 'public', 'status' => 'published', 'published_at' => '', 'seo_title' => 'Synthetic SEO', 'seo_description' => 'A real synthetic test description.'], $changes));
    }

    private function invalid(\Closure $work, string $key): void
    {
        try {
            $work();
            $this->fail();
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($key, $e->errors());
        }
    }

    public function test_article_drafts_schedules_categories_and_seo_only_expose_eligible_content(): void
    {
        $a = $this->article();
        $this->article(['slug' => 'draft-only', 'title' => 'Private draft title', 'status' => 'draft']);
        $this->article(['slug' => 'future-only', 'title' => 'Future test title', 'published_at' => now('Asia/Jakarta')->addDay()->format('Y-m-d\TH:i')]);
        $this->get('/edukasi')->assertOk()->assertSee('Synthetic education')->assertDontSee('Private draft title')->assertDontSee('Future test title');
        $this->get('/edukasi/'.$a->slug)->assertOk()->assertSee('Synthetic SEO')->assertSee('rel="canonical"', false)->assertSee('Pahami risikonya');
        $this->get('/edukasi/draft-only')->assertNotFound();
        $this->get('/edukasi/future-only')->assertNotFound();
        $this->get('/edukasi/kategori/cara-trading')->assertOk()->assertSee('Synthetic education');
        $this->get('/edukasi/kategori/unknown')->assertNotFound();
        $this->get('/sitemap.xml')->assertOk()->assertSee('/edukasi/synthetic-education')->assertDontSee('draft-only')->assertDontSee('future-only');
    }

    public function test_private_article_and_images_require_active_client_including_ib_only_clients(): void
    {
        $a = $this->article(['visibility' => 'client']);
        $image = app(PublishContent::class)->articleImage($a->id, UploadedFile::fake()->image('inside.png', 80, 40), 'Synthetic private illustration');
        auth()->logout();
        $this->get('/edukasi')->assertDontSee('Synthetic education');
        $this->get('/edukasi/'.$a->slug)->assertRedirect(route('login'));
        $url = route('content.image', ['type' => 'article', 'id' => $image->id]);
        $this->get($url)->assertNotFound();
        $this->actingAs($this->buyer);
        $this->assertDatabaseCount('licenses', 0);
        $this->get('/edukasi/'.$a->slug)->assertOk()->assertSee('noindex, nofollow')->assertHeader('Cache-Control', 'no-store, private')->assertSee('Synthetic private illustration');
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->buyer->forceFill(['status' => 'disabled'])->save();
        $this->get('/edukasi/'.$a->slug)->assertForbidden();
        $this->get($url)->assertNotFound();
        $this->get('/sitemap.xml')->assertDontSee('/edukasi/synthetic-education');
    }

    public function test_cover_reencode_sanitization_and_unpublish_close_all_image_endpoints(): void
    {
        $data = ['title' => 'Safe article', 'slug' => 'safe-article', 'body' => '<p>Safe</p><script>BAD-SCRIPT</script><img src="https://evil.invalid/tracker">', 'category' => 'signal', 'visibility' => 'public', 'status' => 'published', 'published_at' => '', 'seo_title' => '', 'seo_description' => ''];
        $a = app(PublishContent::class)->save('edukasi', null, $data, UploadedFile::fake()->image('cover.jpg', 200, 100));
        $this->assertMatchesRegularExpression('/\.webp$/', $a->cover_path);
        $this->assertStringNotContainsString('<script', $a->body);
        $this->get('/edukasi/safe-article')->assertOk()->assertDontSee('BAD-SCRIPT')->assertDontSee('evil.invalid')->assertSee('og:image');
        $image = route('content.image', ['type' => 'cover', 'id' => $a->id]);
        $this->get($image)->assertOk();
        app(PublishContent::class)->save('edukasi', $a->id, [...$data, 'status' => 'draft']);
        $this->get($image)->assertNotFound();
    }

    public function test_guide_steps_images_reorder_and_inactive_visibility_are_real(): void
    {
        $service = app(PublishContent::class);
        $data = ['title' => 'Synthetic installation', 'slug' => 'synthetic-install', 'summary' => '<p>Install safely.</p>', 'product_id' => '', 'is_active' => true];
        $this->invalid(fn () => $service->save('panduan', null, $data), 'steps');
        $g = $service->save('panduan', null, $data, null, [['body' => '<p>Step one</p>', 'alt_text' => 'Synthetic terminal image'], ['body' => '<p>Step two</p>', 'alt_text' => '']], [0 => UploadedFile::fake()->image('step.png', 80, 40)]);
        $steps = $g->steps()->orderBy('step_no')->get();
        $this->get('/panduan/synthetic-install')->assertOk()->assertSee('Step one')->assertSee('Step two');
        $url = route('content.image', ['type' => 'step', 'id' => $steps[0]->id]);
        $this->get($url)->assertOk();
        $service->save('panduan', $g->id, [...$data, 'is_active' => false], null, [['id' => $steps[1]->id, 'body' => 'Step two moved', 'alt_text' => ''], ['id' => $steps[0]->id, 'body' => 'Step one moved', 'alt_text' => 'Synthetic terminal image']]);
        $this->get('/panduan/synthetic-install')->assertNotFound();
        $this->get($url)->assertNotFound();
        $this->assertSame('Step two moved', $g->steps()->orderBy('step_no')->first()->body);
    }

    public function test_testimonials_require_real_source_consent_confirmation_schedule_and_never_publish_consent(): void
    {
        $service = app(PublishContent::class);
        $data = ['display_name' => 'Synthetic reviewer', 'content' => 'Synthetic statement only.', 'product_id' => '', 'rating' => '4', 'source' => 'SYNTHETIC-SOURCE-PRIVATE', 'consent_record' => 'SYNTHETIC-CONSENT-PRIVATE', 'consent_confirmed' => false, 'sort_order' => '0', 'is_visible' => true, 'published_at' => ''];
        $this->invalid(fn () => $service->save('testimoni', null, $data), 'consent_confirmed');
        $t = $service->save('testimoni', null, [...$data, 'consent_confirmed' => true]);
        $this->get('/')->assertOk()->assertSee('Synthetic reviewer')->assertDontSee('SYNTHETIC-CONSENT-PRIVATE')->assertDontSee('SYNTHETIC-SOURCE-PRIVATE');
        $service->save('testimoni', $t->id, [...$data, 'is_visible' => false]);
        $this->get('/')->assertDontSee('Synthetic reviewer');
        $this->assertStringNotContainsString('SYNTHETIC-CONSENT-PRIVATE', DB::table('activity_logs')->get()->toJson());
    }

    public function test_announcement_audience_and_exact_schedule_expiry_use_same_member_page(): void
    {
        $this->freezeTime();
        $service = app(PublishContent::class);
        $base = ['title' => 'Synthetic announcement', 'body' => '<p>Synthetic scheduled message.</p>', 'audience' => 'ib_clients', 'is_active' => true, 'starts_at' => now('Asia/Jakarta')->subMinute()->format('Y-m-d\TH:i'), 'ends_at' => now('Asia/Jakarta')->addMinutes(2)->format('Y-m-d\TH:i')];
        $a = $service->save('pengumuman', null, $base);
        $this->actingAs($this->buyer);
        $this->get('/member')->assertDontSee('Synthetic announcement');
        $this->account();
        $this->actingAs($this->buyer);
        $this->get('/member')->assertSee('Synthetic announcement');
        $this->get('/')->assertDontSee('Synthetic scheduled message.');
        $this->travelTo($a->ends_at);
        $this->get('/member')->assertDontSee('Synthetic announcement');
    }

    public function test_faq_and_static_legal_pages_have_honest_unpublished_state_and_only_published_sitemap(): void
    {
        $this->get('/kebijakan-privasi')->assertOk()->assertSee('Informasi belum dipublikasikan')->assertSee('noindex, nofollow');
        $service = app(PublishContent::class);
        $page = $service->save('halaman', null, ['slug' => 'kebijakan-privasi', 'title' => 'Synthetic policy fixture', 'body' => '<p>Owner test policy.</p>', 'seo_title' => 'Policy SEO', 'seo_description' => 'A test description.', 'is_published' => true]);
        $this->get('/kebijakan-privasi')->assertOk()->assertSee('Owner test policy.')->assertSee('Policy SEO')->assertDontSee('Informasi belum dipublikasikan');
        $this->get('/sitemap.xml')->assertOk()->assertSee('/kebijakan-privasi');
        $service->save('faq', null, ['product_id' => '', 'question' => 'Synthetic question?', 'answer' => '<p>Synthetic answer.</p>', 'sort_order' => '0', 'is_active' => true]);
        $this->get('/faq')->assertOk()->assertSee('Synthetic question?')->assertSee('Synthetic answer.');
        $this->invalid(fn () => $service->save('halaman', $page->id, ['slug' => 'admin', 'title' => 'Invalid', 'body' => 'Invalid text', 'is_published' => true]), 'slug');
    }

    public function test_admin_content_routes_create_edit_and_server_ownership_cannot_be_bypassed(): void
    {
        $a = $this->article();
        $this->get('/admin/edukasi')->assertOk();
        $this->get('/admin/edukasi/create')->assertOk()->assertSee('Editor konten');
        $this->get('/admin/edukasi/'.$a->id.'/edit')->assertOk()->assertSee('Synthetic education');
        $this->actingAs($this->buyer);
        $this->get('/admin/edukasi')->assertForbidden();
        Livewire::test(Editor::class, ['kind' => 'edukasi'])->assertForbidden();
        $this->admin();
        session()->put('auth.reauthenticated_at', now()->subMinutes(11)->timestamp);
        Livewire::test(Editor::class, ['kind' => 'edukasi'])->call('open', $a->id)->call('save')->assertForbidden();
    }
}
