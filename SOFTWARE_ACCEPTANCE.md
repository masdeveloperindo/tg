# SOFTWARE_ACCEPTANCE.md — Checklist Penerimaan Tim Software

**Awal: semua belum diuji / BLOCKED.** Jangan mengklaim lulus sebelum bukti implementasi Laravel dan integrasi MQL5 tersedia. Checklist ini hanya untuk penyerahan perangkat lunak; bukan izin go-live.

| ID | Prioritas | Acceptance software | Bukti wajib | Status |
|---|---|---|---|---|
| SA-01 | P0 | Laravel 13/Livewire 4 dapat di-build dan fresh migration MySQL 8.4 lulus | CI build, migration test | BLOCKED |
| SA-02 | P0 | Autentikasi/TOTP admin, RBAC, CSRF, rate limit dan akses silang teruji | Auth/security tests | BLOCKED |
| SA-03 | P0 | Checkout, admin manual cek transfer, status lunas dan lisensi dibuat atomik sekali | E2E + race test | BLOCKED |
| SA-04 | P0 | Telegram hanya link akun pribadi; tidak ada bot/webhook/otomatis | Route/code audit | BLOCKED |
| SA-05 | P0 | Harga/renewal/expiry sesuai PRD, satu key per akun | Price/renewal tests | BLOCKED |
| SA-06 | P0 | EA MT5 memverifikasi signature, replay/mismatch/revoke/expiry/offline; indikator dibatasi sampai PoC | Terminal MT5 evidence | BLOCKED |
| SA-07 | P0 | EX5 storage logical privat, izin unduh dan checksum | Authorization tests | BLOCKED |
| SA-08 | P0 | IB client multi-broker, rebate ledger append-only dan riwayat cocok dengan admin | Cross-session tests | BLOCKED |
| SA-09 | P0 | Withdrawal concurrent tidak double/negatif; proses admin manual idempoten | DB concurrency tests | BLOCKED |
| SA-10 | P0 | Refund manual, audit transaksi dan rekonsiliasi konsisten | Workflow tests | BLOCKED |
| SA-11 | P0 | 71 URL/endpoint dan action server relevan bekerja, policy benar | Route + E2E test | BLOCKED |
| SA-12 | P0 | Modal, loading nyata, timeout status-unknown, progress upload benar | Browser/throttle tests | BLOCKED |
| SA-13 | P0 | Laravel Scheduler jobs, health endpoint, log aplikasi bebas rahasia | Unit/feature tests | BLOCKED |
| SA-14 | P0 | Paket rilis, `.env.example`, migration plan dan handoff ke infra lengkap | `RELEASE_HANDOFF.md` | BLOCKED |
| SA-15 | P0 | UAT user dan admin berdasarkan alur PRD pada lingkungan yang tersedia | UAT evidence | BLOCKED |
| SA-16 | P1 | Responsif/a11y, SEO, panduan, edukasi, testimoni, pengumuman | Screenshot + E2E | BLOCKED |

## Dependensi di luar scope

Kesiapan VPS, DNS, SSL/TLS, Nginx, server DB, cron host, backup/restore fisik, hosting monitoring, deploy/cutover, izin broker dan keputusan bisnis **bukan task software**. Jika prasyarat untuk tes integrasi belum ada, tulis `EXTERNAL DEPENDENCY` dan lanjutkan tes aplikasi lokal yang tidak terhalang.

**Status akhir software:** `SOFTWARE READY FOR HANDOFF` jika semua P0 aplikasi PASS; keputusan `GO-LIVE` ditentukan owner/infra/legal melalui `PRODUCTION_GATE.md`.
