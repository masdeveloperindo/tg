# Development lokal — Trader Garuda

Panduan ini mencakup TG-004–TG-018, backend TG-019 dan TG-021–TG-022: fondasi Laravel 13, Livewire 4, Tailwind CSS 4, database, autentikasi, layout bersama, build aset dan pengujian. Baca `AGENTS.md`, `SCOPE_OF_WORK.md` dan kontrak di `technical/` sebelum mengembangkan modul berikutnya. Gunakan checkout yang sudah ada; setiap cloud task telah terisolasi, sehingga tidak perlu membuat Git worktree tanpa permintaan pengguna.

## Prasyarat

- Docker Engine dan Docker Compose v2 untuk runtime PHP dan database development.
- Node.js 24 dan npm untuk frontend, atau service Compose `node` jika tidak memakai Node di host.
- Git untuk memperoleh source beserta lockfile. Jalankan semua perintah dari root repository.

Image development menyediakan PHP 8.4 dan Composer 2 beserta ekstensi aplikasi. Project ini memilih PHP `^8.4` agar cocok dengan dependency yang dikunci; versi Laravel 13 yang terpasang sendiri mendeklarasikan PHP `^8.3`. Ikuti constraint `composer.json` dan `composer.lock` saat menyiapkan runtime. MySQL tetap 8.4 LTS; jangan menggantinya dengan PostgreSQL atau menyatakan hasil SQLite sebagai bukti kompatibilitas MySQL.

Container ini khusus pengembangan dan pengujian lokal. Konfigurasi VPS, DNS, Nginx, TLS, cron host, backup fisik dan deployment produksi tetap menjadi tanggung jawab pihak infrastruktur.

## Konfigurasi lokal

Gunakan `.env.example` sebagai contoh aman. `.env` berisi konfigurasi lokal dan tidak boleh di-commit. `APP_KEY`, `DB_PASSWORD` dan `DB_ROOT_PASSWORD` harus dibuat untuk lingkungan sendiri; jangan menyalin secret produksi ke development atau memasukkannya ke chat/log.

Konfigurasi penting:

