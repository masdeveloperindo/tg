# ENVIRONMENT.md — Kontrak Konfigurasi Aplikasi (software only)

**Owner:** tim pengembang untuk kode, contoh konfigurasi, dan pengujian; **owner infrastruktur:** pihak lain untuk provisioning dan operasi host. Lihat `../SCOPE_OF_WORK.md`.

## Pilihan yang harus didukung aplikasi

- Laravel 13, Livewire 4, Tailwind CSS 4; fondasi project memakai PHP 8.4 sesuai `composer.json`/lockfile (framework sendiri mendeklarasikan `^8.3`); MySQL 8.4 LTS (InnoDB/utf8mb4). Tim software membuat migration dan query, bukan memasang layanan DB produksi.
- Parameter `APP_URL`, `APP_ENV`, `APP_DEBUG`, driver session/cache, dan disk storage dibaca dari `.env`; tidak hardcoded. Staging/produksi disediakan pihak infra.
- `SESSION_DRIVER=database`, `CACHE_STORE=database`, `QUEUE_CONNECTION=sync` untuk MVP; tidak wajib Redis/worker.
- Semua transaksi dan API lisensi tetap sah tanpa menunggu job Scheduler; tanggal kedaluwarsa diverifikasi on-request.
- Laravel Scheduler mendefinisikan job teruji untuk pembersihan nonce/log, housekeeping, dan deteksi flag anomali. Pemanggilan cron oleh host ditangani infra.
- Simpan file EX5, bukti bayar dan bukti pencairan pada disk privat yang ditentukan konfigurasi; media publik dipisah. Keputusan storage fisik/backup adalah infra.

## Kontrak variabel

Gunakan `../.env.example` (tanpa secret asli). APP_URL adalah parameter, bukan pekerjaan membeli/mengelola domain. `TG_TELEGRAM_USERNAME` hanya tautan chat pribadi; tidak ada bot/webhook/API Telegram. `TG_LICENSE_ED25519_PRIVATE_KEY_PATH` harus menunjuk materi kunci di lokasi privat yang disediakan pihak infra, tanpa nilai sensitif di repo.

## Kontrak dari software ke infra

1. **Runtime**: PHP dan ekstensi sesuai composer.lock; database kompatibel; jalur storage publik/privat; konektivitas outbound EA ke API.
2. **Host/network**: DNS, domain, Nginx/PHP-FPM, sertifikat TLS, firewall/WAF, ketersediaan host lama saat migrasi; **infra yang mengurus**.
3. **Secret**: infra menyediakan variabel produksi/kunci secara aman, memisahkan tiap environment, dan mengatur akses.
4. **Cron**: infra memanggil `php artisan schedule:run` sesuai interval terdokumentasi, dan memantau keberhasilannya.
5. **Backup/restore**: infra menjalankan backup terenkripsi untuk DB dan file privat, uji restore, retensi dan monitoring; software hanya memberi tes/query untuk verifikasi integritas setelah data dipulihkan.
6. **Deploy/cutover**: infra menjalankan deployment, migration/maintenance sesuai runbook yang disepakati, mengamankan host, serta menangani rollback host.

## Bukti yang dimiliki tim software

- Fresh clone, dependency install/build, migration dan seed test lokal; `php artisan test` + test E2E; route list; pengujian policy, ledger, idempotency dan MQL5.
- Manifest release, catatan migrasi/rollback aplikasi, contoh konfigurasi, test report, health endpoint dan matriks dependensi.
- Smoke test pada staging **apabila disediakan** tim infra. Jika belum tersedia, tandai sebagai dependensi eksternal, bukan task untuk memasang server.

Baca `../RELEASE_HANDOFF.md` untuk formulir serah terima.

## Workflow development fondasi

Lihat [`DEVELOPMENT.md`](../DEVELOPMENT.md) untuk setup lokal teruji, build, startup dan pengujian. `bash scripts/setup-local.sh` menyiapkan dependency development dalam container terisolasi, bukan provisioning host/produksi. Image MySQL lokal membuat database aplikasi `tg` dan database test `tg_test`; tidak ada port DB yang dipublikasikan ke host.

