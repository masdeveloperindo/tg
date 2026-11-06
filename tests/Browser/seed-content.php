<?php

use App\Actions\Content\PublishContent;
use App\Actions\Operations\DetectAnomalies;
use App\Actions\Orders\CalendarPricing;
use App\Models\License;
use App\Models\LicenseLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\CheckoutRecords;
use Tests\Support\DomainRecords;

// Existing seed validates actual e2e/MySQL8.4/tg_test before resetting any tables.
require __DIR__.'/seed.php';
$session = app('session')->driver();
$session->start();
request()->setLaravelSession($session);
$id = (int) DB::table('users')->where('email', 'layout-admin@example.invalid')->value('id');
Auth::loginUsingId($id);
session()->put(['auth.totp_user_id' => $id, 'auth.totp_verified_at' => time(), 'auth.admin_last_activity' => time(), 'auth.password_confirmed_at' => time(), 'auth.reauthenticated_at' => time()]);
CheckoutRecords::configure();
$img = imagecreatetruecolor(240, 120);
imagefill($img, 0, 0, imagecolorallocate($img, 20, 66, 48));
imagestring($img, 5, 40, 50, 'SYNTHETIC', imagecolorallocate($img, 220, 255, 100));
$logo = storage_path('framework/testing/phase4-image.png');
imagepng($img, $logo);
imagedestroy($img);
$upload = fn () => new UploadedFile($logo, 'synthetic.png', 'image/png', null, true);
$publisher = app(PublishContent::class);
$base = ['title' => 'Synthetic public lesson', 'slug' => 'synthetic-public-lesson', 'body' => '<h2>Synthetic lesson</h2><p>Fixture content only, no trading advice.</p>', 'category' => 'cara_trading', 'visibility' => 'public', 'status' => 'published', 'published_at' => '', 'seo_title' => 'Synthetic lesson SEO', 'seo_description' => 'Synthetic educational fixture.'];
$publisher->save('edukasi', null, $base, $upload());
$private = $publisher->save('edukasi', null, [...$base, 'title' => 'Synthetic member lesson', 'slug' => 'synthetic-member-lesson', 'visibility' => 'client']);
$publisher->articleImage($private->id, $upload(), 'Synthetic member illustration');
$publisher->save('edukasi', null, [...$base, 'title' => 'Synthetic unpublished draft', 'slug' => 'synthetic-hidden-draft', 'status' => 'draft']);
$publisher->save('panduan', null, ['title' => 'Synthetic MT5 installation', 'slug' => 'synthetic-mt5-installation', 'summary' => '<p>Synthetic installation steps.</p>', 'product_id' => '', 'is_active' => true], null, [['body' => '<p>Open the synthetic terminal settings.</p>', 'alt_text' => 'Synthetic terminal diagram'], ['body' => '<p>Check the synthetic installation.</p>', 'alt_text' => '']], [0 => $upload()]);
$publisher->save('faq', null, ['question' => 'Synthetic FAQ question?', 'answer' => '<p>Synthetic FAQ answer.</p>', 'product_id' => '', 'sort_order' => '0', 'is_active' => true]);
$publisher->save('halaman', null, ['slug' => 'kebijakan-privasi', 'title' => 'Synthetic privacy fixture', 'body' => '<p>TEST FIXTURE ONLY. Not a real legal policy.</p>', 'seo_title' => 'Synthetic privacy SEO', 'seo_description' => 'Synthetic fixture for testing publication only.', 'is_published' => true]);
$publisher->save('testimoni', null, ['product_id' => '', 'display_name' => 'Synthetic consenting reviewer', 'content' => 'SYNTHETIC FIXTURE. Not a real review.', 'rating' => '4', 'source' => 'SYNTHETIC-PRIVATE-SOURCE', 'consent_record' => 'SYNTHETIC-PRIVATE-CONSENT', 'consent_confirmed' => true, 'sort_order' => '0', 'is_visible' => true, 'published_at' => '']);
$publisher->save('pengumuman', null, ['title' => 'Synthetic client bulletin', 'body' => '<p>Synthetic members-only bulletin.</p>', 'audience' => 'clients', 'is_active' => true, 'starts_at' => '', 'ends_at' => '']);
$r = new DomainRecords;
$client = (int) DB::table('users')->where('email', 'layout-client@example.invalid')->value('id');
$product = $r->product(['name' => 'Synthetic anomaly product']);
$order = $r->create('orders', ['user_id' => $client, 'product_id' => $product, 'status' => 'paid']);
$license = License::findOrFail($r->create('licenses', ['user_id' => $client, 'product_id' => $product, 'issued_order_id' => $order, 'expires_at' => CalendarPricing::end(2026)]));
$r->create('product_versions', ['product_id' => $product, 'version' => '1.0.0', 'released_at' => now()]);
for ($i = 0; $i < 6; $i++) {
    (new LicenseLog)->forceFill(['license_id' => $license->id, 'event' => 'verify', 'result' => 'account_mismatch', 'product_version' => '1.0.0', 'ip_hash' => hash('sha256', 'synthetic-ip'), 'request_id' => (string) Str::uuid()])->save();
}
app(DetectAnomalies::class)->run();
$r->create('orders',['user_id' => $client, 'status' => 'waiting', 'expires_at' => now()->addHour()]);
$r->create('orders',['user_id' => $client, 'status' => 'paid']);
echo "Synthetic CMS, member bulletin, task and anomaly fixtures created only in e2e tg_test. No EA gate or production content enabled.\n";
