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

## Kandidat rilis Fase 5 dan prosedur yang dapat diulang

Runner dan prasyarat lengkap ada di [tools/qa/README.md](tools/qa/README.md). Build menggunakan lockfiles dan `npm run build`, kemudian `python3 tools/qa/release_bundle.py --output qa-artifacts/release`. File `trader-garuda-candidate.tar.gz`, `.sha256` dan `manifest.json` mencatat source commit, dirty tree, SHA-256 isi runtime dan setiap file. Hanya manifest dengan `worktree_dirty=false` dari commit terlacak yang dipakai sebagai kandidat final. Kandidat tersedia pada workspace/artefak CI; tidak ada tag atau public release otomatis. Remote CI dan penerimaan infra/owner tetap harus diverifikasi.

Archive tidak membawa vendor/node_modules: operator menginstal `composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader` pada runtime PHP8.4 dengan bcmath/gd/sodium/pdo_mysql/mbstring, memakai lockfile. Frontend sudah dibangun; tidak perlu Node pada runtime. `.env.example` tetap kosong untuk secret. Jangan menjalankan seed test/demo di lingkungan penerima.

Checklist operator pada lingkungan yang **sudah disediakan**:

1. Cocokkan checksum archive dan inventory manifest; periksa commit/tag yang disetujui. Ekstrak ke direktori release baru; document root adalah public/.
2. Pasang dependencies production. Pasang konfigurasi resmi dari secret store, APP_DEBUG=false, HTTPS APP_URL, MySQL8.4, database cache/session dan private storage persisten. Pertahankan APP_KEY dan signing/encryption keys yang sama; jangan key:generate pada aplikasi berisi data.
3. Pastikan hak baca/tulis storage/bootstrap/cache, private EX5 tidak dipublikasi, disk local serve=false, scheduler/token health tersedia. Rekening/Telegram/produk/legal harus disetujui owner. Jangan mengubah sales flags sampai EA dan gate penerimaan lulus.
4. Setelah prosedur backup yang dimiliki infra terpenuhi, jalankan `php artisan migrate --force` (bukan fresh/reset). Jalankan `php artisan optimize`; periksa route, scheduler, private bearer health dan smoke alur simulasi yang telah disepakati tanpa transfer nyata.
5. Catat versi, hasil smoke, penerima dan persetujuan UAT. Kegagalan gate mempertahankan transaksi nyata tertutup.

**Rollback aplikasi aman:** operator mempertahankan release sebelumnya, konfigurasi/kunci dan storage persisten; hentikan penulisan sesuai prosedur infra, arahkan kembali ke release kode kompatibel yang telah disetujui, jalankan `php artisan optimize:clear` lalu `php artisan optimize` dari release tersebut, kemudian smoke health/auth/data. Pertahankan skema additive termasuk nullable review_note; jangan otomatis migrate:rollback/drop pada ledger, lisensi atau pembayaran. Bila kode lama tidak kompatibel, hentikan rollback dan siapkan forward fix yang direview. Switching release, backup/restore dan cutover tetap tanggung jawab infra; tidak dieksekusi oleh runner software.

Rehearsal lokal mengekstrak kandidat ke direktori sekali pakai, menguji dependencies tanpa dev, fresh migration **khusus tg_test**, rollback migration additive terakhir dan remigrate, config/route/view/event cache, command scheduler aman serta HTTP + bearer health dengan debug mati dan sales tertutup. Folder/kunci sementara dibuang. Ini membuktikan instalasi aplikasi lokal; bukan bukti staging, rollback host, EA MT5 atau UAT owner. Bukti hasil aktual ada di technical/validation/TG038-TG044.md.
