# TASKS.md — Backlog Implementasi Trader Garuda

**Paket:** v1.7.1 (software development only) (tambahan task untuk PRD v1.6, 4 November 2026)  
**Batas kerja wajib:** `SCOPE_OF_WORK.md`; tidak ada task provisioning atau operasi infrastruktur.  
**Sumber utama:** `PRD.md`, `PROMPT.md`, `PRODUCTION_GATE.md`, `technical/`, `.env.example`, `html-reference/`.  
**Baseline paket awal:** semua task belum dikerjakan. **Progres sekarang:** TG-004–TG-018 selesai secara software; bukti di `technical/validation/FOUNDATION.md`, `technical/validation/TG006.md` dan `technical/validation/TG007.md`. TG-001–TG-003 menunggu input eksternal; task lain belum selesai.

## Cara pakai

- Kerjakan secara berurutan per fase; task dengan dependensi harus menunggu hasil dependency. **P0** wajib untuk rilis; **P1** tetap dilaksanakan sebagai bagian MVP bila tercantum Must/Should pada PRD, tetapi tidak boleh menghalangi pengujian P0.
- Pindahkan task satu per satu: `TODO → IN PROGRESS → REVIEW → DONE` atau `BLOCKED`. **DONE** mensyaratkan perubahan source code, uji yang lulus, dan bukti/screenshot/log terkait; bukan sekadar membuat HTML demo.
- Tanda `[ ]` boleh diubah menjadi `[x]` hanya setelah penerimaan task terpenuhi. Penanggung jawab adalah **peran yang disarankan**, bukan orang yang sudah ditugasi.
- Kolom `Gate` memetakan task ke persyaratan pada `PRODUCTION_GATE.md`. Jangan tandai gate PASS sebelum pengujian pada aplikasi produksi/staging nyata.
- Perubahan bisnis wajib mengubah PRD lebih dulu, lalu task dan test; jangan diam-diam membuat keputusan baru dalam implementasi.

## Aturan yang tidak boleh berubah

1. **Telegram adalah chat pribadi admin secara manual**, tanpa bot/webhook/approval dari Telegram; dana dicek manual dan pembayaran hanya dikonfirmasi dari panel admin.
2. **Tidak ada angka saldo atau profit palsu** di produksi, demo hanya pada environment test/staging.
3. **Satu detail produk** memuat preview, panduan dan track record. Client tidak dipaksa ikut IB.
4. **Tindakan uang/lisensi selalu diaudit, diotorisasi, idempoten**, dan memakai modal + loading berdasarkan server.
5. **Indikator berlisensi tidak dijual** sebelum PoC lisensi indikator benar-benar lulus uji MT5.
6. **Lingkup hanya software:** kebutuhan VPS, DNS, TLS, Nginx, cron host, backup/restore fisik dan deployment infrastruktur diserahkan ke pihak lain melalui dokumen handoff.

## Ringkasan fase

| Fase | Tujuan | Jumlah task |
|---|---|---:|
| 0 Keputusan & persiapan | Tetapkan hanya informasi eksternal yang benar-benar diperlukan; jangan menebak data pribadi, rekening, broker, atau hasil trading. | 4 |
| 1 Fondasi | Buat basis Laravel, database, autentikasi, desain dan sistem feedback yang bisa digunakan modul lain. | 6 |
| 2 Produk, order & lisensi | Prioritaskan alur client Pilih EA → Pesan → Transfer → Chat Pribadi → Admin cek mutasi → Key terbit. | 12 |
| 3 IB, rebate & pencairan | IB opsional, tetapi bila client ikut, daftar broker, hasil rebate dan pencairan harus lengkap, konsisten dan aman. | 8 |
| 4 Konten & operasional | Pengalaman client tetap ringan; semua kompleksitas operasional diletakkan di panel admin. | 7 |
| 5 QA, staging & rilis | Bukti uji aplikasi Laravel dan terminal EA nyata wajib ada sebelum uang sungguhan diterima. | 7 |

**Total: 44 task.** Status terkini mengikuti checklist dan bukti progres di bawah; baseline awal adalah TODO.

