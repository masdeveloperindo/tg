# ENVIRONMENT.md — Kontrak Konfigurasi Aplikasi (software only)

**Owner:** tim pengembang untuk kode, contoh konfigurasi, dan pengujian; **owner infrastruktur:** pihak lain untuk provisioning dan operasi host. Lihat `../SCOPE_OF_WORK.md`.

## Pilihan yang harus didukung aplikasi

- Laravel 13, Livewire 4, Tailwind CSS 4; PHP minimal 8.3; MySQL 8.4 LTS (InnoDB/utf8mb4). Tim software membuat migration dan query, bukan memasang layanan DB.
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
