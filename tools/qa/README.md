# QA aplikasi / kandidat rilis

Runner hanya menerima loopback dan memeriksa koneksi MySQL **8.4 / tg_test** sebelum fixture/migration. `tg_test` akan dikosongkan: jangan jalankan bersamaan dengan PHPUnit. PHP 8.4 + Composer 2, Node 24, Chromium/Playwright 1.62.1 dan Python 3 diperlukan; dependencies aplikasi mengikuti lockfiles. Tidak menjalankan staging atau produksi.

```sh
# Dari root repo, Docker development sudah berjalan:
docker compose run --rm --no-deps app php artisan test --no-ansi
python3 -m pip install -r tools/qa/requirements.txt
python3 tests/Support/verify-license-vectors.py
python3 tools/qa/test_release_bundle.py
npm run build
python3 tools/qa/browser_matrix.py --output qa-artifacts/browser
python3 tools/qa/release_bundle.py --output qa-artifacts/release
docker compose run --rm --no-deps app php tools/qa/rehearse-release.php /app/qa-artifacts/release/trader-garuda-candidate.tar.gz /app/qa-artifacts/release-smoke.json
```

Playwright harus tersedia di Node resolver; bila belum: `npm install --no-save --package-lock=false playwright@1.62.1` dan `npx playwright install --with-deps chromium`. Browser memakai `/usr/bin/chromium` bila tersedia; override lewat `QA_BROWSER_EXECUTABLE`. Matrix memakai loopback 8081; `--base-url http://127.0.0.1:8082` bila port terpakai, `--cases checkout fulfillment` untuk pengulangan suite tertentu. Setiap suite memakai fixture fresh, server sementara, storage terpisah, lalu menghentikan hanya container miliknya. Override sales hanya di router QA di luar public/, setelah guard test DB; konfigurasi sales aplikasi tetap tertutup.

Untuk runtime PHP/MySQL native gunakan `--backend local`. GitHub Actions menjalankan jalur ini dan mengunggah laporan/screenshot sintetis serta kandidat selama 14 hari. Workflow dikonfigurasi; status remote harus diperiksa dari run aktual, bukan disimpulkan dari tes lokal.

Paket berisi source runtime, compiled frontend, lockfiles, `.env.example` kosong dan handoff. Manifest mencatat commit, dirty tree, inventory dan SHA-256. `.env`, database, upload, private key/EX5, vendor, node_modules, test/fixture dan cache tidak disertakan. Verifikasi: `python3 tools/qa/release_bundle.py --verify qa-artifacts/release/trader-garuda-candidate.tar.gz`. Build dengan commit, isi runtime dan aset yang sama menghasilkan bytes yang sama. Paket kandidat belum merupakan release produksi.

Rehearsal menyalin dependency yang sudah terpasang ke direktori sementara, melakukan `composer install --no-dev` tanpa jaringan, memeriksa **DB aktual** sebelum `migrate:fresh`, menguji migration/rollback satu langkah/remigrate, caches, command aman dan HTTP/health dengan debug mati. Hanya schema `tg_test` yang diubah. Kunci sementara/cache di direktori kandidat dihapus dalam finally; APP_KEY lingkungan asal tidak diubah. Rehearsal bukan backup/restore host atau deployment. Owner, terminal EA, staging dan penerimaan handoff tidak digantikan oleh fixture.