## Fase 0 Keputusan & persiapan

Tetapkan hanya informasi eksternal yang benar-benar diperlukan; jangan menebak data pribadi, rekening, broker, atau hasil trading.

- [ ] **TG-001 — Kunci setelan bisnis dan data asli** `P0`
  - **Pemilik peran:** Owner + Lead Dev. **Dependensi:** —. **Gate:** G-23.
  - **Selesai jika:** Dokumen berisi harga awal, penurunan bulanan, minimum, kurs, minimal pencairan, rekening dan Telegram pribadi; nilai rahasia tidak ditulis di repo.

- [ ] **TG-002 — Validasi aturan promosi IB dan legal** `P0`
  - **Pemilik peran:** Owner + Legal. **Dependensi:** —. **Gate:** G-22.
  - **Selesai jika:** Bukti perizinan/perjanjian broker, disclaimer, ketentuan sewa/refund, privasi dan penggunaan testimoni dinyatakan layak oleh penanggung jawab.

- [ ] **TG-003 — Audit source EA dan rencana integrasi MT5** `P0`
  - **Pemilik peran:** Owner + MQL5 Dev. **Dependensi:** —. **Gate:** G-10,G-11,G-12.
  - **Selesai jika:** Ada source EA yang dapat dimodifikasi/izin integrasi, kontrak verifikasi disepakati; indikator tetap tidak dapat dibeli sampai PoC lulus.

- [x] **TG-004 — Definisikan kontrak lingkungan aplikasi dan siapkan workflow development lokal** `P0`
  - **Pemilik peran:** Lead Dev. **Dependensi:** —. **Gate:** G-01.
  - **Selesai jika:** aplikasi dapat dikembangkan/ditest secara lokal dengan dependency yang terdokumentasi, `.env.example` aman, pilihan driver/APP_URL dapat diatur tanpa hard-code; kebutuhan staging/domain lama tertulis sebagai handoff ke infra, bukan dikerjakan developer.
  - **Artefak teknis wajib:** `technical/ENVIRONMENT.md`, `.env.example`, `SCOPE_OF_WORK.md`, daftar kebutuhan eksternal kepada infra.

## Fase 1 Fondasi

Buat basis Laravel, database, autentikasi, desain dan sistem feedback yang bisa digunakan modul lain.

- [x] **TG-005 — Bootstrap Laravel 13 / Livewire 4 / Tailwind 4** `P0`
  - **Pemilik peran:** Full-stack Dev. **Dependensi:** TG-004. **Gate:** G-01.
  - **Selesai jika:** Aplikasi jalan dari fresh clone; .env.example tanpa rahasia; build & test smoke dapat dijalankan.
  - **Artefak teknis wajib:** `.env.example` aman dan test fresh clone + database migration.

- [x] **TG-006 — Migrations, relasi, indeks dan constraint DB** `P0`
  - **Pemilik peran:** Backend Dev. **Dependensi:** TG-005. **Gate:** G-01,G-07,G-14,G-15.
  - **Selesai jika:** Fresh migrate dan rollback tervalidasi; angka keuangan/lot pakai DECIMAL; uniqueness penting ditegakkan DB.
  - **Artefak teknis wajib:** `technical/DATABASE_SCHEMA.md` (30 domain tables), migration+FK/check/unique/rollback test, `technical/ERD.mmd`.

- [x] **TG-007 — Register/login client, admin TOTP, policies dan rate limit** `P0`
  - **Pemilik peran:** Backend Dev. **Dependensi:** TG-006. **Gate:** G-03,G-04.
  - **Selesai jika:** Auth sukses; client A tidak bisa mengakses data client B; admin TOTP/recovery diuji; reset password via admin tercatat.
  - **Artefak teknis wajib:** `technical/SECURITY_BASELINE.md` dan test role/owner access, rate limiter, TOTP/recovery.

