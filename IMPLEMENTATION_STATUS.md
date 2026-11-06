# IMPLEMENTATION_STATUS.md — Bukti kerja aplikasi

**Baseline saat paket ini dibuat (2026-11-04):** Repository berisi PRD, kontrak teknis, backlog dan HTML simulasi. **Belum ada source Laravel dan belum ada bukti tes aplikasi nyata.** Jangan menandai software siap handoff atau go-live.

| Item | Status awal | Bukti aktual |
|---|---|---|
| Laravel foundation (`artisan`, `composer.json`) | NOT STARTED | — |
| Database migrations & constraints | NOT STARTED | — |
| Auth / policies | NOT STARTED | — |
| Pesanan manual & lisensi | NOT STARTED | — |
| EA MT5 real PoC | NOT STARTED | — |
| IB / ledger / pencairan | NOT STARTED | — |
| Unit & feature tests | NOT RUN | — |
| Browser E2E & accessibility | NOT RUN | — |
| Security and concurrency | NOT RUN | — |
| SOFTWARE READY FOR HANDOFF | NO | — |

Saat sebuah task selesai, tambahkan tanggal, commit/PR, perintah pengujian, output ringkas nyata, dan keterbatasan. Task eksternal dicatat sebagai `EXTERNAL DEPENDENCY` (bukan kewajiban infra untuk developer). Pembuktian P0 ada pada `SOFTWARE_ACCEPTANCE.md`.

## Progres 4 November 2026 — TG-004 / TG-005

**Branch:** `feat/laravel-foundation`; perubahan lokal untuk review, belum dipush/PR. Baseline di atas adalah status paket spesifikasi awal.

| Item | Status terkini | Bukti |
|---|---|---|
| TG-004: workflow development lokal | DONE | `DEVELOPMENT.md`, `.env.example`, Docker/Compose, helper setup yang mempertahankan `.env` existing |
| TG-005: Laravel/Livewire/Tailwind | DONE | Laravel 13.40.0, Livewire 4.4.8, Tailwind 4, PHP 8.4.27, Composer 2.11.1, MySQL 8.4.13; build dan HTTP smoke PASS |
| Fresh clone kandidat lokal | PASS | Source kandidat disalin tanpa file ignored/secret, dibuat snapshot Git lokal, lalu di-clone ke direktori terpisah; dependency install, build, init DB kosong, helper, rollback/remigrate dan suite PASS. Bukan validasi checkout remote yang sudah dipush |
| Tests fondasi | PASS | 18 tests / 48 assertions di checkout kerja dan clone kandidat; cache/session MySQL, lock ownership, render HTTP/Livewire, konfigurasi dan endpoint sensitif belum tersedia |
| Lint dan metadata dependency | PASS | `composer lint` (28 file), `composer validate --strict`, `git diff --check` |
| TG-006 dan TG-007 | TODO | Tiga puluh tabel domain, constraint uang/lisensi, autentikasi/TOTP/policy belum diimplementasikan |
| Modul bisnis / MT5 / QA menyeluruh | TODO / EXTERNAL DEPENDENCY | Belum ada alur transaksi, EA source/terminal nyata, UAT, security/concurrency domain, browser E2E/staging |
| SOFTWARE READY FOR HANDOFF / GO-LIVE | NO | Gate aplikasi penuh dan persyaratan eksternal belum terpenuhi |

Perintah dan rincian diagnosis tersedia di [`technical/validation/FOUNDATION.md`](technical/validation/FOUNDATION.md), dengan output suite aktual di [`foundation-tests.txt`](technical/validation/foundation-tests.txt). Hanya tabel framework session/cache yang dimigrasikan; hasil ini bukan bukti TG-006, SA-01 lengkap atau G-01 produksi.

Input owner/legal/source EA (TG-001–TG-003) belum ada dan tidak diganti dengan data contoh. Penjualan lisensi/indikator tetap ditutup di server. Referensi HTML dipertahankan; CSS/JS demo yang hilang tidak dipulihkan dalam task fondasi.

