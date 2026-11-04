# PRODUCTION_GATE.md — Go-live Lintas Tim Trader Garuda v1.7.1

**Status awal:** BLOCKED — baru PRD + referensi HTML, belum ada implementasi Laravel/MQL5 yang lolos pengujian produksi.

**Kepemilikan:** gate ini milik **owner/infra/legal secara kolektif**. **Tim software hanya berkewajiban menuntaskan `SOFTWARE_ACCEPTANCE.md`**; G-02 (DNS/TLS), G-20 (backup fisik) dan G-29 (recovery host) adalah *EXTERNAL INFRA* dan tidak boleh masuk backlog developer.

**Cara menilai:** setiap kriteria diberi `PASS`, `FAIL`, atau `BLOCKED` **beserta bukti**, tanggal, environment, dan penanggung jawab. Jangan tandai PASS hanya berdasarkan demo HTML atau kesan visual. **Semua P0 wajib PASS sebelum transaksi nyata diaktifkan.**

| ID | Level | Kriteria go-live | Status awal | Bukti yang harus dilampirkan |
|---|---|---|---|---|
| G-01 | P0 | Aplikasi Laravel 13 + Livewire 4 + Tailwind 4 benar-benar berjalan pada PHP/DB produksi | BLOCKED | `php -v`, composer.lock, migration & test logs |
| G-02 | P0 / INFRA | DNS, HTTPS/TLS sertifikat, web server dan hardening host oleh tim infra; software hanya mendukung APP_URL & secure session | EXTERNAL | bukti deployment/konfigurasi dari infra |
| G-03 | P0 | Auth admin TOTP, rate limit login, CSRF, policies client/admin semua route/action | BLOCKED | automated auth/authz tests |
| G-04 | P0 | Isolasi data: client A tidak melihat/mengubah lisensi, pesanan, rebate dan file client B | BLOCKED | negative access tests |
| G-05 | P0 | Telegram chat pribadi saja, username riil benar, tanpa bot/webhook/auto message | BLOCKED | route/config audit, manual test |
| G-06 | P0 | Admin cek mutasi rekening lalu konfirmasi di `/admin/pesanan`; pesanan mengaktifkan key sekali | BLOCKED | order E2E + transaction/idempotency test |
| G-07 | P0 | Double click, race concurrent, network timeout, retry tidak membuat license/key ganda | BLOCKED | concurrency test, audit DB |
| G-08 | P0 | Harga dan `coverage_year` benar termasuk Desember/renewal, tanggal akhir tampil sebelum bayar | BLOCKED | test matrix price/renewal |
| G-09 | P0 | Private `.ex5`, verifikasi pemilik, signed URL terbatas, checksum, semua file upload terjaga | BLOCKED | download and path abuse tests |
| G-10 | P0 | EA nyata berhasil memverifikasi lisensi: valid/salah akun/expired/revoked/suspend/replay | BLOCKED | terminal MT5 log + signed-response test vector |
| G-11 | P0 | Offline grace 48 jam & expiry, WebRequest allowlist, versi/retry diuji pada MT5 nyata | BLOCKED | integrated network tests |
| G-12 | P0 | Indikator berlisensi tidak dapat dipesan sebelum PoC dan release flag | BLOCKED | catalog/checkout authorization test |
| G-13 | P0 | Client IB melihat semua broker akun, hasil rebate riil dan status payout berdasarkan DB | BLOCKED | admin↔client cross-session test |
| G-14 | P0 | Rebate immutable, referensi duplikat ditolak, koreksi tercatat | BLOCKED | ledger tests |
| G-15 | P0 | Dua pengajuan withdrawal serentak tidak membuat saldo minus; reserve/release/pay once | BLOCKED | DB concurrency & idempotency tests |
| G-16 | P0 | Admin pembayaran withdrawal hanya dicatat setelah transfer manual, bukti/ref wajib | BLOCKED | workflow tests & audit |
| G-17 | P0 | Refund terpisah dari revoke dan audit merekam aktor & perubahan | BLOCKED | workflow/audit tests |
| G-18 | P0 | Modal kustom, loading asli Livewire, upload % terukur, timeout status-unknown | BLOCKED | Playwright E2E + throttling tests |
| G-19 | P0 | Seluruh route PRD dan komponen penting tersedia; bukan tombol demo/dekoratif | BLOCKED | route list + E2E smoke |
| G-20 | P0 / INFRA | Backup terenkripsi dan uji restore fisik oleh tim infra; software menyediakan query validasi data | EXTERNAL | bukti backup/restore tim infra |
| G-21 | P0 / SOFTWARE | Kode health endpoint, scheduler jobs dan audit/error log aman; monitoring host disediakan infra | BLOCKED | automated application observability tests |
| G-22 | P0 | Izin/ketentuan broker IB, disclaimer risiko, privasi, syarat/refund ditinjau | BLOCKED | legal approval/documentation |
| G-23 | P0 | Telegram username asli, rekening, harga, persentase diskon, kurs, min withdrawal terisi | BLOCKED | admin config screenshot redacted |
| G-24 | P0 | Tidak ada seed/demo client/testimoni/statistik profit palsu di produksi | BLOCKED | DB review & deploy smoke |
| G-25 | P0 | UAT pembeli EA dan client IB pada staging dengan uang simulasi & akun MT5 test berhasil | BLOCKED | signed UAT record |
| G-26 | P1 | Responsif 360/768/1280, accessibility modal/keyboard, warna sesuai reference | BLOCKED | screenshot diff/manual QA |
| G-27 | P1 | SEO meta, sitemap, robots dan noindex privat benar | BLOCKED | HTML/head/sitemap audit |
| G-28 | P1 | Tugas Hari Ini, panduan bergambar, testimoni manual, pengumuman lengkap | BLOCKED | functional test |
| G-29 | P1 / INFRA | Runbook backup/restore dan rollback host oleh infra; software menyerahkan migration plan | EXTERNAL | rehearsal operasi oleh infra |
| G-30 | P1 / SHARED | Software: LCP/API/query performance tests; infra: kapasitas server dan host | BLOCKED | test laporan software dan uji kapasitas infra terpisah |