- [x] **TG-008 — Layout dan design system sesuai referensi HTML** `P1`
  - **Pemilik peran:** Frontend Dev. **Dependensi:** TG-005. **Gate:** G-18,G-26.
  - **Selesai jika:** Forest Green/Lime/Warm Bone/Coral; tema terang; menu mobile; responsif lebar 360,768,1280.

- [x] **TG-009 — Komponen modal, loading, error, toast dan status belum pasti** `P0`
  - **Pemilik peran:** Frontend Dev. **Dependensi:** TG-007,TG-008. **Gate:** G-18.
  - **Selesai jika:** Tidak ada alert/confirm/prompt bawaan; tindakan kritis modal aksesibel; saat timeout ada tombol Cek Status; tidak ada progress palsu.
  - **Artefak teknis wajib:** `technical/ACTION_AUTH_MATRIX.md` modal/loading/timeout, upload progress dari event transfer asli.

- [x] **TG-010 — Audit log dan pengelolaan error yang aman** `P0`
  - **Pemilik peran:** Backend Dev. **Dependensi:** TG-006,TG-007. **Gate:** G-21.
  - **Selesai jika:** Log mencatat aktor/status & request_id tanpa key, password atau data rekening; errors ramah pengguna.
  - **Artefak teknis wajib:** `technical/SECURITY_BASELINE.md` audit log redacted, retensi, error cases.

## Fase 2 Produk, order & lisensi

Prioritaskan alur client Pilih EA → Pesan → Transfer → Chat Pribadi → Admin cek mutasi → Key terbit.

- [x] **TG-011 — CRUD produk, media, versi EA dan satu editor detail** `P1`
  - **Pemilik peran:** Full-stack Dev. **Dependensi:** TG-006,TG-008. **Gate:** G-19.
  - **Selesai jika:** Admin mengelola deskripsi, tutorial, performa backtest/forward test, FAQ, versi, screenshot; data kosong tidak ditampilkan dan statistik tidak direkayasa.

- [x] **TG-012 — Halaman publik katalog, detail produk dan broker** `P1`
  - **Pemilik peran:** Frontend Dev. **Dependensi:** TG-011. **Gate:** G-19,G-27.
  - **Selesai jika:** Route publik PRD aktif, metadata SEO & risk disclaimer tampil; satu halaman detail produk memuat preview + track record.

- [x] **TG-013 — Mesin harga kalender, kuotasi dan renewal** `P0`
  - **Pemilik peran:** Backend Dev. **Dependensi:** TG-006,TG-011,TG-001. **Gate:** G-08.
  - **Selesai jika:** Tes Januari/Februari/Desember, minimum/pembulatan, expired 31 Des WIB, quote snapshot dan renewal lulus.

- [x] **TG-014 — Checkout client dan halaman pesanan** `P0`
  - **Pemilik peran:** Full-stack Dev. **Dependensi:** TG-007,TG-012,TG-013. **Gate:** G-06,G-08.
  - **Selesai jika:** Client isi akun MT5 sekali; nominal, metode bayar dan akses sampai 31 Desember jelas; order belum lunas tidak menampilkan key.

- [x] **TG-015 — Tautan Telegram pribadi + Salin Detail Pesanan** `P0`
  - **Pemilik peran:** Frontend Dev. **Dependensi:** TG-014,TG-001. **Gate:** G-05.
  - **Selesai jika:** Tautan membuka chat ke @username admin, client mengirim bukti manual; tidak ada bot/webhook, pesan otomatis ataupun perubahan status pesanan.

- [x] **TG-016 — Konfirmasi pembayaran hanya dari panel admin** `P0`
  - **Pemilik peran:** Backend Dev. **Dependensi:** TG-006,TG-007,TG-009,TG-014. **Gate:** G-06,G-07.
  - **Selesai jika:** Modal wajib cek dana; admin simpan catatan verifikasi mutasi, klik ganda/race/timeout tidak menghasilkan dua lisensi; audit ada.
  - **Artefak teknis wajib:** `technical/ACTION_AUTH_MATRIX.md` A02, test T-ORD-02 konkurensi/idempotency.