## Progres 4 November 2026 — TG-006

**TG-006: DONE.** Branch lokal tetap `feat/laravel-foundation`, belum dipush/PR. Tabel progres TG-004/TG-005 di atas adalah catatan sebelum TG-006.

- 30 migration tabel domain + satu migration FK tertunda, 30 model dan 22 backed enum; InnoDB/utf8mb4, uang DECIMAL/string dan DATETIME(6) UTC.
- FK/unique/CHECK benar-benar diuji: 38 foreign key beserta target/delete rule dan penolakan parent hilang; satu versi produk aktif; lisensi unik permanen; idempotency scoped; koreksi rebate signed.
- Model memiliki cast enkripsi/hidden, guard mass assignment dan proteksi append-only Eloquent. Ini belum merupakan policy/auth atau workflow pembayaran/saldo.
- Fresh migration, lepas/pasang FK siklik, reset/remigrate, dua koneksi yang bersaing mengunci wallet dan enam profil EXPLAIN pada 1.000 baris sintetis per tabel PASS.
- **Suite penuh: 171 PASS / 1.294 assertions**, tanpa skip/expected failure. `composer lint`: 121 file PASS. Migrasi additive diterapkan ke database development; beranda HTTP tetap PASS. Bukti dan output aktual: [`TG006.md`](technical/validation/TG006.md), [`tg006-tests.txt`](technical/validation/tg006-tests.txt).
- Seeder default tidak membuat akun/data bisnis. Data tes hanya berada di `tg_test`; data owner, legal dan EA asli TG-001–TG-003 tetap **EXTERNAL DEPENDENCY**.

Berikutnya **TG-007: registrasi/login, TOTP admin, policies dan rate limit**. Audit/redaksi payload, perhitungan saldo, race pembayaran/pencairan, API/terminal MT5, UAT dan staging tetap belum selesai. **SOFTWARE READY FOR HANDOFF: NO; GO-LIVE: NO.**

## Progres 4 November 2026 — TG-007

**TG-007: DONE.** Catatan TG-006 di atas adalah histori sebelum implementasi autentikasi. Branch lokal tetap `feat/laravel-foundation`, belum dipush/PR.

- Registrasi client dengan kontak terenkripsi, email normalisasi, Argon2id, wallet+audit atomik; login generik, rate limit 5/email+IP dan 20/IP per 15 menit, rotasi sesi dan logout POST/CSRF.
- TOTP admin RFC 6238 (enam vektor resmi SHA1 lulus), secret terenkripsi, anti-replay, 10 recovery code 128-bit hashed/sekali pakai. Password admin saja tidak membuka panel; reauth password+faktor baru untuk reset berlaku 10 menit.
- Middleware aktif/role/forced-password/admin-idle, persistent middleware Livewire, policies owner/admin tanpa bypass global, dan penolakan aksi Livewire yang dipalsukan. Sesi idle 30 menit tidak dapat dihidupkan lewat challenge OTP tanpa login password ulang.
- Reset manual memerlukan kontak terdaftar, dua pemeriksaan dan alasan; semua sesi client lama dicabut, audit hanya metadata yang disensor. Password sementara/recovery hanya ditampilkan sekali; respons HTML dan JSON Livewire sensitif private/no-store. Tidak ada pengiriman otomatis.
- **265 tes PASS / 1.946 assertion**, tanpa skip/expected failure; **154 file lint PASS**, Vite build dan kompilasi Blade PASS; HTTP nyata login/register/update Livewire dan CSRF PASS. Bukti: [`TG007.md`](technical/validation/TG007.md), [`tg007-tests.txt`](technical/validation/tg007-tests.txt).

