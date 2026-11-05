<?php

namespace Tests\Feature;

use App\Livewire\Catalog\Brokers;
use App\Livewire\Catalog\Detail;
use App\Livewire\Catalog\Products;
use App\Models\Product;
use App\Support\PublicContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\DomainRecords;
use Tests\TestCase;

class PublishedCatalogTest extends TestCase
{
    use RefreshDatabase;

    private DomainRecords $records;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->records = new DomainRecords;
        Storage::fake('local');
    }

    private function product(array $changes = []): Product
    {
        return Product::findOrFail($this->records->product(array_replace(['name' => 'Published fixture', 'slug' => 'published-fixture', 'status' => 'published', 'description' => '<p>Verified description</p>'], $changes)));
    }

    public function test_catalog_and_home_only_show_published_products_and_filter_by_type(): void
    {
        $product = $this->product();
        $this->product(['name' => 'Private draft', 'slug' => 'private-draft', 'status' => 'draft']);
        $this->product(['name' => 'Archived fixture', 'slug' => 'archived-fixture', 'status' => 'archived']);
        $this->product(['name' => 'Indicator fixture', 'slug' => 'indicator-fixture', 'type' => 'indicator']);
        foreach (['/', '/produk'] as $uri) {
            $this->get($uri)->assertOk()->assertSee($product->name)->assertDontSee('Private draft')->assertDontSee('Archived fixture')->assertDontSee('Belum ada produk tersedia');
        }
        Livewire::test(Products::class)->set('type', 'indicator')->assertSee('Indicator fixture')->assertDontSee('Published fixture');
        Livewire::test(Products::class)->set('search', 'Published fixture')->assertSee('Published fixture')->assertDontSee('Indicator fixture');
    }

    public function test_empty_catalog_and_broker_pages_are_honest_and_accessible_server_rendered_documents(): void
    {
        foreach (['/produk', '/broker'] as $uri) {
            $response = $this->get($uri)->assertOk()->assertSee('id="konten"', false)->assertSee('lang="id"', false)->assertSee('rel="canonical"', false)->assertSee('Trading dapat menyebabkan kerugian');
            $this->assertSame(1, substr_count($response->getContent(), '<main '));
        }
        $this->get('/produk')->assertSee('Belum ada produk tersedia');
        $this->get('/broker')->assertSee('Informasi broker belum tersedia')->assertDontSee('Hitung estimasi');
    }

    public function test_detail_hides_absent_sections_and_never_exposes_a_checkout_or_fake_metrics(): void
    {
        $this->withoutExceptionHandling();
        $product = $this->product(['sale_enabled' => true]);
        $this->get('/produk/'.$product->slug)->assertOk()->assertSee('Penjualan belum dibuka')->assertDontSee('id="performa"', false)->assertDontSee('id="preview"', false)->assertDontSee('id="faq"', false)->assertDontSee('id="ulasan"', false)->assertDontSee('Profit bersih')->assertDontSee('href="/checkout', false);
    }

    public function test_draft_archived_unknown_and_unpublished_after_mount_return_not_found(): void
    {
        $product = $this->product();
        $component = Livewire::test(Detail::class, ['slug' => $product->slug]);
        $product->forceFill(['status' => 'draft'])->save();
        $component->call('$refresh')->assertNotFound();
        foreach (['draft', 'archived'] as $status) {
            $product->forceFill(['status' => $status])->save();
            $this->get('/produk/'.$product->slug)->assertNotFound();
        }
        $this->get('/produk/unknown-product')->assertNotFound();
    }

    public function test_published_detail_unifies_real_metadata_and_redacts_private_paths_and_absent_figures(): void
    {
        $product = $this->product();
        $user = $this->records->user();
        $version = $this->records->create('product_versions', ['product_id' => $product->id, 'version' => '1.0', 'path' => 'PRIVATE-version-path', 'released_at' => now(), 'changelog' => 'Released change']);
        $this->records->create('product_performances', ['product_id' => $product->id, 'version_id' => $version, 'test_type' => 'forward_test', 'account_environment' => 'real', 'starts_on' => '2026-01-01', 'ends_on' => '2026-01-31', 'symbol' => 'TEST', 'timeframe' => 'H1', 'net_profit_pct' => '-2.5000', 'max_drawdown_pct' => null, 'win_rate_pct' => null, 'report_path' => 'PRIVATE-report-path', 'methodology_note' => 'Actual source methodology fixture', 'updated_by' => $user]);
        $this->records->create('faqs', ['product_id' => $product->id, 'question' => 'Published question', 'answer' => '<p>Published answer</p>', 'is_active' => true]);
        $this->records->create('faqs', ['product_id' => $product->id, 'question' => 'Hidden question', 'answer' => 'Hidden answer', 'is_active' => false]);
        $guide = $this->records->create('guides', ['product_id' => $product->id, 'is_active' => true]);
        $this->records->create('guide_steps', ['guide_id' => $guide, 'step_no' => 1, 'body' => '<p>Install from real guide</p>']);
        $this->records->create('testimonials', ['product_id' => $product->id, 'display_name' => 'Consented name', 'content' => 'Consented review', 'is_visible' => true, 'published_at' => now(), 'consent_record' => 'PRIVATE-consent-record']);
        $this->records->create('testimonials', ['product_id' => $product->id, 'display_name' => 'Unpublished name', 'content' => 'Hidden testimonial', 'is_visible' => false, 'published_at' => now()]);
        $this->get('/produk/'.$product->slug)->assertOk()->assertSee('Forward test')->assertSee('Akun real')->assertSee('-2.5000')->assertSee('Actual source methodology fixture')->assertSee('Published question')->assertSee('Install from real guide')->assertSee('Released change')->assertSee('Consented review')->assertDontSee('Hidden question')->assertDontSee('Hidden testimonial')->assertDontSee('PRIVATE-')->assertDontSee('Drawdown maksimum')->assertDontSee('Win rate');
    }

    public function test_published_images_are_visible_but_unpublished_images_and_raw_reports_remain_private(): void
    {
        $product = $this->product();
        $path = 'products/images/'.Str::uuid().'.webp';
        $im = imagecreatetruecolor(4, 3);
        ob_start();
        imagewebp($im);
        $bytes = ob_get_clean();
        imagedestroy($im);
        Storage::disk('local')->put($path, $bytes);
        $media = $this->records->create('product_media', ['product_id' => $product->id, 'media_type' => 'image', 'section' => 'preview', 'disk_path' => $path, 'alt_text' => 'Actual alt text']);
        $this->get('/produk/'.$product->slug)->assertOk()->assertSee('Actual alt text')->assertSee('width="4"', false)->assertDontSee($path);
        $this->get(route('produk.media', $media))->assertOk()->assertHeader('Content-Type', 'image/webp');
        $product->forceFill(['status' => 'draft'])->save();
        $this->get(route('produk.media', $media))->assertNotFound();
    }

    public function test_missing_or_unsafe_media_do_not_produce_an_empty_preview_section_or_unsafe_links(): void
    {
        $product = $this->product();
        $this->records->create('product_media', ['product_id' => $product->id, 'media_type' => 'image', 'section' => 'preview', 'disk_path' => 'products/images/missing.webp']);
        $this->records->create('product_media', ['product_id' => $product->id, 'media_type' => 'video_link', 'section' => 'preview', 'external_url' => 'https://youtube.com.evil.invalid/video']);
        $this->get('/produk/'.$product->slug)->assertOk()->assertDontSee('id="preview"', false)->assertDontSee('evil.invalid');
    }

    public function test_seo_uses_configured_canonical_origin_and_json_ld_cannot_close_script_tags(): void
    {
        config(['app.url' => 'https://canonical.example.invalid']);
        $product = $this->product(['seo_title' => 'SEO fixture', 'seo_description' => 'Description </script><script>SECRET-XSS</script>']);
        $response = $this->withHeader('Host', 'untrusted.example.invalid')->get('/produk/'.$product->slug)->assertOk()->assertSee('<title>SEO fixture — Trader Garuda</title>', false)->assertSee('https://canonical.example.invalid/produk/published-fixture', false)->assertSee('property="og:title"', false)->assertDontSee('</script><script>SECRET-XSS</script>', false);
        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $response->getContent(), $match);
        $schema = json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('Product', $schema['@type']);
        $this->assertArrayNotHasKey('offers', $schema);
        $this->assertStringNotContainsString('untrusted', $schema['url']);
    }

    public function test_broker_page_only_shows_active_effective_partners_and_allowlisted_links(): void
    {
        config(['tradergaruda.broker_referral_hosts' => ['partner.example.invalid']]);
        $this->records->create('brokers', ['name' => 'Available partner', 'ib_link' => 'https://partner.example.invalid/register', 'is_active' => true, 'valid_from' => '2026-01-01', 'terms' => '<p>Partner terms</p><script>BAD-SCRIPT</script>']);
        $this->records->create('brokers', ['name' => 'Hidden partner', 'is_active' => false]);
        $this->records->create('brokers', ['name' => 'Future partner', 'is_active' => true, 'valid_from' => now()->addYear()->toDateString()]);
        $this->get('/broker')->assertOk()->assertSee('Available partner')->assertSee('https://partner.example.invalid/register', false)->assertSee('Partner terms')->assertDontSee('BAD-SCRIPT')->assertDontSee('Hidden partner')->assertDontSee('Future partner')->assertSee('Hitung estimasi')->assertSee('bukan syarat membeli EA');
        foreach (['https://partner.example.invalid.evil.invalid/register', 'https://user@partner.example.invalid/register', 'http://partner.example.invalid/register', 'https://127.0.0.1/register', 'https://partner.example.invalid:444/register'] as $url) {
            $this->assertNull(PublicContent::brokerLink($url));
        }
    }

    public function test_rebate_estimate_uses_decimal_strings_and_rejects_inactive_partner_without_mutation(): void
    {
        $broker = $this->records->create('brokers', ['is_active' => true, 'valid_from' => '2026-01-01', 'rate_usd_per_lot' => '1.2345']);
        $component = Livewire::test(Brokers::class)->set('brokerId', (string) $broker)->set('lots', '0.1000')->call('calculate')->assertHasNoErrors()->assertSet('estimate', '0.1234');
        DB::table('brokers')->where('id', $broker)->update(['is_active' => false]);
        $component->call('calculate')->assertHasErrors(['brokerId'])->assertSet('estimate', null);
        $this->assertDatabaseCount('rebate_entries', 0);
        $this->assertDatabaseCount('wallets', 0);
        Livewire::test(Brokers::class)->set('brokerId', (string) $broker)->set('lots', '1e3')->call('calculate')->assertHasErrors(['lots']);
    }
}
