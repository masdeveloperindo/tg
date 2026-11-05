# Bukti fondasi TG-004 / TG-005 — 4 November 2026

Lingkup: aplikasi dasar Laravel 13 + Livewire 4 + Tailwind 4, konfigurasi aman, workflow development MySQL 8.4. Branch lokal `feat/laravel-foundation`; belum ada push, PR, deployment atau publikasi environment yang dibuktikan.

## Pemeriksaan aktual

| Pemeriksaan | Hasil |
|---|---|
| `docker compose build app db` | PASS; image development PHP 8.4.27/Composer 2.11.1 dan MySQL 8.4.13 |
| `composer install --prefer-source --no-interaction` | PASS mengikuti `composer.lock`, Git HTTPS dan CA resmi lingkungan; 108 paket terkunci |
| `composer validate --strict` | PASS |
| `npm ci` dan `npm run build` | PASS; Tailwind 4/Vite 8.3.5; audit npm pada clone melaporkan 0 vulnerability |
| `php artisan migrate` | PASS, dua migration framework (sessions, cache/cache_locks) |
| `composer test -- --no-ansi` | PASS: **18 tests, 48 assertions**, tidak ada skip/expected failure |
| `composer lint` | PASS: 28 file PHP |
| `php artisan route:list --except-vendor` | PASS: route aplikasi `/` merender `App\Livewire\Home`; route vendor Livewire terpisah |
| HTTP aplikasi berjalan | PASS: beranda 200, bootstrap Livewire dan aset Vite dapat diambil; `/admin`, `/member`, `/.env`, file privat contoh 404 |
| Clone kandidat lokal terpisah | PASS: install dependency dari cache terpercaya, init volume MySQL kosong, build via Node 24 container, setup helper lengkap, rollback/remigrate, 18 tests/48 assertions |
| Pengulangan helper setup | PASS; hash `.env` sebelum/sesudah identik, secret tidak dicetak |

Clone validasi dibuat dari snapshot Git **lokal atas source kandidat**, tanpa `.env`, vendor, node_modules atau hasil build dari checkout kerja. Ini membuktikan reproducibility source kandidat, bukan clone commit yang sudah tersedia di remote. Instance validasi memakai project/volume Docker terpisah dari instance kerja; hanya instance validasi tersebut yang dibersihkan sesudah pengujian.

Suite menguji render HTTP/Livewire, konfigurasi UTC/WIB/private storage/sales closed/queue sync/Argon2id, 11 endpoint yang belum boleh tersedia, native MySQL 8.4, inventory tabel framework pada `tg_test`, persistensi cache dan sesi database, serta kepemilikan lock. Ini bukan pengujian transaksi atau concurrency bisnis. Output suite: [`foundation-tests.txt`](foundation-tests.txt).

## Diagnosis yang sudah diperbaiki

- Arsip GitHub API ditolak policy egress; source Git HTTPS berhasil dengan CA dipasang untuk Composer **dan** Git. Verifikasi tidak dinonaktifkan. Kebutuhan `api.github.com` disimpan dalam draft lingkungan untuk unduhan dist standar; menyimpan draft belum menerapkan policy runtime.
- Plugin font bawaan skeleton mencoba mengunduh font yang tidak digunakan. Plugin tersebut dihapus; UI memakai system font dan tidak membutuhkan layanan font eksternal.
- Umask ketat membuat bind-mount SQL tidak terbaca user MySQL. Image development MySQL kini menyalin SQL dengan mode `0444`; init volume kosong dari clone ketat berhasil.
- Tes inventory awal melihat seluruh schema yang diizinkan dan nama fully-qualified. Tes diperbaiki memakai schema `tg_test` eksplisit dan nama tabel tanpa qualifier; assertion inventory tetap lengkap. Hasil awal 17 PASS/1 FAIL sudah ditangani, hasil final 18 PASS.
- Review menemukan `DB_URL` inherited dan suite Unit kosong sebagai risiko. PHPUnit kini force-clear `DB_URL`, memaksa environment/database test dan memeriksa target sebelum reset; suite kosong dihapus.

## Batas penerimaan

TG-004/TG-005 selesai untuk fondasi development. Tiga puluh tabel domain/constraint (TG-006), auth/TOTP (TG-007), design system menyeluruh (TG-008), uang/lisensi/IB dan seluruh gate QA tetap belum selesai. Tidak ada seed akun admin/data bisnis palsu. CSS/JS HTML demo tetap hilang di paket awal; halaman Laravel baru menggunakan asetnya sendiri.

Owner harus menyediakan konfigurasi bisnis asli, legal/broker dan source EA berizin/terminal MT5 untuk task terkait. Infra menangani hosting/staging, domain, secret produksi, storage fisik dan operasi host. **SOFTWARE READY FOR HANDOFF: NO. GO-LIVE: NO.**