- [x] **TG-017 — Penerbitan key, perpanjangan, suspend, revoke** `P0`
  - **Pemilik peran:** Backend Dev. **Dependensi:** TG-016,TG-003. **Gate:** G-07,G-08,G-10.
  - **Selesai jika:** 1 lisensi per kombinasi pemilik-produk-akun, key kriptografis terenkripsi + hash lookup, renewal tetap key sama; operasi idempoten.
  - **Artefak teknis wajib:** `technical/DATABASE_SCHEMA.md` unique license triple + renewal key sama, tests.

- [x] **TG-018 — Storage privat .ex5 dan unduh aman** `P0`
  - **Pemilik peran:** Backend Dev. **Dependensi:** TG-011,TG-017. **Gate:** G-09.
  - **Selesai jika:** File hanya bisa diunduh pemilik lisensi berhak melalui URL sementara dan cek authorization; checksum dan tes akses silang lulus.

- [ ] **TG-019 — API lisensi v1, nonce, rate limit, signature** `P0`
  - **Pemilik peran:** Backend + MQL5 Dev. **Dependensi:** TG-003,TG-017. **Gate:** G-10,G-11.
  - **Selesai jika:** Endpoint benar-benar memverifikasi key+produk+akun+expiry; replay/invalid/mismatch ditolak; test vector signature dapat diverifikasi EA nyata.
  - **Artefak teknis wajib:** `technical/API_CONTRACT.md` + test fixture signed Ed25519 dan nonce TTL 10 menit.

- [ ] **TG-020 — Integrasi EA MT5 ke API dan uji offline** `P0`
  - **Pemilik peran:** MQL5 Dev + QA. **Dependensi:** TG-018,TG-019. **Gate:** G-10,G-11,G-12.
  - **Selesai jika:** Uji terminal MT5 nyata: valid, expired, salah akun, revoke, suspend, WebRequest allowlist, timeout, offline grace <=48 jam dan tidak lewat expiry; indikator terkunci bila tanpa PoC.
  - **Artefak teknis wajib:** Log uji terminal MT5 + pure MQL5 Ed25519 verify yang lulus, bukan simulated status.

- [ ] **TG-021 — Client Produk Saya, key, unduhan & riwayat verifikasi** `P0`
  - **Pemilik peran:** Full-stack Dev. **Dependensi:** TG-017,TG-018. **Gate:** G-04,G-19.
  - **Selesai jika:** Status dan sisa hari lisensi benar; salin key/unduh hanya untuk pemilik; 50 verifikasi terakhir terbatas data sendiri.

- [ ] **TG-022 — Refund manual dan perubahan akun MT5 terkontrol** `P0`
  - **Pemilik peran:** Backend Dev. **Dependensi:** TG-016,TG-017. **Gate:** G-17.
  - **Selesai jika:** Refund uang tercatat terpisah dari revoke; perubahan akun butuh persetujuan admin & audit; transaksi tidak merusak order/lisensi.

## Fase 3 IB, rebate & pencairan

IB opsional, tetapi bila client ikut, daftar broker, hasil rebate dan pencairan harus lengkap, konsisten dan aman.

- [ ] **TG-023 — Admin broker dan promosi IB publik** `P1`
  - **Pemilik peran:** Full-stack Dev. **Dependensi:** TG-006,TG-008. **Gate:** G-13.
  - **Selesai jika:** Admin kelola logo, URL referral, syarat, rate berlaku; kalkulator berlabel estimasi dan tidak menjanjikan profit.

- [ ] **TG-024 — Broker IB Saya: tautkan akun dan verifikasi admin** `P0`
  - **Pemilik peran:** Full-stack Dev. **Dependensi:** TG-007,TG-023. **Gate:** G-13.
  - **Selesai jika:** Client boleh gabung beberapa broker, status Menunggu/Disetujui/Ditolak dan bukti kepemilikan; IB tetap opsional bagi pembeli EA.