## Bukti yang SUDAH dimiliki dalam paket ini

- HTML referensi: **20 halaman** (19 contoh halaman + index) berhasil dirender dan **6 alur simulasi** diuji ulang, serta **8 tampilan mobile** pada 390 px lolos pemeriksaan tidak melebar. Ini **BUKAN** bukti P0 produksi.
- PRD v1.6, prompt implementasi, route mapping, screenshot referensi sudah tersedia.

## Persetujuan go-live

| Bidang | Penanggung jawab | Tanggal | Status |
|---|---|---|---|
| Pemilik bisnis (harga, rekening, Telegram pribadi) | Belum ditentukan | — | BLOCKED |
| Developer / QA (aplikasi & tes) | Belum ditentukan | — | BLOCKED |
| MQL5/EA (integrasi terminal nyata) | Belum ditentukan | — | BLOCKED |
| Operasional server/backup (pihak eksternal) | Belum ditentukan | — | EXTERNAL |
| Legal/ketentuan broker | Belum ditentukan | — | BLOCKED |

**Keputusan saat dokumen dibuat:** **PRODUCTION GO-LIVE: NOT APPROVED**. Ini status jujur, bukan kegagalan dokumen PRD.


## Lampiran evidence teknis v1.7

- Gate data: `DATABASE_SCHEMA.md` diterjemahkan ke 30 migration domain, FK/unique, dan query locking nyata yang diuji di MySQL 8.4; dua payout paralel tidak boleh membuat saldo negatif.
- Gate lisensi: API envelope Ed25519 diverifikasi nyata pada EA terminal MT5, test vectors valid/tampered, replay/expiry/mismatch; indikator online tetap dinonaktifkan sampai PoC sah.
- Gate akses: `ROUTES_MATRIX.csv` 71 route dibandingkan `php artisan route:list`, `ACTION_AUTH_MATRIX.md` 23 action diuji admin/client IDOR dan CSRF.
- Gate keamanan: `SECURITY_BASELINE.md` diterapkan pada **aplikasi** (TOTP, rate limits, audit, signed download, dependency scan). Server/proxy dan backup+restore fisik ditangani tim infra.
- Gate software: `.env.example`, migration/kontrak MySQL, Laravel Scheduler job, debug configuration, tanpa Telegram bot/webhook, pembayaran manual admin. Gate TLS, provision DB, cron host dan deploy adalah tanggung jawab infra.
- Status **GO-LIVE** hanya diberikan setelah *actual* staging tests, UAT owner, legal/broker review, dan seluruh P0 PASS. Dokumen siap implementasi tidak otomatis membuka transaksi asli.


**Pembagian gate:** yang menjadi bukti selesai developer adalah `SOFTWARE_ACCEPTANCE.md`; go-live menyatukan bukti software, infra dan owner/legal. Jangan memberi task infra kepada developer hanya karena gate lintas tim belum PASS.
