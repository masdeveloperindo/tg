# Audit keamanan main — 7 November 2026

Baseline `e8efbf5` pada main. Scope: kode Laravel/Livewire, kontrak role/owner, autentikasi/sesi/admin TOTP, input/HTML/upload/private file, API lisensi, order/refund/ledger/payout concurrency, exception/log, konfigurasi, file terlacak/history Git dan dependency advisory. Ini review software dan regresi lokal, bukan pentest infrastruktur atau jaminan seluruh kerentanan tidak ada.

## Temuan dan perbaikan

| Temuan | Dampak / perbaikan |
| --- | --- |
| Perubahan harga produk belum memeriksa reauthentication | Admin dengan sesi TOTP valid tetapi verifikasi sensitif >10menit masih dapat mengubah harga. `ManageProducts::save` kini memeriksa fresh actor + reauth saat membuat produk atau mengubah harga dasar/minimum, di dalam transaksi setelah product lock. Penolakan tidak mengubah harga/audit; perubahan konten tanpa perubahan harga tetap diizinkan sesuai policy. UI menyediakan link verifikasi ulang. |
| Header anti-framing dan referrer belum menyeluruh | Middleware global dan exception responder menambahkan X-Frame-Options DENY, CSP frame-ancestors none/object-src none/base-uri self, nosniff, no-referrer dan Permissions-Policy kamera/mikrofon/geolokasi disabled. Berlaku juga pada redirect, error, API dan respons Livewire. CSP ini tidak mengklaim mitigasi XSS lengkap; HTML tetap disanitasi dengan allowlist. |
| Storage privat hanya memeriksa lokasi langsung di webroot | Root kini juga menolak disk publik, target konfigurasi link publik dan symlink aktual yang mengekspos seluruh/sebagian storage privat. GET gambar publik/admin serta admin EX5/PDF memeriksa pola path generated dan canonical file containment; symlink escape/path traversal ditolak. Role/owner/publish policy tetap berlaku. |
| Default session encryption tidak aman bila env dihilangkan | Default encryption menjadi true; Secure cookie default aktif pada APP_ENV production. HTTP lokal tetap menggunakan SESSION_SECURE_COOKIE=false sesuai .env.example. Tidak mengubah .env existing atau APP_KEY. |
| Guard tes baru hanya memeriksa konfigurasi DB | TestCase sekarang memeriksa SELECT DATABASE()/VERSION() aktual sebelum RefreshDatabase; session/cache/lock test dipaksa memakai koneksi mysql khusus tg_test, sehingga tidak mengikuti koneksi session/cache lain dari konfigurasi lokal. |

## Pemeriksaan kode lain

- Route/middleware dan persistent middleware Livewire mempertahankan auth/active/role/TOTP/session/password-change; client queries scoped user_id. Policy dan service memeriksa owner/role saat render/action, bukan hanya tombol UI.
- Register memaksa client, password Argon2id; password/OTP dibersihkan dari snapshot setelah submit. Admin TOTP/recovery tidak dibypass; reauth dan idle timeout memakai timestamp server/session.
- Contact/TOTP/license key/settings/tujuan bank terenkripsi; fields sensitif disembunyikan dari serialisasi. Key/signature/bank/contact tidak disalin ke log/audit mentah; exception responder memberi pesan generik tanpa stack trace.
- SQL dinamis yang ditemukan berupa agregasi/constraint tetap tanpa interpolasi input; tidak ditemukan eval/unserialize/shell execution pada handler aplikasi.
- Checkout quote, konfirmasi, refund dan wallet tetap menggunakan decimal/BCMath, transaction/row lock, audit atomik dan idempotency. Test dua worker/koneksi memeriksa double-submit, old snapshot, nonce/refund/account change dan saldo tidak negatif.
- API memeriksa HTTPS, ukuran/format, timestamp, nonce durable unik, rate limit dan signature Ed25519. Endpoint deactivate tidak dibuka. Signature PHP/Python bukan bukti terminal EA.
- Upload allowlist/re-encode image membatasi tipe/ukuran/dimensi; file privat tidak menggunakan public disk atau storage route generik. HTML publik dirender melalui SafeHtml; outbound broker memakai allowlist HTTPS.

## Dependency dan secret

`npm audit --json` lokal: **0 vulnerability**. `composer audit --locked` tidak dapat diselesaikan lokal: TLS trust dipenuhi dengan CA managed read-only, kemudian proxy menolak CONNECT ke `packagist.org/api/security-advisories/` dengan403. Verifikasi TLS tetap aktif. Ini **BLOCKED EXTERNAL**, bukan hasil bersih. Workflow CI ditambah audit Composer/npm yang gagal bila advisory ditemukan/layanan tidak dapat diperiksa; hasil remote CI belum diklaim lulus.

Pemeriksaan file terlacak dan path di seluruh reachable Git history: tidak ditemukan .env asli, auth.json, private key/EX5/database dump atau marker token/private key nyata yang cocok pola pemeriksaan. Satu marker BEGIN PRIVATE KEY ada dalam **tes negatif sintetis** `tools/qa/test_release_bundle.py`; bukan key valid. File `.sql` terlacak hanya script DDL inisialisasi database tes, bukan dump data. Ini pemeriksaan pola/path, bukan jaminan deteksi semua jenis secret.

## Batas data dan pengujian

Sebelum tes, preflight read-only memverifikasi MySQL8.4 tg_test cloud tidak berisi baris bisnis pada users/orders/licenses/withdrawals/rebate_entries. Seluruh migration/reset/fixture sesudahnya hanya pada tg_test sintetis. Database aplikasi tg dan database di komputer pengguna tidak dimigrasi/reset, kredensial tidak dibaca ke output, tidak membuat transfer atau memanggil broker/Telegram. APP_KEY dan .env existing tidak diubah.

Hasil akhir: **411 tes PHP/MySQL8.4 PASS /3997 assertions /54,88detik**, mencakup semua suite sebelumnya serta9 tes regresi baru (6 hardening +3 harga). **279 file Pint PASS**, Composer validate --strict dan Vite build PASS. Enam suite browser Livewire: **56 pemeriksaan PASS**, tanpa error JS; suite produk diulang sesudah perubahan harga final. Empat vector Ed25519 PHP→Python dan4 tes artifact builder juga PASS.

Bukti: [suite dan lint](security-main-tests.txt), [build](security-main-build.txt), [npm audit](security-main-npm-audit.json), [status Composer](security-main-composer-status.json), [browser](security-main-browser/summary.json).

Pemeriksaan public/ aktual tidak menemukan symlink. Guard storage menolak konfigurasi/operasi aplikasi yang akan mengekspos storage; jika operator sudah membuat link publik pada host lain, operator harus menghilangkan exposure file statis tersebut, karena file statis tidak melewati middleware Laravel. Tidak mengubah link/storage milik operator secara otomatis.

Tidak ada migration baru pada patch ini. Setelah mengambil kode, jalankan `php artisan optimize:clear` agar config/route/view lama tidak dipakai; pertahankan APP_KEY, env dan file privat existing. Kandidat/manifest Fase5 commit fe4b4c4 adalah bukti historis, belum berisi patch audit; build kandidat ulang dari main terbaru sebelum handoff. Bukti yang belum tersedia: audit Composer remote yang selesai, staging/host TLS/DNS/webserver body limit/backup, terminal EA dan UAT owner. Transaksi nyata tetap terkunci; status SOFTWARE READY/GO-LIVE tidak diubah menjadi lulus.