- [ ] **TG-025 — Ledger rebate immutable dan koreksi** `P0`
  - **Pemilik peran:** Backend Dev. **Dependensi:** TG-006,TG-024. **Gate:** G-14.
  - **Selesai jika:** Input admin dengan source_reference unik, larang duplikasi, koreksi menambah reversal entry; semua mengacu akun IB terverifikasi.
  - **Artefak teknis wajib:** `technical/DATABASE_SCHEMA.md` ledger append-only, unique account/source ref dan koreksi.

- [ ] **TG-026 — Client Hasil Rebate per broker & periode** `P0`
  - **Pemilik peran:** Full-stack Dev. **Dependensi:** TG-025. **Gate:** G-13,G-14.
  - **Selesai jika:** Total lot/rebate, saldo tersedia, pending, paid dan mutasi akurat dari DB; filter broker/akun dan empty state jelas.

- [ ] **TG-027 — Reservasi saldo pada permintaan pencairan** `P0`
  - **Pemilik peran:** Backend Dev. **Dependensi:** TG-025,TG-001. **Gate:** G-15.
  - **Selesai jika:** Validasi min/batas saldo; row lock + idempotency; dua pengajuan paralel tidak membuat saldo minus; kurs IDR snapshot.
  - **Artefak teknis wajib:** `technical/DATABASE_SCHEMA.md` wallet row lock, min payout, snapshot kurs, idempotency.

- [ ] **TG-028 — Client ajukan pencairan + riwayat status** `P0`
  - **Pemilik peran:** Full-stack Dev. **Dependensi:** TG-027,TG-009. **Gate:** G-15,G-18.
  - **Selesai jika:** Modal menunjukkan nominal USD/IDR dan rekening tujuan; status Diajukan/Diproses/Dibayar/Ditolak; tidak ada keberhasilan sebelum commit.

- [ ] **TG-029 — Admin proses transfer dan penolakan pencairan** `P0`
  - **Pemilik peran:** Full-stack Dev. **Dependensi:** TG-027,TG-028. **Gate:** G-16,G-15.
  - **Selesai jika:** Transfer manual dicatat ref/bukti; Pay once; menolak me-release reserve tepat sekali; client melihat status aktual.
  - **Artefak teknis wajib:** `technical/ACTION_AUTH_MATRIX.md` A14–A16, bukti transfer manual dan pay-once.

- [ ] **TG-030 — Audit konsistensi saldo end-to-end** `P0`
  - **Pemilik peran:** QA + Backend Dev. **Dependensi:** TG-025,TG-029. **Gate:** G-13,G-14,G-15,G-16.
  - **Selesai jika:** Test kredit, koreksi, dua akun, dua broker, reserved, rejected, paid, retry dan timeout; ledger = dashboard client & admin.

## Fase 4 Konten & operasional

Pengalaman client tetap ringan; semua kompleksitas operasional diletakkan di panel admin.

- [ ] **TG-031 — FAQ, panduan instalasi bergambar, edukasi** `P1`
  - **Pemilik peran:** Full-stack Dev. **Dependensi:** TG-011,TG-008. **Gate:** G-28.
  - **Selesai jika:** Admin CRUD media bertipe aman, langkah instalasi MT5, SEO dan publikasi; tampilan mobile dan empty state benar.

- [ ] **TG-032 — Testimoni manual dan pengumuman** `P1`
  - **Pemilik peran:** Full-stack Dev. **Dependensi:** TG-011,TG-008. **Gate:** G-28.
  - **Selesai jika:** Hanya admin dapat input/aktifkan testimoni nyata berizin; pengumuman tampil sesuai masa aktif/audiens.

- [ ] **TG-033 — Dashboard Tugas Hari Ini dan shortcut** `P1`
  - **Pemilik peran:** Full-stack Dev. **Dependensi:** TG-016,TG-024,TG-029. **Gate:** G-28.
  - **Selesai jika:** Menunggu bayar/IB/pencairan, lisensi hampir habis, anomali dan order lunas tanpa key; setiap kartu menuju pekerjaan konkret.

