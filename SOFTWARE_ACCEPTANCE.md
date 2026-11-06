# SOFTWARE_ACCEPTANCE.md — Checklist Penerimaan Tim Software

**Pembaruan Fase 5, 7 November 2026:** bukti lokal ada di `technical/validation/TG038-TG044.md`; PASS lokal tidak menggantikan CI remote, staging, terminal EA atau UAT owner. Checklist ini bukan izin go-live.

| ID | Prioritas | Acceptance software | Bukti wajib | Status |
|---|---|---|---|---|
| SA-01 | P0 | Laravel 13/Livewire 4 dapat di-build dan fresh migration MySQL 8.4 lulus | CI build, migration test | LOCAL PASS; CI remote belum diverifikasi |
| SA-02 | P0 | Autentikasi/TOTP admin, RBAC, CSRF, rate limit dan akses silang teruji | Auth/security tests | PASS lokal |
| SA-03 | P0 | Checkout, admin manual cek transfer, status lunas dan lisensi dibuat atomik sekali | E2E + race test | PASS lokal (sales fixture QA) |
| SA-04 | P0 | Telegram hanya link akun pribadi; tidak ada bot/webhook/otomatis | Route/code audit | PASS |
| SA-05 | P0 | Harga/renewal/expiry sesuai PRD, satu key per akun | Price/renewal tests | PASS lokal |
| SA-06 | P0 | EA MT5 memverifikasi signature, replay/mismatch/revoke/expiry/offline; indikator dibatasi sampai PoC | Terminal MT5 evidence | DEFERRED — source EA/MT5 belum tersedia |
| SA-07 | P0 | EX5 storage logical privat, izin unduh dan checksum | Authorization tests | PASS lokal |
| SA-08 | P0 | IB client multi-broker, rebate ledger append-only dan riwayat cocok dengan admin | Cross-session tests | PASS lokal |
| SA-09 | P0 | Withdrawal concurrent tidak double/negatif; proses admin manual idempoten | DB concurrency tests | PASS lokal — dua proses/koneksi MySQL8.4 |
| SA-10 | P0 | Refund manual, audit transaksi dan rekonsiliasi konsisten | Workflow tests | PASS lokal |
| SA-11 | P0 | 71 URL/endpoint dan action server relevan bekerja, policy benar | Route + E2E test | PASS lokal 71 path; staging pending |
| SA-12 | P0 | Modal, loading nyata, timeout status-unknown, progress upload benar | Browser/throttle tests | PASS lokal; staging pending |
| SA-13 | P0 | Laravel Scheduler jobs, health endpoint, log aplikasi bebas rahasia | Unit/feature tests | PASS lokal |
| SA-14 | P0 | Paket rilis, `.env.example`, migration plan dan handoff ke infra lengkap | `RELEASE_HANDOFF.md` | PARTIAL — kandidat lokal; penerimaan infra pending |
| SA-15 | P0 | UAT user dan admin berdasarkan alur PRD pada lingkungan yang tersedia | UAT evidence | BLOCKED — UAT owner/staging belum tersedia |
| SA-16 | P1 | Responsif/a11y, SEO, panduan, edukasi, testimoni, pengumuman | Screenshot + E2E | PASS lokal; konten/legal nyata pending |

## Dependensi di luar scope

Kesiapan VPS, DNS, SSL/TLS, Nginx, server DB, cron host, backup/restore fisik, hosting monitoring, deploy/cutover, izin broker dan keputusan bisnis **bukan task software**. Jika prasyarat untuk tes integrasi belum ada, tulis `EXTERNAL DEPENDENCY` dan lanjutkan tes aplikasi lokal yang tidak terhalang.

**Status akhir software:** `SOFTWARE READY FOR HANDOFF` jika semua P0 aplikasi PASS; keputusan `GO-LIVE` ditentukan owner/infra/legal melalui `PRODUCTION_GATE.md`.

**Keputusan aktual: NOT READY / transaksi nyata tetap tertutup.** SA-06 dan SA-15 belum lulus; SA-01/11/12/14 masih memiliki tindak lanjut eksternal. Tidak ada persetujuan owner/infra yang dibuat oleh developer. Rencana UAT: `technical/UAT_CHECKLIST.md`.