`.env.example` tidak memuat nilai rahasia. Helper hanya membuat `.env` jika belum ada, dengan `APP_KEY` dan password DB acak tanpa mencetak nilainya; konfigurasi existing dipertahankan. Default session/cache database, queue sync, disk lokal privat; `TG_PRIVATE_STORAGE_PATH` dapat mengubah lokasi privat tanpa perubahan kode. Cookie secure harus diaktifkan ketika memakai HTTPS staging/produksi. Waktu koneksi MySQL dan aplikasi UTC; zona tampilan Asia/Jakarta.

PHPUnit memaksa database `tg_test`, mengosongkan `DB_URL` dan memeriksa target sebelum migration/reset. Sejak TG-006, tabel framework `sessions`, `cache` dan `cache_locks` disertai 30 tabel domain dan satu migration FK tertunda; bukti di `validation/TG006.md`. Autentikasi/TOTP sudah tersedia melalui TG-007; lihat `validation/TG007.md`. Jangan mengarahkan test ke database produksi atau data penting.

Dependensi eksternal tetap: data owner dan keputusan harga/rekening/Telegram (TG-001), persetujuan legal/IB (TG-002), source EA berizin dan terminal MT5 untuk PoC (TG-003/TG-020), serta staging/domain API lama/secret produksi/storage fisik dari pihak infra. Penjualan lisensi dan indikator tetap nonaktif selama PoC belum terbukti; fondasi tidak mengirim pesan Telegram atau menerima pembayaran.

Layout bersama TG-008 dan pemeriksaan Chromium 360/768/1280 tersedia; lihat `DESIGN_SYSTEM.md` dan `validation/TG008.md`. Server e2e wajib memakai `--no-reload` agar override environment database dipertahankan; prosedur terisolasi ada di `../DEVELOPMENT.md`.

TG-011 memerlukan ekstensi GD (dideklarasikan Composer) untuk re-encode gambar; Docker development menyediakannya. PHP upload_max_filesize20M/post_max_size24M dan temporary upload admin-only sudah diselaraskan; lihat `validation/TG011.md`. File INI di image harus terbaca oleh user non-root.

## Kontrak scheduler dan health — TG-035 (7 November 2026)

Aplikasi menyediakan lima jadwal Laravel dengan withoutOverlapping/onOneServer. Operator infra perlu memanggil `php artisan schedule:run` sekali per menit dari root release aktif, memakai PHP/runtime/env yang sama dengan aplikasi. Gunakan satu shared cache database untuk mutex. Tidak perlu queue worker MVP. Task software ini hanya mendaftarkan/menguji schedule, tidak memasang atau menjalankan cron host.

| Job | Jadwal aplikasi | Efek |
| --- | --- | --- |
| tg:expire-orders | Tiap menit | Hanya waiting lewat deadline, audit system, tidak mengubah paid |
| tg:detect-license-anomalies | Tiap10menit | Flag dedup untuk review manusia; tidak auto-suspend |
| tg:prune-api-nonces --apply | Tiap10menit | Hanya nonce expired |
| tg:prune-license-logs --apply | 02:00 WIB | Telemetry>90hari dan rawIP>30hari; audit finansial tidak dihapus |
| tg:reconcile-rebates | 02:30 WIB | Read-only, nonzero bila mismatch; perlu review manusia |

schedule:list dapat menampilkan ekspresi/next_due yang dikonversi UTC; sumber daily jobs memakai Asia/Jakarta. Periksa cache lock dan output command saat insiden, jangan melakukan auto-fix saldo. Kebutuhan log/storage: private directory di luar public, writable untuk upload/tmp/log/cache; pertahankan APP_KEY dan file versi immutable. Rotasi log/kapasitas, backup DB+private files serta secret recovery diserahkan kepada infra, bukan dijalankan task software.

GET /health berada di luar session middleware. Operator menyediakan TG_HEALTH_TOKEN melalui secret configuration, minimal32karakter acak, tanpa nilai bawaan atau commit .env. Probe mengirim Authorization: Bearer melalui koneksi internal/HTTPS; jangan taruh token di query/URL/log. Missing/wrong token404; authorized DB+cache sehat200 {status:ok}; dependency gagal503 {status:unavailable}, tanpa detail versi/credential dan tanpa cookie. Proxy perlu meneruskan Authorization. Host monitor/token/cron belum diaktifkan oleh software task.