- [ ] **TG-034 — Deteksi anomali lisensi dan review admin** `P0`
  - **Pemilik peran:** Backend Dev. **Dependensi:** TG-019,TG-010. **Gate:** G-21.
  - **Selesai jika:** Aturan threshold dedup, review/suspend manual, audit; tidak ada suspend otomatis agresif dan tidak menampilkan data pribadi berlebihan.
  - **Artefak teknis wajib:** `technical/SECURITY_BASELINE.md` anomaly flag dedup, no automatic suspend default.

- [ ] **TG-035 — Scheduler Laravel, health check aplikasi dan log aman** `P0`
  - **Pemilik peran:** Backend Dev. **Dependensi:** TG-006,TG-010. **Gate:** G-21.
  - **Selesai jika:** Laravel Scheduler jobs terdaftar/idempoten dan lulus tes; `/health` serta log/error aplikasi bisa diuji; frekuensi cron dan kebutuhan backup/storage **didokumentasikan untuk infra**, tanpa mengoperasikan backup atau monitoring host.
  - **Artefak teknis wajib:** automated scheduler/health/log tests, kontrak kebutuhan di `technical/ENVIRONMENT.md` dan `RELEASE_HANDOFF.md`.

- [ ] **TG-036 — Selesaikan semua route PRD, SEO, legal & akses** `P1`
  - **Pemilik peran:** Full-stack Dev. **Dependensi:** TG-012,TG-031,TG-032. **Gate:** G-19,G-27.
  - **Selesai jika:** Route list diverifikasi; sitemap/robots/canonical/noindex tepat; hak akses admin/member di server.
  - **Artefak teknis wajib:** `technical/ROUTES_MATRIX.csv` 71 routes + `php artisan route:list` reconciliation.

- [ ] **TG-037 — Polish mobile, status loading, progres & modal** `P1`
  - **Pemilik peran:** Full-stack Dev. **Dependensi:** TG-008,TG-009,TG-036. **Gate:** G-18,G-26.
  - **Selesai jika:** Tes responsif 360/768/1280, keyboard/focus trap, upload progress byte asli, idle/empty/error/timeout/retry tidak menipu.

## Fase 5 QA aplikasi, UAT & handoff rilis

Bukti uji aplikasi Laravel dan terminal EA nyata wajib ada sebelum uang sungguhan diterima.

- [ ] **TG-038 — Tes otomatis autentikasi, order, lisensi dan refund** `P0`
  - **Pemilik peran:** QA. **Dependensi:** TG-007,TG-016,TG-022. **Gate:** G-03,G-04,G-06,G-07,G-17.
  - **Selesai jika:** Test authz silang, transaksi ganda, replay, negative cases dan refund tercatat PASS dalam log test.
  - **Artefak teknis wajib:** `technical/TEST_PLAN.md` P0 order/license/refund/RBAC suite pass.

- [ ] **TG-039 — Tes transaksi rebate & pencairan paralel** `P0`
  - **Pemilik peran:** QA. **Dependensi:** TG-030. **Gate:** G-13,G-14,G-15,G-16.
  - **Selesai jika:** Concurrency test dan ledger reconciliation PASS; tidak ada saldo negatif atau pembayaran ganda.
  - **Artefak teknis wajib:** `technical/TEST_PLAN.md` concurrent payout / ledger reconciliation pass.

- [ ] **TG-040 — Bukti integrasi EA ↔ API pada terminal nyata** `P0`
  - **Pemilik peran:** QA + MQL5 Dev. **Dependensi:** TG-020. **Gate:** G-10,G-11,G-12.
  - **Selesai jika:** Log testing MT5 menunjukkan verifikasi valid/invalid/offline dan signature; indikator disembunyikan sampai PoC selesai.
  - **Artefak teknis wajib:** `technical/API_CONTRACT.md` signature PoC fixture + terminal evidence.

- [ ] **TG-041 — E2E Livewire di staging & audit responsive** `P0`
  - **Pemilik peran:** QA + Frontend Dev. **Dependensi:** TG-037. **Gate:** G-18,G-19,G-26.
  - **Selesai jika:** Bukan HTML demo: order, admin konfirmasi, IB, saldo, pencairan, modal, loading nyata, 360/768/1280 diuji.
  - **Artefak teknis wajib:** `technical/ROUTES_MATRIX.csv` + mobile Livewire e2e evidence.

