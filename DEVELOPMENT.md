# Development lokal — Trader Garuda

Panduan ini mencakup TG-004–TG-012: fondasi Laravel 13, Livewire 4, Tailwind CSS 4, database, autentikasi, layout bersama, build aset dan pengujian. Baca `AGENTS.md`, `SCOPE_OF_WORK.md` dan kontrak di `technical/` sebelum mengembangkan modul berikutnya. Gunakan checkout yang sudah ada; setiap cloud task telah terisolasi, sehingga tidak perlu membuat Git worktree tanpa permintaan pengguna.

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
