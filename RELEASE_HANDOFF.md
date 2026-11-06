# RELEASE_HANDOFF.md — Serah Terima Kode ke Tim Infrastruktur

Dokumen ini **bukan instruksi penugasan infrastruktur kepada developer**. Pekerjaan developer selesai pada kode yang dapat dibangun, lulus pengujian, dan artefak yang bisa diserahkan. Pelaksanaan deployment, DNS, TLS, hosting dan backup dikerjakan pihak lain.

## Yang diserahkan tim software

1. Source Laravel/Livewire dan `composer.lock`, aset frontend dan langkah build yang teruji.
2. File migration, skema DB, indeks/constraint, serta catatan migrasi yang reversible bila aman; pantangan rollback destruktif keuangan.
3. `.env.example` tanpa secret, daftar env wajib, kebutuhan storage privat/publik dan hak akses logical.
4. Definisi Laravel Scheduler jobs dan interval, health endpoint dan struktur log/error aplikasi.
5. API_CONTRACT.md + test vector signature MT5, URL aplikasi/API yang dibutuhkan (alamat host disediakan infra).
6. Hasil `php artisan test`, E2E, concurrency, security aplikasi, PoC MT5, dan `SOFTWARE_ACCEPTANCE.md` yang telah ditandatangani.
7. Petunjuk `php artisan migrate --force` dan `php artisan optimize` sebagai **referensi untuk operator infra**, bukan eksekusi yang ditugaskan pada developer.

## Yang harus disediakan/dijalankan pihak infra (di luar scope software)

- VPS/OS/runtime PHP, host MySQL, DNS/domain, web server/reverse proxy, SSL/TLS, firewall/WAF dan egress jaringan.
- Staging dan produksi; konfigurasi APP_URL, rahasia/kunci, filesystem dan hak akses fisik.
- Instalasi cron host untuk Laravel Scheduler; monitoring uptime/host, error 5xx dan kapasitas.
- Backup terenkripsi DB+file privat, restore drill, retensi, snapshot dan rencana rollback host.
- Deployment/cutover, smoke test host, mitigasi downtime dan menjaga host API lisensi lama ketika migrasi.

## Kriteria serah terima

| Item | Bukti | Penerima | Status |
|---|---|---|---|
| Source dan versi release | Commit tag, checksum artefak | Infra | Pending |
| Kontrak environment & migrations | `.env.example`, migration plan | Infra | Pending |
| Scheduler, health, logs | Command list + documented intervals | Infra | Pending |
| QA fungsional dan keamanan aplikasi | CI, E2E, MT5 test, `SOFTWARE_ACCEPTANCE.md` | Owner | Pending |
| Kebutuhan eksternal | daftar domain/TLS/DB/backup/monitoring | Infra | Pending |

**Keputusan:** Setelah semua item software lulus, status `SOFTWARE READY FOR HANDOFF`; transaksi asli dibuka hanya oleh owner sesudah infra/legal dan kriteria go-live umum juga lulus.

## Paket operasional aplikasi dari Fase 4

Source scheduler ada pada routes/console.php. Lima command dan cadence tercantum di technical/ENVIRONMENT.md; bukti schedule:list dan health tests di technical/validation/TG031-TG037.md. Infra perlu shared database cache, pemanggilan schedule:run setiap menit dari release aktif, token health yang disuplai melalui secret config, penerusan Authorization dan writable private storage/log. Tidak ada kebutuhan queue worker MVP. Seluruh perintah tetap melalui runtime/env aplikasi dan hak file minimum.

Migration Fase4 hanya menambah review_note nullable pada license_flags. Terapkan migrate biasa; jangan migrate:fresh/reset pada data bisnis. Pertahankan APP_KEY, encryption/signing keys dan version files saat rilis; recovery/backup host dilakukan infra sesuai prosedur yang disahkan, tidak disediakan sebagai aksi otomatis aplikasi.

Health minimal hanya memeriksa DB/cache; bukan klaim kesiapan EA, bank, konten legal atau sales gate. Tanpa token sah endpoint404 memang diharapkan. Probe internal/HTTPS menggunakan bearer header, bukan query. Reconcile hanya mendeteksi mismatch dan tidak memindah uang/memperbaiki saldo. Flag anomali tidak mensuspend otomatis. Ops harus meninjau kegagalan job/mutex/cache, bukan mengulang transfer manual.

Owner masih perlu legal/about/contact dan sumber/consent testimoni nyata, konfigurasi bisnis yang sah serta UAT. Source EA/verifier dan terminal ditunda sesuai owner; software tidak mengaktifkan sales flags. Paket Fase4 ini belum menyatakan seluruh handoff/produksi siap dan tidak memasang cron, monitor, backup atau deploy host.