- [ ] **TG-042 — Paket rilis aplikasi, validasi migration dan serah-terima ke infra** `P0`
  - **Pemilik peran:** Lead Dev + QA. **Dependensi:** TG-035,TG-041. **Gate:** G-01,G-19,G-21.
  - **Selesai jika:** build artefak, migration dan kompatibilitas skema, perintah rollback aplikasi yang aman, smoke-test, konfigurasi contoh, checklist instalasi dan handoff sudah diuji pada lingkungan development/staging **yang disediakan**; tim developer tidak melakukan provisioning/deploy/backup host.
  - **Artefak teknis wajib:** `RELEASE_HANDOFF.md`, hasil CI/QA, migration plan dan manifest artefak rilis.

- [ ] **TG-043 — UAT owner dan verifikasi konfigurasi asli** `P0`
  - **Pemilik peran:** Owner + QA. **Dependensi:** TG-002,TG-038,TG-039,TG-040,TG-042. **Gate:** G-22,G-23,G-24,G-25.
  - **Selesai jika:** Pemilik cek alur transaksi simulasi + akun MT5 test; username Telegram, rekening, produk, broker/legal dikonfirmasi; seed demo tidak di produksi.
  - **Artefak teknis wajib:** `technical/TEST_PLAN.md` UAT owner and actual config approval.

- [ ] **TG-044 — Persetujuan kesiapan software dan handoff go-live** `P0`
  - **Pemilik peran:** Lead Dev + QA + Owner (penerimaan software). **Dependensi:** TG-043. **Gate:** aplikasi P0 di `SOFTWARE_ACCEPTANCE.md`.
  - **Selesai jika:** semua gate aplikasi P0 PASS dan paket kode+tes diserahkan; keputusan membuka transaksi nyata menunggu verifikasi terpisah pemilik/infra/legal, bukan task developer.
  - **Artefak teknis wajib:** `SOFTWARE_ACCEPTANCE.md` berisi bukti, dan `RELEASE_HANDOFF.md` yang diterima pihak infra/owner.

## Definition of Done untuk setiap task

- [ ] UI mengikuti HTML referensi yang relevan; semua tombol menuju aksi nyata.
- [ ] Backend Laravel memproses data DB; tidak menggunakan `localStorage` sebagai penyimpanan produksi.
- [ ] Akses diuji pada role/admin/client yang benar, termasuk upaya akses silang.
- [ ] Validasi, empty/error, modal/konfirmasi, loading nyata, idempotency bila relevan.
- [ ] Test otomatis atau langkah manual yang dapat direproduksi lulus dan log hasil disimpan.
- [ ] Tidak ada kredensial/secret, key atau data sensitif di commit/log/screenshot publik.
- [ ] PR atau perubahan kode direview dan dependensi downstream diberi informasi.

## Bukti progres (isi developer)

| ID task | Status | Commit/PR | Perintah & hasil tes | Reviewer | Tanggal |
|---|---|---|---|---|---|
| TG-004 | DONE | `feat/laravel-foundation` (working tree) | Setup lokal, konfigurasi aman, helper repeatable; lihat `technical/validation/FOUNDATION.md` | Review agent + validasi lokal | 2026-11-04 |
| TG-005 | DONE | `feat/laravel-foundation` (working tree) | Clone kandidat lokal, MySQL migrate/rollback, build, 18 tests / 48 assertions PASS | Review agent + validasi lokal | 2026-11-04 |
| TG-006 | DONE | `feat/laravel-foundation` (working tree) | 171 tests / 1294 assertions, 30 domain tables, rollback, 38 FK, EXPLAIN, 2-connection lock; `technical/validation/TG006.md` | Review agent + validasi MySQL 8.4 lokal | 2026-11-04 |
| TG-007 | DONE | `feat/laravel-foundation` (working tree) | 265 tests / 1946 assertions, register/login/TOTP/recovery/reset/policy/CSRF/idle PASS; `technical/validation/TG007.md` | Review keamanan agent + MySQL lokal | 2026-11-04 |
| TG-016–TG-018 | DONE (software) | `feat/laravel-foundation` | 343 tests/2771 assertions; race MySQL,8 browser checks; `technical/validation/TG016-TG018.md` | MySQL8.4 + Chromium lokal | 2026-11-06 |
| TG-019 | TODO | — | — | — | — |
| TG-027 | TODO | — | — | — | — |
| TG-044 | BLOCKED | — | Seluruh gate P0 belum diuji | — | — |