Admin pertama belum dibuat di database development; operator memakai `tg:create-admin` dengan input privat ketika identitas asli tersedia. Tidak ada credential bawaan atau akun produksi palsu. TG-001–TG-003 tetap **EXTERNAL DEPENDENCY**. Berikutnya TG-008 (layout/design system); komponen feedback menyeluruh TG-009, audit operasional TG-010 dan seluruh workflow uang/lisensi masih belum selesai. **SOFTWARE READY FOR HANDOFF: NO; GO-LIVE: NO.**

## Progres 5 November 2026 — TG-008

**TG-008: DONE.** Layout publik/client/admin bersama, tema terang forest/lime/bone/coral, komponen UI dan navigasi responsif telah diintegrasikan. Menu hanya menuju fitur tersedia, tanpa statistik bisnis simulasi.

- **278 tes PHP PASS / 2.298 assertions**, lint 156 file, build Vite dan kompilasi Blade PASS.
- **139 pemeriksaan Chromium PASS**, tanpa error JavaScript; 32 screenshot pada viewport 360/768/1280, termasuk drawer, fokus keyboard, data panjang dan update Livewire.
- Bukti: [`TG008.md`](technical/validation/TG008.md), [design system](technical/DESIGN_SYSTEM.md). Data browser sintetis hanya di `tg_test`.

Branch tetap `feat/laravel-foundation`, perubahan lokal belum dipush/PR. Berikutnya TG-009. TG-001–TG-003 tetap EXTERNAL DEPENDENCY; seluruh gate bisnis/QA belum selesai. **SOFTWARE READY FOR HANDOFF: NO; GO-LIVE: NO.**

## Progres 5 November 2026 — TG-009

**TG-009: DONE.** Modal aksesibel bersama dipasang pada reset password; loading, errors, toast dan upload status tersedia. Interceptor Livewire mengganti browser confirm/error mentah dengan dialog aman; timeout/koneksi terputus mempertahankan status belum pasti dan Cek Status GET tanpa mengulang mutasi.

Suite penuh 278 tests/2298 assertions PASS; setelah integrasi dialog native, 85 Auth/Layout tests/829 assertions PASS. Browser: lima skenario PASS (419,503,putus koneksi,timeout,komponen keyboard/upload/toast). Lint157 file, build dan sintaks JS PASS. Bukti: [`TG009.md`](technical/validation/TG009.md). Berikutnya TG-010. Perubahan lokal belum dipush/PR. Task bisnis dan QA menyeluruh tetap belum selesai; tidak siap handoff/go-live.

## Progres 5 November 2026 — TG-010

**TG-010: DONE.** UUID request dari server dikorelasikan dengan header/error/audit. Logger allowlist membuang pesan bebas, exception, nested context dan extra. Audit autentikasi memakai schema bertipe dan transaksi; error HTML/JSON generik tanpa stack/secret bahkan saat debug aktif.

Retensi telemetry90hari/IP raw terenkripsi30hari tersedia melalui command dry-run/apply batch; audit tidak dihapus. Default baru daily14file; konfigurasi existing dipertahankan. Scheduler mengikuti TG-035.

**284 tes PASS / 2358 assertions**, lint163 file PASS; enam tes khusus setelah penyesuaian view/default rotasi juga PASS. Bukti: [`TG010.md`](technical/validation/TG010.md). Berikutnya TG-011. Perubahan lokal belum dipush/PR. Input owner/legal/source EA masih menunggu; software belum siap handoff/go-live.

## Progres 5 November 2026 — TG-011 / Fase2

**TG-011: DONE.** Editor produk tujuh tab, policy admin/TOTP dan file privat, sanitasi HTML, gambar re-encode, versi/checksum/satu aktif, FAQ/tutorial serta metadata performa dengan audit editor/waktu/hash. Publikasi/arsip melalui modal; sale_enabled tetapfalse. Tidak ada angka performa buatan atau produk produksi dibuat.