| Variabel | Kegunaan |
| --- | --- |
| `APP_ENV`, `APP_DEBUG`, `APP_URL` | Mode runtime dan alamat aplikasi; jangan hard-code domain produksi |
| `APP_KEY` | Kunci enkripsi aplikasi lokal yang dibuat helper konfigurasi |
| `DB_CONNECTION` | `mysql` untuk development dan integrasi |
| `DB_HOST`, `DB_PORT` | `db`, `3306` pada jaringan Compose |
| `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | `tg`/`tg` untuk instance Compose bawaan; password hanya di `.env` |
| `DB_ROOT_PASSWORD` | Inisialisasi instance MySQL lokal; jangan gunakan user root untuk aplikasi |
| `SESSION_DRIVER`, `CACHE_STORE` | Default database; tabel framework dibuat melalui migration |
| `QUEUE_CONNECTION` | `sync`; tidak diperlukan queue worker |
| `FILESYSTEM_DISK` | Disk privat aplikasi; `.ex5` tidak boleh disimpan di direktori publik |

Waktu internal disimpan sebagai UTC dan waktu antarmuka ditampilkan dalam Asia/Jakarta. Data bisnis belum disediakan: jangan mengisi username Telegram, rekening, broker, harga, kurs, hasil trading atau testimoni produksi dengan data contoh. Tidak diperlukan token Telegram, SMTP, Redis atau worker untuk tahap ini.

## Menyiapkan dan menjalankan aplikasi

Helper lokal membangun image app, membuat `.env` baru dengan kunci aplikasi/password database acak, memasang dependency dari lockfile, memulai MySQL, menjalankan migration, dan membangun aset memakai service `node`. **Jika `.env` sudah ada, helper tidak mengubahnya, termasuk nilai kosong**; pastikan konfigurasi existing sudah lengkap sebelum menjalankan setup. Tidak perlu menyalin `.env.example` terlebih dahulu. Periksa `scripts/setup-local.sh` dan `scripts/configure-local.php` untuk langkah yang dijalankan:

```bash
bash scripts/setup-local.sh
docker compose up -d app
```

Aplikasi mendengarkan pada `127.0.0.1:8080`; database tidak diekspos ke port host. Periksa respons secara lokal:

```bash
curl --fail http://127.0.0.1:8080/
docker compose logs --tail=50 app
```

Service harus dijalankan ulang ketika memulai mesin atau cloud task baru; proses hidup tidak diasumsikan bertahan dalam snapshot. Hentikan service yang dibuat oleh workflow ini dengan `docker compose stop`. Jangan menjalankan `docker compose down -v` pada database yang datanya perlu dipertahankan.

Untuk menjalankan langkah yang sama secara terpisah:

```bash
docker compose build app db
docker compose run --rm --no-deps app php scripts/configure-local.php
docker compose run --rm --no-deps app composer install --no-interaction --prefer-dist
docker compose up -d db --wait
docker compose run --rm app php artisan migrate
npm ci
npm run build
docker compose up -d app
```

Helper membuat `APP_KEY` saat membuat `.env` baru. Jika menggunakan `.env` sendiri dengan `APP_KEY` kosong, buat kunci sekali melalui `docker compose run --rm --no-deps app php artisan key:generate` setelah dependency terpasang; siapkan password database lokal yang kuat melalui pengelolaan konfigurasi aman. Jangan mengganti kunci existing: data terenkripsi dapat menjadi tidak terbaca. Gunakan `composer install` dan `npm ci` agar mengikuti lockfile; jangan menjalankan update dependency tanpa task yang sesuai.

Alternatif build frontend menggunakan Node di Docker:

```bash
docker compose run --rm node npm ci
docker compose run --rm node npm run build
```

Gunakan Vite hanya selama mengembangkan frontend, melalui `npm run dev`. Build produksi lokal menggunakan `npm run build`; server Vite tidak harus terus hidup untuk membaca hasil build tersebut.

## Jaringan dan trust container

Install membutuhkan registry package, source GitHub, dan registry image yang dirujuk Dockerfile/Compose. Pada cloud dengan proxy, teruskan konfigurasi proxy resmi dan trust CA milik lingkungan ke container melalui override Compose lokal yang tidak di-commit. Composer/Git dan npm di dalam container harus mempercayai CA tersebut; trust pada host saja tidak menjamin trust di container. Pertahankan verifikasi TLS, signature/checksum dan referensi commit lockfile. Jangan menonaktifkan verifikasi untuk mengatasi kegagalan akses.

Jika distribusi ZIP Composer melalui GitHub API ditolak tetapi akses Git HTTPS melalui proxy sudah terbukti bekerja, helper menyediakan pilihan instalasi dari source yang tetap mengikuti commit pada lockfile:

```bash
COMPOSER_INSTALL_MODE=source bash scripts/setup-local.sh
```

Akses source tetap memerlukan izin jaringan dan trust yang benar. Penolakan domain harus diselesaikan melalui pengaturan lingkungan; opsi ini tidak memberi akses ke domain yang diblokir. Jangan memasukkan credential proxy atau token ke file konfigurasi yang di-commit.

## Pengujian

Jalankan pemeriksaan berikut dari root setelah dependency, `.env` dan database lokal tersedia:

```bash
docker compose run --rm app composer test
docker compose run --rm app php artisan route:list
npm run build
```

Konfigurasi PHPUnit memaksa koneksi MySQL dan database khusus `tg_test`; host dan credential mengikuti konfigurasi lokal (`db` pada Compose). Script inisialisasi MySQL membuat database test dan memberi akses kepada user lokal `tg` saat volume baru dibuat. Gunakan hanya instance development yang dibuat untuk proyek ini: migration/reset database pada test dapat menghapus data test. Jangan arahkan konfigurasi test ke database aplikasi yang berisi data penting, staging bersama atau produksi.

Periksa hasil dan jumlah test yang benar-benar berjalan. Test framework/smoke yang lulus tidak menggantikan pengujian workflow pembayaran, saldo concurrent, TOTP atau alur pembelian yang belum diimplementasikan. Constraint database dan anchor lock telah diuji terpisah dalam TG-006. Fresh migration dan rollback harus diuji pada database kosong/khas test; jangan menjalankan perintah destruktif tersebut pada database yang ingin dipertahankan. Catatan versi runtime, command, hasil pass/fail dan keterbatasan tersedia di `IMPLEMENTATION_STATUS.md`.

## Cakupan dan dependensi berikutnya

Fondasi ini menyiapkan runtime, konfigurasi, halaman dasar, aset dan migration framework untuk cache/session. TG-006 sudah menyediakan 30 tabel domain beserta model, FK, CHECK, unique dan indeks (lihat `technical/validation/TG006.md`). Autentikasi, policy dan TOTP sudah tersedia melalui TG-007; workflow transaksi dan validasi action belum disediakan oleh keberadaan schema. Panel admin tahap ini hanya mengelola akses client; pembelian EA, rebate, pencairan dan API lisensi belum tersedia sebagai alur produksi.

`html-reference/` tetap menjadi referensi visual dan interaksi. Checkout awal kehilangan `assets/style.css` serta `assets/app.js`, sehingga interaksi demo tidak bisa dianggap tervalidasi meskipun HTML dapat dilayani. Screenshot dan PRD menjadi acuan sambil mempertahankan referensi asli.

TG-001–TG-003 menunggu input owner/legal/source EA dan bukti pengujian terminal MT5. Hal itu tidak menghentikan pekerjaan fondasi, tetapi penjualan berlisensi dan indikator harus tetap nonaktif sampai PoC yang disyaratkan lulus. Username Telegram hanya untuk tautan chat pribadi manual; tidak ada bot, webhook, pesan otomatis, ataupun konfirmasi pembayaran melalui chat.

Staging, domain API lama selama migrasi, credential produksi, storage fisik dan operasional host diserahkan melalui `RELEASE_HANDOFF.md`. Kesiapan development lokal tidak berarti `SOFTWARE READY FOR HANDOFF` atau persetujuan `GO-LIVE`.

## Autentikasi TG-007

Halaman `/register` membuat client dengan kontak Telegram/WhatsApp terenkripsi, bukan admin. `/login` memakai email/password; client tidak memerlukan email verification atau OTP. Login gagal dibatasi 5 percobaan per email+IP dan 20 per IP selama 15 menit. Registrasi dan reset juga dibatasi pada service agar aksi Livewire tidak melewati pembatasan.

Buat **admin pertama saja** lewat CLI privat (bukan seeder, tidak ada password bawaan):

```bash
docker compose run --rm app php artisan tg:create-admin
```

Perintah meminta nama/email dan password tersembunyi beserta konfirmasi, lalu menolak dijalankan ulang jika admin sudah ada. Jangan menaruh password pada argumen command, Git, chat atau log. Tidak ada akun yang otomatis dibuat oleh setup. Setelah login, admin wajib memasukkan secret penyiapan ke aplikasi authenticator secara manual dan membuktikan kode 6 digit. Simpan 10 recovery code dari halaman sekali tampil; database hanya menyimpan hash kode. Kehilangan seluruh faktor memerlukan prosedur pemulihan owner yang terverifikasi, bukan bypass Telegram atau CLI pembuatan admin ulang.

Panel `/admin` dan `/admin/client` memerlukan TOTP. Sesi admin idle lebih dari 30 menit diputus, termasuk bila mencoba masuk lewat halaman challenge/setup/password. Reset client memerlukan reauth password+TOTP yang berlaku 10 menit, kontak terdaftar, alasan dan dua pemeriksaan identitas manual. Modal menampilkan kontak hanya kepada admin yang berhak. Password sementara ditampilkan sekali pada halaman private/no-store; tidak ada pengiriman chat/email otomatis. Seluruh sesi client lama dicabut dan client harus mengganti password sesudah login. Perubahan password juga mencabut sesi lama dan merotasi sesi saat ini.

Gunakan transaksi sintetis hanya di test. Semua suite masih memakai database `tg_test`. Bukti autentikasi: `technical/validation/TG007.md`; bukti layout terbaru: `technical/validation/TG008.md`. Baseline pengujian lama dipertahankan sebagai histori. Autentikasi tidak mengaktifkan penjualan atau menyelesaikan otorisasi action bisnis yang belum diimplementasikan.

## Layout dan pengujian browser TG-008

Komponen, token warna, aturan keyboard dan batas responsif dijelaskan di [`technical/DESIGN_SYSTEM.md`](technical/DESIGN_SYSTEM.md). Halaman publik memakai header bersama; client dan admin memakai sidebar sesuai peran. Menu hanya menuju fitur yang tersedia. Dashboard tidak mengisi saldo atau statistik simulasi.

Pengujian browser memerlukan Node, paket `playwright` yang dapat di-resolve oleh Node, serta Chromium pada `/usr/bin/chromium`. Ketiganya tersedia pada runtime cloud yang divalidasi; runner ini tidak memasang dependency browser atau mengubah lockfile aplikasi. Suite PHP tetap dapat dijalankan tanpa browser.

Runner melakukan login melalui form Livewire dan TOTP, bukan bypass sesi. Jalankan hanya pada server e2e dengan database khusus `tg_test`. **Jangan menjalankan suite PHP bersamaan**, karena keduanya melakukan reset database test yang sama. Hentikan server browser lama sebelum membuat ulang fixture:

```bash
docker compose run --rm --no-deps -e APP_ENV=e2e -e DB_DATABASE=tg_test -e DB_URL= app php tests/Browser/seed.php
docker compose run --rm --no-deps --name tg008-browser-app -p 127.0.0.1:8081:8000 -e APP_ENV=e2e -e APP_URL=http://127.0.0.1:8081 -e DB_DATABASE=tg_test -e DB_URL= app php artisan serve --host=0.0.0.0 --port=8000 --no-reload
```

Perintah server berjalan di foreground. **`--no-reload` wajib** untuk server ini: Laravel ServeCommand dapat menghapus override environment dari worker HTTP ketika reload aktif, sehingga worker tidak memakai database e2e yang dimaksud. Script fixture menolak konfigurasi dan database aktual selain MySQL 8.4 `tg_test` pada `APP_ENV=e2e` sebelum migration destruktif. Credential di script hanya fixture sintetis dan tidak boleh dipakai sebagai akun nyata.

Di terminal terpisah:

```bash
LAYOUT_ARTIFACT_DIR=/tmp/tg-layout-artifacts node tests/Browser/layout.cjs
docker stop tg008-browser-app
```

Origin default runner adalah loopback port 8081; `LAYOUT_BASE_URL` hanya menerima origin HTTP loopback. Runner memeriksa viewport 360/768/1280, tema terang dengan preferensi OS gelap, overflow, skip link, target menu, navigasi aktif, focus trap drawer, Escape/pengembalian fokus, penutupan sesudah mengikuti tautan, nama/email panjang, dan pencarian Livewire. JSON hasil dan screenshot memakai fixture sintetis; halaman secret TOTP, recovery dan password sementara tidak diambil. Arsip hasil aktual ada di [`technical/validation/TG008.md`](technical/validation/TG008.md).

Hasil ini mencakup layout yang tersedia, bukan seluruh workflow transaksi atau audit aksesibilitas lintas browser. Berikutnya TG-009: komponen modal, loading, error, toast dan status belum pasti.

## Feedback TG-009

Komponen bersama dan reproduksi pemeriksaan kegagalan browser: [`technical/validation/TG009.md`](technical/validation/TG009.md). Bungkus input upload dengan `x-ui.upload-status`; selesai transfer tetap memerlukan hasil validasi server. Modal `x-ui.modal` mengirim event `modal-cancel`; pemakai wajib menghubungkannya ke aksi pembatalan berotorisasi. Gunakan loading berdasarkan request dan toast hanya setelah keberhasilan server. Untuk transaksi yang belum pasti, tombol Cek Status mengambil ulang data lewat GET; jangan mengulang mutasi atau membuat intent baru otomatis.

## Audit dan error TG-010

Semua request memperoleh `X-Request-ID` dari server; sertakan referensi itu saat melaporkan error. Pesan/context log bebas disensor oleh allowlist, sehingga event baru harus memakai metadata aman. Activity audit autentikasi memakai `App\Support\Audit` dalam transaksi mutasi yang sama. Lihat [`technical/validation/TG010.md`](technical/validation/TG010.md).

`docker compose run --rm app php artisan tg:prune-license-logs` hanya menampilkan dry run. Opsi `--apply` menerapkan retensi telemetry90hari/IP terenkripsi30hari; audit tetap dipertahankan. Scheduler belum diaktifkan (TG-035). Default baru `LOG_STACK=daily`; `.env` existing dengan `single` tetap perlu disesuaikan operator untuk rotasi14file. Tidak ada secret atau `.env` existing yang diubah dalam task ini.

## Produk TG-011

Admin terverifikasi mengelola produk melalui `/admin/produk` dan satu editor bertab. Semua file berada di disk privat; endpoint gambar/file/preview memerlukan admin. Publikasi konten tidak membuka penjualan. Editor memakai HTML sederhana yang disanitasi, bukan embed/script. Harga dasar harus berasal dari keputusan owner; hasil uji wajib berasal dari sumber nyata.

Rebuild image app setelah perubahan Dockerfile: GD menyediakan re-encode JPG/PNG/WebP; PHP harus membaca upload20M/post24M. File INI disalin dengan mode0644 agar terbaca oleh user non-root. `.env` existing tetap dipertahankan. Temporary upload Livewire saat ini admin-only dengan batas20MiB; use case upload client berikutnya memerlukan kebijakan tersendiri.

Gambar maksimal2MiB, PDF bukti maksimal5MiB dan EX5 maksimal20MiB. Format gate EX5 saat ini ketat dan hanya diuji memakai fixture biner; validasi file EA nyata/terminal tetap memerlukan TG-003/TG-020. File tidak dieksekusi server. Versi historis dan bukti asli dipertahankan saat presentasi dihapus. Lihat [`technical/validation/TG011.md`](technical/validation/TG011.md) untuk alur browser, hasil aktual dan batasnya.

## Publikasi TG-012

`/produk` dan `/produk/{slug}` hanya menampilkan published. Unpublish/arsip menutup halaman dan endpoint gambar berikutnya. File versi EA, laporan PDF asli dan consent ulasan tetap privat. `/broker` memakai mitra aktif/efektif; isi `TG_BROKER_REFERRAL_HOSTS` dengan hostname HTTPS persis yang disetujui owner/legal bila pendaftaran sudah siap. Nilai default kosong; `.env` existing tidak diubah. Estimasi rebate adalah informasi, bukan saldo.

Fixture browser publik memakai `tests/Browser/seed-catalog.php` dan `tests/Browser/catalog.cjs`; database tg_test dan storage e2e terpisah. Prosedur dan hasil: [`technical/validation/TG012.md`](technical/validation/TG012.md). Harga kalender/kuotasi dan order belum aktif; berikutnya TG-013.

## Harga, checkout dan Telegram TG-013–TG-015

Konfigurasi owner dibaca dari Setting.value_encrypted (cast enkripsi), tanpa nilai produksi bawaan:

- checkout.pricing: JSON dengan monthly_discount_pct (string desimal 0–100), rounding_increment_idr (string desimal positif), rounding_mode (nearest/up/down), quote_minutes (integer1–60), payment_minutes (integer1–10080).
- checkout.payment_methods: array 1–10 objek dengan id slugunik, label,account_holder,account_number semuanya string; instructions opsional. Gunakan data yang disetujui owner, jangan fixture. Pengaturan UI owner menyeluruh mengikuti TG-034.
- telegram_admin_username: username pribadi, boleh diawali@ atau URL https://t.me/username tanpa query/fragment; fallback TG_TELEGRAM_USERNAME. Format tidak membuktikan akun manusia, sehingga owner tetap memastikan akun pribadinya benar.

Sales flag aplikasi tetapfalse. Checkout memerlukan product published/sale_enabled, PoC gate, versi aktif sudah dirilis dengan ukuran/checksum file cocok, konfigurasi harga/pembayaran dan Telegram valid. Indikator memiliki gate terpisah. Jangan membuka penjualan hanya karena fixture/unit test lulus.

Migration additive menambah checkout_quotes dan kolom order. Harga/coverage/payment disimpan sebagai snapshot server. Quote expired atau data renewal berubah memerlukan kuotasi baru; respons hilang memakai URL intent yang sama untuk Cek Status. Jangan mengirim ulang mutasi dengan UUID baru otomatis. Tidak ada bank transfer/API Telegram yang dijalankan aplikasi.

Rincian hasil dan browser test-only router: [`technical/validation/TG013-TG015.md`](technical/validation/TG013-TG015.md). PHP memerlukan BCMath; Docker development sudah menyediakannya dan Composer mendeklarasikannya.

## Pembayaran, lisensi dan unduhan TG-016–TG-018

`/admin/pesanan` menyediakan pemeriksaan dan konfirmasi/penolakan pembayaran. Admin wajib TOTP terverifikasi dan reauthentication masih berlaku; konfirmasi memerlukan cek dana, referensi dan catatan verifikasi. Dana tetap diperiksa manusia; Telegram tidak mengubah status. Konfirmasi ulang order lunas mengembalikan receipt lama. Pembayaran lewat deadline ditangani manual, bukan dipaksakan menjadi lunas.

`/admin/lisensi` menyediakan suspend, aktivasi kembali sebelum expiry, dan revoke permanen dengan kategori alasan. Retry UUID sama tidak mengulang perubahan atau membatalkan perubahan lebih baru. Renewal menjaga key lama. Key terenkripsi tidak ditampilkan di panel admin; portal key/riwayat lengkap mengikuti TG-021.

Invoice lunas pemilik menyediakan unduhan EX5 versi aktif melalui URL signed maksimal5 menit, dibatasi expiry. Setiap unduhan memeriksa owner, status, fingerprint lisensi, versi produk, ukuran dan SHA-256. Storage harus di luar public; file tidak dieksekusi server. Suspend/revoke menutup permintaan unduhan berikutnya; byte yang sudah ditransmisikan tidak dapat ditarik. Revocation EA offline masih memerlukan API dan PoC terminal TG-019/TG-020.

Bukti dan fixture sintetis: [`technical/validation/TG016-TG018.md`](technical/validation/TG016-TG018.md). Tidak ada migration baru dalam kelompok ini. Flag penjualan produksi tetapfalse; menjalankan tes tidak mengaktifkan bisnis.

## API lisensi TG-019 dan handoff TG-020

API stateless: `POST /api/v1/licenses/verify`, HTTPS JSON maksimal4KiB. Semua verdict bisnis HTTP200 memakai Ed25519 atas byte JSON persis; validasi error generik422, nonce replay409, rate limit429 dan kegagalan signer503. Endpoint deactivate tetap404. Format lengkap ada di `technical/API_CONTRACT.md`.

Runtime PHP memerlukan ext-sodium (sudah tersedia di image development). Konfigurasi `TG_LICENSE_ED25519_KID` dan `TG_LICENSE_ED25519_PRIVATE_KEY_PATH` diisi operator dengan pasangan yang disetujui; path file harus di luar public, dapat dibaca proses app, mode0600/0400 tanpa akses group/other. Isi file adalah base64 secret key Ed25519 libsodium64byte; bukan seed32byte atau PEM. Server memeriksa konsistensi secret/public. Tidak ada key runtime bawaan, pembangkitan key produksi otomatis, atau perubahan .env existing. Public key harus dipin di EA sebelum pergantian kid; jangan memakai key RFC8032 fixture untuk runtime.

Rate limit60/jam per fingerprint key dan120/jam per IP; cache bucket memakai HMAC, bukan key/IP mentah. Nonce base64url canonical minimal16byte, skew timestamp ±300detik; SHA-256 nonce di-insert unik atomik dengan TTL10menit. `php artisan tg:prune-api-nonces` dry run; `--apply` hanya menghapus yang expired, scheduler TG-035 masih terpisah. Aplikasi membaca status lisensi langsung dari DB, bukan cache verdict lama. `license_logs` menyimpan hasil, versi, account masked, hash IP dan requestID; tidak menyimpan raw key/nonce/IP.

Tes memakai signer sementara di luar webroot yang dihapus sesudah tes. Reproduksi vector: `APP_ENV=testing DB_DATABASE=tg_test DB_URL= php tests/Support/generate-license-vectors.php` dalam container app. Verifikasi independen: `python3 tests/Support/verify-license-vectors.py` (paket cryptography diperlukan). Semua fixture memakai public test material. Ini membuktikan PHP→Python, bukan PHP→MQL5. Source EA, pure MQL5 verifier dan terminal belum tersedia; `mt5/INTEGRATION.md` berisi checklist integrasi dan test matrix. TG-019/TG-020 belum memenuhi penerimaan penuh; sales flag tetapfalse.

## Produk Saya dan operasi manual TG-021–TG-022

`/member/produk` dan detail `/member/produk/{licenseId}` hanya client pemilik aktif. Detail menampilkan key (tidak disimpan dalam public property Livewire), expiry/sisa hari, status, unduhan sesuai policy, terakhir terhubung dan50verifikasi melalui Lihat Riwayat. Copy key mengikuti hasil Promise clipboard; bila ditolak, teks dipilih untuk salin manual. IP,nonce,signature dan log client lain tidak ditampilkan.

Pengajuan ganti akun membutuhkan akun/server baru; tipe real/demo dan key tetap. Satu request pending per lisensi, dengan row lock lisensi. Persetujuan admin wajib reauth, alasan, cek identity snapshot/uniqueness dan status aktif/belum expired. Default satu perpindahan disetujui per tahun layanan kalender WIB. Renewal dini tidak mereset kuota tahun berjalan; rejection tidak memakan kuota. Audit menyimpan period_year dan hash identity, bukan nomor akun. Akun order historis tetap; request ulang intent sama atau approval lama tidak mengembalikan akun sebelumnya. URL download lama berubah fingerprint; terminal lama ditolak API pada verifikasi online berikutnya. Batas offline tetap menunggu PoC.

Detail pesanan admin memiliki rencana refund initiated, lalu completed hanya setelah checkbox transfer manual dan referensi. Metode saat ini manual_bank; jumlah parsial boleh tetapi total initiated+completed tidak melebihi nilai paid order, memakai BCMath dan lock order. Rejected melepas reservasi, bukan menyatakan dana terkirim. Tidak ada transfer bank/API eksternal; refund tidak mengubah paid historis, status lisensi, key atau ledger rebate. Revoke diputuskan terpisah. Audit dalam transaksi yang sama; respons hilang menggunakan Cek Status GET, bukan intent baru.

Bukti aktual: [`technical/validation/TG019-TG022.md`](technical/validation/TG019-TG022.md). Fixture browser `seed-license-operations.php` dan `license-operations.cjs` mengikuti router e2e/tg_test yang terisolasi dari aplikasi normal. Tidak ada migration baru dalam kelompok ini.

## Fase 3 — IB, rebate dan pencairan (TG-023–TG-030)

Routes client: `/member/rebate/akun`, `/member/rebate`, `/member/penarikan`. Routes admin: `/admin/broker`, `/admin/rebate/akun`, `/admin/rebate`, `/admin/penarikan` dan detail `/{id}`. Seluruh mutasi admin Fase 3 memerlukan TOTP dan reauthentication; client hanya mengakses data sendiri. IB tidak mensyaratkan pembelian atau lisensi EA.

Isi `TG_BROKER_REFERRAL_HOSTS` dengan hostname HTTPS persis yang disetujui owner sebelum menyimpan broker. Admin dapat upload logo, mengisi tarif/syarat/tanggal efektif dan menonaktifkan tanpa menghapus histori. Upload logo memakai batas/re-encode gambar produk yang sudah ada. Estimasi publik tidak menambah wallet. Bukti pengajuan IB berupa deskripsi dan referensi konfirmasi broker, bukan password/OTP. Admin wajib memeriksa bukti kepemilikan/referral secara manual; review note terpisah dari bukti awal. Status approved/rejected terminal, retry identik mengembalikan status lama.

Input rebate menggunakan angka terverifikasi dari broker, bukan otomatis lots*tarif atau input client. Referensi sumber wajib unik per akun, memo wajib. Koreksi memasukkan reversal+replacement dalam satu transaksi, tidak mengubah row lama; isi nominal/lot pengganti akhir, bukan delta. Untuk koreksi lanjutan pilih replacement terakhir. Saldo = net ledger − requested/processing − paid. USD dihitung dengan BCMath 4 desimal, kurs 6 desimal, IDR half-up 2 desimal; tampilan USD 2 desimal. Semua operasi uang memakai wallet lock yang sama, lalu current locking reads untuk mencegah stale snapshot.

Pada `/admin/penarikan`, isi Setting terenkripsi `rebate.payout`: `minimum_usd` string positif (maksimal16 digit integer/4 pecahan), `fx_rate_idr` string positif (14/6), `enabled` boolean. Tidak ada nilai produksi bawaan dan tidak ada setting owner yang diisi otomatis. Quote client terautentikasi dan encrypted berlaku5menit; mengikat identitas, tujuan, UUID intent dan semua nominal. Kurs/minimum preview dihormati selama quote sah; pause enabled=false memblokir reservasi baru. Existing intent tetap mengembalikan receipt setelah expiry/pause. Setelah request tersimpan, amount/kurs/tujuan tidak diubah oleh setting berikutnya.

Tandai Diproses sebelum transfer manual agar client tidak bisa membatalkan selama pembayaran. Sesudah transfer nyata berhasil, isi cek dana, referensi dan tanggal WIB lalu Catat sudah dibayar. Backend menerima requested/processing→paid sesuai A15, tetapi UI mengarahkan processing dahulu. Rejected/cancelled melepas reservasi sekali; paid mempertahankan pengurangan total tanpa debit ulang. Tidak ada API bank, pengiriman uang, broker API/CSV atau notifikasi otomatis. Full destination hanya pada modal pemilik dan detail admin dengan reauth; list masked, penyimpanan encrypted, audit/log tidak berisi tujuan/bukti mentah.

`docker compose exec app php artisan tg:reconcile-rebates` memeriksa konsistensi wallet dan snapshot IDR tanpa mengubah data. Exit nonzero memerlukan review manual; jangan memperbaiki ledger dengan UPDATE/DELETE. Scheduler terpisah pada TG-035. Migration Fase3 hanya menambah nullable trading_accounts.verification_note; jalankan migrate biasa tanpa reset database development.

Validasi: [`technical/validation/TG023-TG030.md`](technical/validation/TG023-TG030.md). Fixture browser memakai `seed-rebates.php`, `router-rebates.php` dan `rebates.cjs` dengan e2e/tg_test serta storage terpisah; guard tidak mengaktifkan sales EA. Jangan menjalankan browser seed dan PHP tests bersamaan. Konfigurasi bisnis/transfer nyata tetap menjadi tanggung jawab operator setelah verifikasi kesiapan.