## Urutan pengerjaan untuk AI coding agent

1. Baca `PROMPT.md`, PRD dan referensi HTML. Pilih task tanpa dependensi yang belum selesai.
2. Kerjakan fitur sampai dapat diuji, bukan hanya scaffolding. Sertakan migrasi, service, routes, policy, halaman Livewire dan test yang relevan.
3. Eksekusi tes, laporkan PASS/FAIL berikut bukti, lalu perbarui checklist tugas dan `PRODUCTION_GATE.md` hanya bila buktinya sah.
4. Jika gagal karena input eksternal (akun MT5, hosting, rekening, izin IB), tandai `BLOCKED`, jelaskan informasi yang dibutuhkan dan lanjutkan task independen.
5. Jangan menyatakan proyek siap produksi sebelum **TG-044** dan semua gate P0 lulus.

## Lampiran teknis v1.7 sebagai acceptance artifacts (bukan task baru)

Jumlah tetap **44 task**; status awal seluruhnya TODO, progres aktual mengikuti checklist dan bukti di atas. Dokumen berikut wajib dituntaskan **ketika task terkait dikerjakan** (bukan harus selesai sebelum developer mulai coding): `technical/DATABASE_SCHEMA.md`, `technical/API_CONTRACT.md`, `technical/ACTION_AUTH_MATRIX.md`, `technical/ROUTES_MATRIX.csv`, `technical/SECURITY_BASELINE.md`, `technical/ENVIRONMENT.md`, `technical/TEST_PLAN.md`, `.env.example`. Semua dipakai untuk menulis migration, implementation tests, CI/staging, sampai go-live gate.

**Pengingat status:** PRD dan HTML reference adalah target desain/kontrak, bukan aplikasi yang sudah terpasang. MVP tidak memakai Telegram Bot API/webhook, semua pembayaran oleh admin manual melalui panel. Lingkungan production jangan menggunakan data dummy atau hasil test palsu.

| TG-008 | DONE | `feat/laravel-foundation` (working tree) | 278 tests / 2298 assertions; 139 browser checks, 32 screenshots, 360/768/1280; `technical/validation/TG008.md` | Review layout + Chromium/MySQL lokal | 2026-11-05 |

| TG-009 | DONE | `feat/laravel-foundation` (working tree) | Komponen feedback, 278 suite PASS + 85 Auth/Layout setelah integrasi modal; 5 skenario browser PASS; `technical/validation/TG009.md` | Chromium + MySQL lokal | 2026-11-05 |

| TG-010 | DONE | `feat/laravel-foundation` (working tree) | 284 tests / 2358 assertions; redaksi, korelasi request, error dan retensi; `technical/validation/TG010.md` | MySQL lokal | 2026-11-05 |

| TG-011 | DONE | `feat/laravel-foundation` | 301 tests/2467 assertions;17 tes produk dan9 alur browser PASS; `technical/validation/TG011.md` | MySQL8.4 + Chromium lokal | 2026-11-05 |

| TG-012 | DONE | `feat/laravel-foundation` | 311 tests/2566 assertions;12 browser cases, published-only/SEO/privacy; `technical/validation/TG012.md` | MySQL8.4 + Chromium lokal | 2026-11-05 |

| TG-013–TG-015 | DONE (software) | `feat/laravel-foundation` | 331 tests/2692 assertions; dua worker MySQL,8 browser checks; `technical/validation/TG013-TG015.md` | MySQL8.4 + Chromium lokal | 2026-11-05 |