**301 tests/2467 assertions PASS**,172 file lint, Vite build dan9 alur browser PASS pada360/768/1280. Tes produk akhir17PASS/101assertions. Runtime GD dan upload20MiB/post24MiB disiapkan dalam Docker development. Bukti: [`TG011.md`](technical/validation/TG011.md). Push fondasi TG-004–TG-010 sebelumnya berhasil sebagai `8cfcd1d`; pekerjaan Fase2 mengikuti branch yang sama. Berikutnya TG-012. Input owner/legal/EA dan PoC terminal tetap eksternal; software belum siap handoff/go-live.

## Progres 5 November 2026 — TG-012 / Fase2

**TG-012: DONE.** Katalog/detail published-only, preview/cara pakai/track record/FAQ/changelog/ulasan dalam satu halaman, metadata SEO dan disclaimer. Broker aktif/efektif dengan allowlist link dan estimasi BCMath tanpa mutasi saldo. Data kosong disembunyikan; file EA/PDF/consent tetap privat; penjualan belum dibuka.

**311 tests/2566 assertions PASS**,179 file lint, build dan12 kasus browser PASS pada360/768/1280 tanpa error JS. Bukti: [`TG012.md`](technical/validation/TG012.md). TG-011 sudah dipush sebagai `70d29c5`; status push TG-012 mengikuti Git. Berikutnya TG-013. Owner/legal/EA dan gate produksi tetap belum selesai.

## Progres 5 November 2026 — TG-013–TG-015

**DONE untuk implementasi software.** Harga kalender BCMath, pembulatan/minimum, renewalH-30, quote receipt immutable dan tujuan transfer terenkripsi; checkout/riwayat/detail owner-only; Telegram personal dan clipboard manual. Intent di URL privat memulihkan order lama saat respons hilang, tanpa membuat order baru. Row lock+unique+audit diuji dengan dua worker independen.

**331 tes / 2.692 assertion PASS**,195 file lint, build, Composer metadata dan8 pemeriksaan browser PASS. Migration additive diterapkan ke development; tidak ada reset/data bisnis nyata. Bukti: [`TG013-TG015.md`](technical/validation/TG013-TG015.md).

Keputusan UX: server MT5 dan real/demo diminta satu kali karena schema/API wajib, sedangkan renewal memakai akun lama. PoC/sales gate tetapfalse; harga/aturan/rekening/Telegram owner belum diisi. Konfirmasi pembayaran dan penerbitan key belum termasuk group ini. Berikutnya TG-016. **SOFTWARE READY FOR HANDOFF:NO;GO-LIVE:NO.**

## Progres 6 November 2026 — TG-016–TG-018

**DONE untuk software:** panel pembayaran admin dengan TOTP/reauth, modal cek dana dan catatan; paid+lisensi+audit atomik dan idempoten; key CSPRNG128bit terenkripsi/hash lookup; renewal mempertahankan key; suspend/reactivate/revoke dengan intent replay aman. Unduhan EX5 pemilik menggunakan URL sementara, pemeriksaan hak/status saat akses dan checksum file privat.

**343 tes / 2.771 assertion PASS**,206 file lint, build dan8 pemeriksaan browser PASS tanpa error JS. Dua worker MySQL membuktikan satu lisensi dan audit pembayaran untuk konfirmasi bersamaan; respons browser yang hilang setelah commit pulih melalui Cek Status GET. Setelah fingerprint diperkuat dengan timestamp perubahan,3 tes unduhan/14assertion serta browser lulus. Bukti: [`TG016-TG018.md`](technical/validation/TG016-TG018.md).

Tidak ada migration baru atau transaksi/transfer/chat nyata. Flag penjualan tetapfalse. TG-001–TG-003 dan PoC EA/indikator tetap eksternal; suspend/revoke aplikasi bukan bukti penghentian EA offline. Berikutnya TG-019, lalu integrasi terminal TG-020. **SOFTWARE READY FOR HANDOFF: NO; GO-LIVE: NO.**
