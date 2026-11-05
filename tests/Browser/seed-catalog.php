<?php

// seed.php checks e2e, actual MySQL 8.4 tg_test and DB_URL before any destructive action.
require __DIR__.'/seed.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\DomainRecords;

DB::transaction(function (): void {
    $records = new DomainRecords;
    $product = $records->product(['name' => 'Synthetic public EA', 'slug' => 'synthetic-public-ea', 'status' => 'published', 'sale_enabled' => false, 'description' => '<p>Synthetic catalogue fixture only.</p>']);
    $records->product(['name' => 'Synthetic empty indicator', 'slug' => 'synthetic-empty-indicator', 'type' => 'indicator', 'status' => 'published', 'sale_enabled' => false]);
    $records->product(['name' => 'PRIVATE draft fixture', 'slug' => 'private-draft-fixture', 'status' => 'draft']);
    $image = 'products/images/'.Str::uuid().'.webp';
    $im = imagecreatetruecolor(320, 200);
    ob_start();
    imagewebp($im);
    $bytes = ob_get_clean();
    imagedestroy($im);
    Storage::disk('local')->put($image, $bytes);
    $records->create('product_media', ['product_id' => $product, 'media_type' => 'image', 'section' => 'preview', 'disk_path' => $image, 'alt_text' => 'Synthetic preview image']);
    $records->create('product_media', ['product_id' => $product, 'media_type' => 'video_link', 'section' => 'tutorial', 'external_url' => 'https://www.youtube.com/watch?v=synthetic-fixture', 'alt_text' => 'Synthetic tutorial link']);
    $records->create('faqs', ['product_id' => $product, 'question' => 'Synthetic FAQ question', 'answer' => '<p>Synthetic answer.</p>', 'is_active' => true]);
    $guide = $records->create('guides', ['product_id' => $product, 'is_active' => true]);
    $records->create('guide_steps', ['guide_id' => $guide, 'step_no' => 1, 'body' => '<p>Synthetic install step.</p>', 'image_path' => $image, 'alt_text' => 'Synthetic installation image']);
    $records->create('product_performances', ['product_id' => $product, 'test_type' => 'backtest', 'account_environment' => 'demo', 'starts_on' => '2026-01-01', 'ends_on' => '2026-01-31', 'symbol' => 'TEST', 'timeframe' => 'H1', 'net_profit_pct' => '-2.5000', 'max_drawdown_pct' => null, 'win_rate_pct' => null, 'methodology_note' => 'Synthetic methodology; no real performance claim.', 'updated_by' => DB::table('users')->where('role', 'admin')->value('id')]);
    $records->create('product_versions', ['product_id' => $product, 'version' => '1.0', 'path' => 'products/versions/private-fixture.ex5', 'released_at' => now(), 'changelog' => 'Synthetic version note.']);
    $records->create('testimonials', ['product_id' => $product, 'display_name' => 'Synthetic reviewer', 'content' => 'Synthetic review (test only).', 'consent_record' => 'private-fixture-consent', 'is_visible' => true, 'published_at' => now()]);
    $records->create('brokers', ['name' => 'Synthetic partner', 'ib_link' => 'https://partner.example.invalid/register', 'is_active' => true, 'valid_from' => '2026-01-01', 'rate_usd_per_lot' => '1.2345', 'terms' => '<p>Synthetic broker terms.</p>']);
});
echo "Synthetic published catalogue fixtures created only in tg_test.\n";
