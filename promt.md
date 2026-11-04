# PROMPT.md — TRADER GARUDA v1.7 MASTER IMPLEMENTATION PROMPT

> **ATURAN WAJIB v1.7.1 — HANYA SOFTWARE DEVELOPMENT.** Baca `SCOPE_OF_WORK.md` sebelum mengerjakan task. Jangan melakukan/mengklaim provisioning VPS, OS, DNS/domain, Nginx, sertifikat SSL/TLS, firewall, instalasi server DB, cron host, backup/restore fisik, monitoring host, deploy maupun cutover. Implementasikan kebutuhan di **level aplikasi**, tes dengan lingkungan yang diberikan, dan buat dokumen handoff untuk tim infra. Kesiapan software bukan persetujuan go-live.

**Proyek:** Trader Garuda — Platform Penjualan EA MT5, Broker IB, dan Rebate
**Versi paket:** 1.7.1 (software-only) / 4 November 2026
**Tujuan dokumen:** instruksi terikat spesifikasi untuk developer atau AI coding agent membangun aplikasi Laravel yang **layak diuji untuk produksi**, bukan membuat mockup tambahan.
**Bahasa UI:** Indonesia. **Zona waktu tampilan:** Asia/Jakarta. **Penyimpanan waktu:** UTC.

> **PENTING: PAKET INI BELUM MERUPAKAN APLIKASI PRODUKSI.** Jangan menyatakan "siap go-live" sebelum source Laravel, integrasi MQL5, tes, audit keamanan, migrasi, bukti backup-restore dari pihak infra, pemeriksaan legal dan checklist `PRODUCTION_GATE.md` dinyatakan lulus berdasarkan bukti nyata.


## Lampiran teknis v1.7 — WAJIB dibaca sebelum coding

Baca secara urut: `PRD.md` → `technical/DATABASE_SCHEMA.md` → `technical/API_CONTRACT.md` → `technical/ACTION_AUTH_MATRIX.md` → `technical/ROUTES_MATRIX.csv` → `technical/SECURITY_BASELINE.md` → `technical/ENVIRONMENT.md` + `.env.example` → `TASKS.md` → `technical/TEST_PLAN.md` → `PRODUCTION_GATE.md` → referensi HTML.

Kunci DB = **MySQL 8.4 LTS**, 30 domain tables, `wallets` sebagai row-lock anchor, monetary DECIMAL, `api_nonces` untuk anti-replay. Kontrak API = `payload_b64` + Ed25519 signature + `kid`, diverifikasi di EA pure MQL5 sebelum klaim sukses. **Jangan menyatakan PoC MQL5 PASS sebelum benar-benar diuji.** Semua 71 route wajib dipetakan dan 23 mutating action wajib policy+transaction+audit+UI feedback.

Status artefak: **siap implementasi**, bukan aplikasi siap go-live. Kerjakan task bertahap; modul yang sudah lulus boleh maju, tetapi tidak menerima transaksi nyata sebelum semua GATE P0 lolos. Tidak boleh menambahkan Telegram bot, webhook, email/WA otomatis atau langkah ekstra kepada client. Setelah menyelesaikan task, catat bukti uji aktual dan perubahan status task, jangan ubah TODO menjadi DONE hanya karena draft/prototype.

## 0. Instruksi peran dan urutan membaca

Anda bertindak sebagai lead engineer Laravel, full-stack developer Livewire, perancang database, QA, dan reviewer keamanan. Kerjakan implementasi sungguhan sampai semua alur utama berjalan dengan data database dan pengujian otomatis. Jangan sekadar menyajikan pseudocode, screenshot, template HTML, atau daftar pekerjaan. Bila akses hosting / akun Telegram / kode sumber MQL5 / informasi broker belum diberikan, kerjakan bagian yang dapat diimplementasikan dan nyatakan blocker secara eksplisit; **jangan memalsukan pengujian ataupun status sukses**.

Baca **berurutan**:
1. `PRD.md` sebagai sumber aturan bisnis, data, URL, dan acceptance criteria.
2. `TASKS.md` dan `TASKS.csv` sebagai backlog kerja yang bisa dilacak; jangan centang sebelum tes lulus.
3. `html-reference/index.html` dan `html-reference/01-beranda.html` s.d. `19-admin-pengaturan.html` sebagai acuan visual/interaksi, **bukan backend**.
4. `html-reference/assets/style.css` untuk token gaya warna/tipografi/layout.
5. `html-reference/ROUTE_MAPPING.csv` untuk mapping route; untuk route yang tidak mempunyai HTML, implementasikan dari PRD.
6. `PRODUCTION_GATE.md` untuk gerbang go-live; `RELEASE_HANDOFF.md` untuk kontrak handoff ke infrastruktur; deployment aktual bukan tugas developer.

**Prioritas bila konflik:** keputusan bisnis PRD v1.6 > keamanan/ketepatan data > persyaratan interaksi UI di PRD > referensi HTML. Catat alasan jika ada deviasi. Jangan diam-diam menghilangkan fitur Must.

## 1. Stack tetap dan arsitektur sederhana

- Laravel **13**, PHP **8.3 atau lebih tinggi yang didukung**, Livewire **4**, Tailwind CSS **4**, Alpine.js bawaan Livewire, Blade; navigasi `wire:navigate`, render HTML publik untuk SEO. Gunakan dokumentasi versi yang cocok, bukan asumsi versi lama.
- Database: gunakan **MySQL 8.4 LTS** sesuai `technical/DATABASE_SCHEMA.md` (tidak memilih PostgreSQL). Gunakan migrations, indexes, FK, transaksi, row locking, `DECIMAL` untuk uang/lot, dan timezone UTC.
- Kebutuhan platform (diserahkan ke pihak infra): Linux + Nginx + PHP-FPM + HTTPS, pemanggilan Laravel Scheduler, backup fisik. **Tidak membutuhkan Redis, message broker, worker queue, microservices, WebSocket, atau SPA framework tambahan untuk MVP.** Jangan menambahkan infrastruktur hanya agar terlihat kompleks.
- Monolit Laravel yang modular secara folder/domain; server tetap satu sumber kebenaran. Source rapi, kode bisa diuji, migrasi bisa dijalankan pada database kosong, seed hanya demo/staging.
- Hanya admin dan client. Admin wajib TOTP beserta recovery codes/alternatif pemulihan aman; client tidak dipaksa 2FA, OTP, atau verifikasi email.
- Semua data sensitif disembunyikan dari log, URL, dan browser yang tidak berhak. License key dapat ditampilkan ulang kepada pemilik dari ciphertext terenkripsi; gunakan lookup token/hash terpisah, tanpa menyimpan plaintext di kolom biasa atau log.

## 2. Aturan bisnis yang TIDAK BOLEH diubah

### Telegram — manual murni
- Telegram **hanya tautan chat ke akun pribadi admin** `https://t.me/{username}`. Username disetel di `/admin/pengaturan`, kosong secara default. Tombol chat tidak mengirim pesan otomatis dan tidak mengubah status apa pun.
- Client melihat ringkasan pesanan, klik **Salin Detail Pesanan**, mengirim pesan dan bukti transfer secara **manual** di Telegram.
- Admin memeriksa **mutasi uang yang benar-benar masuk**, lalu membuka `/admin/pesanan`, menyetujui checkbox cek dana dalam **modal konfirmasi**, kemudian menekan **Konfirmasi Pembayaran** di panel. Baru backend dalam transaksi DB menandai Lunas dan menerbitkan/memperpanjang lisensi **tepat sekali**.
- **DILARANG:** Telegram Bot API, webhook/callback, token bot, polling, tombol approve di Telegram, notifikasi otomatis ke admin/client. Tidak ada webhook Telegram dalam daftar routes atau environment.

### Sewa dan lisensi
- MT5 saja. EA dapat dijual setelah verifikasi EA ↔ API **teruji di terminal MT5 nyata**; **indikator berlisensi disembunyikan/tidak dapat dipesan sampai PoC mekanisme aktivasi MQL5 lulus**. `WebRequest()` tidak tersedia langsung dalam indikator ataupun Strategy Tester.
- Harga tiap produk `base_price`, reset awal tahun, turun linear per bulan dengan persentase / pembulatan / minimum yang disetel admin. Checkout menampilkan nominal dan `Akses sampai 31 Desember YYYY`, **bukan** berjanji aktif 12 bulan.
- `price_locked`, `coverage_year`, mata uang, dan product/account disimpan pada order. Pesanan yang kedaluwarsa tidak boleh tiba-tiba berubah Lunas tanpa alur penanganan manual khusus dan audit.
- **1 key = 1 pemilik + 1 produk + 1 nomor akun MT5.** Renewal pada akun yang sama menggunakan key yang sama. Akun kedua memerlukan pembelian/lisensi terpisah. Ganti akun default sekali per masa lisensi melalui persetujuan admin.
- Renewal H-30 untuk tahun berikutnya jika masih aktif; ketika expired gunakan tarif tahun berjalan. Untuk harga tahun berikutnya yang belum disetel, gunakan `base_price` saat quote dibuat dan simpan snapshot, tampilkan eksplisit; jangan menetapkan harga retroaktif saat 1 Januari.
- Produk `.ex5` di private storage; unduh hanya pemilik yang punya hak akses, via signed temporary URL + server authorization, cek checksum. Per versi ada changelog dan status aktif.
- API verifikasi `POST /api/v1/licenses/verify`: akun, produk, status dan expiry dicek, rate limit dan anti-replay. Tanda tangan respons dengan public-key verification yang **benar-benar dibuktikan berjalan dalam MQL5**; jangan sekadar menulis `signature` JSON tanpa validasi di EA. Offline grace default maksimum 48 jam hanya setelah validasi sukses sebelumnya dan tidak melewati expiry. Respon penolakan online mengalahkan cache.
- Migrasi domain: host API lama tetap melayani selama periode relevan, bukan hanya mengirim `api_base_url` baru.

### Broker IB, rebate, saldo dan pencairan
- IB **opsional**; pembeli EA tidak wajib ikut broker. Client yang ikut IB dapat mengelola beberapa broker dan beberapa nomor akun, masing-masing perlu verifikasi manual admin.
- Client memiliki tiga tampilan jelas: **Broker IB Saya**, **Hasil Rebate**, **Pencairan Rebate**. Hasil meliputi broker, nomor akun, periode, lot, rebate USD, total, ditahan, dibayar, tersedia. Admin memiliki pasangan fitur **Verifikasi Akun IB**, **Input Rebate**, dan **Proses Pencairan**.
- Rebate dicatat admin sebagai **hak bersih client** per akun/periode; sumber/referensi unik mencegah duplikasi. Koreksi menggunakan baris pembalik/penyesuaian tanpa menghapus sejarah.
- Saldo tersedia = total rebate bersih terposting − withdrawal yang sudah dibayar − withdrawal yang sedang direservasi. **Pengajuan penarikan harus mengunci saldo pengguna dalam transaksi DB** agar dua request paralel tidak melampaui saldo. Gunakan nilai `DECIMAL` dan kalkulasi deterministik, jangan Float JS untuk penyimpanan angka uang.
- Request withdrawal menyimpan amount USD, kurs snapshot, estimasi IDR, rekening tujuan, idempotency key. Status `Diajukan`, `Diproses`, `Dibayar`, `Ditolak`, `Dibatalkan`. Status berbayar hanya jika admin sudah melakukan transfer nyata lalu mengisi referensi/bukti. Tolak/batal melepaskan reservasi **satu kali**.
- Refund manual dana nyata dipisahkan dari pencabutan lisensi; setiap tindakan punya jejak audit. Tidak ada API broker, CSV broker otomatis, ataupun payment gateway pada MVP.

### Konten dan kepercayaan
- Satu detail `/produk/{slug}` menyatukan preview, cara pakai, performa backtest/forward test, catatan parameter, changelog, FAQ, ulasan, harga. **Tidak ada halaman performa terpisah**.
- Admin upload `.ex5`, screenshot, panduan langkah bergambar, FAQ, edukasi, pengumuman, testimoni nyata berizin, broker, setelan SEO. Jangan menciptakan statistik profit, track record, review, atau klaim broker palsu. Bagian tanpa data disembunyikan/empty state jujur.
- Katalog/website publik dan client/mobile mengikuti palet Forest `#102821`, Warm Bone `#f5f3eb`, Lime `#dcff69`, Coral `#f77b60`; desain editorial trading lab, tidak kembali menjadi template biru SaaS generik. **Tema terang saja**.

## 3. UX: sederhana bagi client, jelas bagi admin

- Client mengalir **Pilih EA → buat pesanan → transfer & chat admin → Salin Key + Unduh & Pasang**. Registrasi sekali dan nomor MT5 cukup diminta jika dibutuhkan. Hindari wizard multi-halaman, field berulang, dan jargon keamanan.
- Navigasi client ringkas: Ringkasan, Produk Saya, Pesanan, Rebate (muncul sesuai konteks IB), Bantuan, Profil pada menu akun. Di dashboard tampil aksi paling relevan terlebih dahulu.
- Admin mempunyai Tugas Hari Ini: pesanan menunggu, verifikasi IB, withdrawal, lisensi berakhir, anomali, dan order lunas tanpa lisensi. Seluruh item membuka tugas nyata.
- Semua submit menyatakan fase **Idle → Validasi → Memproses → Hasil**. Pakai Livewire 4 `wire:loading`/`data-loading`, disable trigger, spinner teks `Memproses…`, validasi inline. Navigasi memakai progress/skeleton yang tidak menyebabkan layout bergeser.
- **Persentase progress upload hanya jika benar-benar berasal dari byte transfer yang dapat diukur** (contohnya event `livewire-upload-progress`), bukan setInterval atau delay demo; tahap pemeriksaan server tanpa persentase palsu.
- Uang/lisensi/konfirmasi admin selalu memakai **modal kustom** berisi kode, nominal/akun/rekening, akibat dan Batal/Konfirmasi. Admin cek mutasi dana wajib checkbox. Modal aksesibel: `role=dialog`, `aria-modal`, focus trap/restore, keyboard, serta tidak mudah ditutup ketika status permintaan belum pasti.
- `alert()`, `confirm()`, `prompt()` native browser dilarang. Toast untuk sukses kecil; modal status untuk tindakan berisiko; inline untuk kesalahan field.
- Pada putus koneksi setelah submit, jangan mengaku gagal/sukses secara spekulatif. Tampilkan `Status belum dapat dipastikan — Cek Status`; query sumber data dan retry aman berbasis idempotency, bukan mengulang commit buta.
- Responsif 360 / 768 / 1280 px, target sentuh ~44px, kontras teks, aria-live, reduced motion, tabel menjadi kartu/scroll mudah dipakai HP.

## 4. Alur transaksi atomik, authorisasi, dan integritas wajib

Buat service/action/use-case yang mudah diuji, misalnya:
- `CreateOrder` mengunci harga saat order dibuat dan memastikan hanya pemilik melihat order.
- `ConfirmManualPayment` (admin auth + TOTP session) mengecek status order, row lock pada order, menyimpan confirmed_by/check dana, menerbitkan atau memperpanjang lisensi secara atomik, unique key constraint dan idempotency; untuk order yang sudah Lunas, return status existing tanpa duplikasi.
- `VerifyLicense` dengan status enumerasi, replay protection, client account, expiry, tanda tangan yang diuji terhadap EA MQL5.
- `ApproveIbAccount` dengan perubahan status tersimpan + audit dan tidak menerima akun tanpa bukti cukup.
- `RecordRebate` memakai source_reference unik per akun/sumber dan entri immutable.
- `RequestWithdrawal` dengan lock pada wallet/user ledger, validasi available, transaksi reservasi sekali.
- `PayWithdrawal` dengan referensi transfer, tanggal/bukti, state transitions dan idempotency; `RejectWithdrawal` membalik reservasi sekali.
- `RefundOrder` mencatat proses manual terpisah dari `RevokeLicense`.

Otorisasi server wajib untuk **semua Livewire action**, route, file, `download`, modal, dan API. Tidak cukup menyembunyikan tombol. Gunakan Laravel Policy/Gate, CSRF web, rate limiting, anti-enumerasi order, HTTPS, cookies Secure/HttpOnly/SameSite, dan validasi upload MIME + ekstensi + ukuran. Pisahkan disk private `.ex5` dari public images. Audit transaksi uang dan key tanpa mencatat secret.

## 5. Database dan API

Implementasikan tabel PRD Bagian 7.3 dengan migration seimbang. Saran indeks/constraint minimal:
- `users.email` unique; `products.slug` unique; `orders.code` unique; `licenses.key_hash` unique.
- `orders` relasi client, produk, nomor MT5, snapshot harga dan coverage year; permintaan yang terlambat tidak membuat data rancu.
- Lisensi produk-akun: cegah duplikasi lisensi aktif atau ganda terbit; gunakan constraint yang **benar-benar didukung DB pilihan**, bukan mengandalkan partial unique index tidak didukung MySQL tanpa alternatif.
- `rebate_entries(trading_account_id, source_reference)` unik bila ref tersedia; import koreksi dengan reversal; `withdrawals.idempotency_key` unik per pemilik.
- Untuk saldo gunakan ledger/lock per user atau agregat yang selalu dihitung dari transaksi DB; jika ada cache saldo, jelaskan invalidasi dan pastikan sumber sebenarnya tidak berubah.
- Simpan URL media privat dan checksum; hindari menyimpan file dari nama upload asli sebagai path publik.
- API verify ada versi `/api/v1`, status resmi dari PRD, validasi timestamp + nonce, signature dan `request_id`; uji HTTP 200/4xx/429/5xx dan error jaringan di MT5, jangan berasumsi semua error sama.
- Metode admin/internal tidak boleh terekspos sebagai API tanpa autentikasi. `POST /api/v1/licenses/deactivate` ditunda/dinonaktifkan kalau skema otorisasinya belum terbukti perlu.

## 6. Route dan cakupan modul

**Wajib implementasikan keseluruhan route PRD Bagian 13**, bukan hanya file HTML yang tersedia. `html-reference/ROUTE_MAPPING.csv` hanya menunjukkan 19 layar sampel. Route publik minimal beranda, katalog, detail produk, broker, edukasi, FAQ, panduan dan halaman legal. Route client minimal dashboard, produk/key/download, pesanan, broker IB, rebate, penarikan dan profil. Route admin minimal dashboard, produk/versi/performa, pesanan/refund, lisensi/anomali, client, broker, verifikasi akun IB, rebate, penarikan, artikel, FAQ/panduan, testimoni, pengumuman, pengaturan, log audit. Implementasikan sitemap, robots/noindex, API verify dan pengalihan klik eksternal yang aman. Tidak ada route Telegram webhook.

## 7. Tahapan kerja — jangan lompat

**Fase A — Fondasi:** siapkan project Laravel/Livewire/Tailwind; test/lint/build; auth role/admin TOTP; layout responsive, design tokens, komponen UI; migrations & policies; seed *staging only*. Bukti: fresh migration dan suite auth/authorization lolos.

**Fase B — Produk dan lisensi EA:** katalog, detail produk, harga tahunan, checkout, invoice, chat Telegram manual, admin konfirmasi dana, license key & .ex5 private, API verifikasi dan integrasi EA MQL5. Bukti: order ganda idempoten, salah akun ditolak, expiry/offline diuji. **Jika source EA/MQL5 belum ada, catat blocker rilis.**

**Fase C — IB, rebate dan penarikan:** broker + referral, akun IB terverifikasi, input rebate, saldo immutable, reserve-withdraw-pay/refund. Bukti: dua request parallel, double click, reject, timeout, audit sesuai angka.

**Fase D — Konten, observabilitas aplikasi dan polishing:** panduan, artikel, testimoni, FAQ, pengumuman, anomali, audit, SEO, log/health/scheduler aplikasi, kebutuhan backup/monitoring untuk handoff infra, media dan UI states. Bukti: route PRD lengkap, accessibility/mobile, mode jaringan lambat, keamanan.

**Fase E — QA, UAT dan handoff software:** jalankan E2E dan tes beban ringan pada lingkungan yang disediakan, cek integrasi MT5 nyata, validasi migration/rollback aplikasi dan bukti UAT. Serahkan artefak+instruksi ke pihak infra melalui `RELEASE_HANDOFF.md`. Backup/restore fisik, deploy staging/produksi dan keputusan go-live milik infra/owner.

Jangan meminta ulang keputusan yang sudah dikunci PRD. Jika ada nilai belum tersedia (contoh username Telegram asli, rekening pembayaran, harga aktual, kurs USD/IDR, minimum withdrawal, izin broker, file EA), buat `settings` tanpa dummy produksi, tampilkan status belum dikonfigurasi kepada admin, dan tandai sebagai blocker go-live.

## 8. Testing yang wajib ditulis dan dieksekusi

- **Unit/domain:** harga Januari/Februari/Desember & pembulatan, expiry 31 Desember WIB, renewal aktif vs expired, money DECIMAL dan lot.
- **Feature/auth:** login/register, policy lintas client, admin TOTP, password reset manual, seluruh file/private media/route terproteksi, XSS/CSRF/upload invalid.
- **Payment:** hanya admin dapat konfirmasi setelah cek dana; klik tombol dua kali, paralel, timeout lalu Cek Status, retry menghasilkan satu order lunas dan satu key; bukti via Telegram tidak mengaktifkan lisensi.
- **EA/API:** license valid, invalid, account_mismatch, server_mismatch, expired, revoked, suspended, update_required, replay timestamp/nonce, signature tamper, offline 48 jam, WebRequest allowlist, Strategy Tester mode.
- **IB:** broker lebih dari satu, verifikasi manual, referensi rebate duplikat, koreksi negatif, saldo per akun/semua broker, akun belum verified tidak dapat kredit sesuai aturan.
- **Withdrawal:** request serentak sama saldo, duplicate idempotency, invalid/negative/over-balance, reserve, paid once, rejected restore once, timeout + Cek Status; kurs snapshot tetap.
- **UI/e2e:** 19 halaman referensi + seluruh route PRD, desktop/mobile 360/768/1280, modal keyboard, upload progress nyata, empty/error states, no native alert/confirm/prompt, log JS tanpa error; modal status final harus database-driven.
- **Operations:** `composer test` atau `php artisan test`, asset build, migrasi DB kosong, rollback kode/migration aplikasi yang aman, job Scheduler teruji, log tidak memuat key/rekening/secret; dokumentasikan kebutuhan cron/backup/Nginx/HTTPS kepada tim infra, bukan menyiapkannya sendiri.

**Tidak cukup:** hanya lulus Playwright pada HTML demo, mengubah state `localStorage`, membuat screenshot, atau mendeskripsikan hasil tanpa log test. Simpan test report dengan perintah, lingkungan, hasil, dan bug yang tersisa.

## 9. Format hasil kerja setiap tahap

Setiap sesi engineering harus menyerahkan:
1. Ringkasan fitur yang **betul-betul** selesai dan daftar file/source yang berubah.
2. Perintah menjalankan aplikasi lokal dan konfigurasi environment yang diperlukan **tanpa nilai secret**.
3. Migrasi/schema yang dibuat, route yang aktif, serta kebijakan role.
4. Bukti hasil test automatis (perintah, jumlah, pass/fail); screenshot hanya sebagai pelengkap.
5. Blocker, risiko, dan daftar fitur yang belum selesai; jangan mengatakan 100% ketika masih staging/demo.
6. Update `PRODUCTION_GATE.md` status PASS/FAIL/BLOCKED beserta bukti dan penanggung jawab.

**Definition of Done fitur:** UI sesuai HTML/PRD + server action bekerja + DB persist + auth policy + loading/modal/error/empty + test lolos + responsive + audit untuk uang/lisensi. Jika satu poin belum ada, status fitur masih *in progress*.

## 10. Pelarangan ketat

JANGAN: menggunakan kode demo `localStorage` sebagai database produksi; membuat fiktif profit/testimoni; menggunakan popup native; mengirim chat otomatis; menambahkan Telegram bot; memasang payment gateway; mewajibkan IB saat checkout EA; memakai `float` untuk saldo uang; mengakui pembayaran dari screenshot/Telegram saja; menerbitkan key sebelum admin cek mutasi; menyimpan `.ex5` dalam public folder; memperlihatkan secret/license key di log; menambah queue worker sebelum perlu; menyatakan rilis siap hanya karena halaman HTML bisa diklik.

## 11. Sumber rujukan teknis yang perlu dicek saat coding

- Laravel 13: https://laravel.com/docs/13.x/releases (PHP minimum 8.3)
- Livewire 4 loading: https://livewire.laravel.com/docs/4.x/wire-loading
- Livewire 4 upload progress: https://livewire.laravel.com/docs/4.x/uploads
- Livewire 4 navigate: https://livewire.laravel.com/docs/4.x/navigate
- MetaQuotes MQL5 `WebRequest()`: https://www.mql5.com/en/docs/network/webrequest

**Permintaan akhir:** Bangun aplikasi lengkap berdasarkan paket ini secara bertahap sampai siap menjalani *production go-live gate*. Untuk setiap gap yang perlu input data dari pemilik, sediakan admin setting yang jelas, bukan data dummy tersembunyi. Jika infra/staging belum disediakan, selesaikan semua yang dapat diuji secara lokal dan tandai tes integrasi yang bergantung pada infra sebagai **external dependency**; jangan membuat pekerjaan infra. Status akhir developer adalah **SOFTWARE READY FOR HANDOFF** bila `SOFTWARE_ACCEPTANCE.md` lulus; go-live diputuskan owner/infra secara terpisah.

## 12. Checklist pelaksanaan

Gunakan `TASKS.md` sebagai urutan kerja formal dan `TASKS.csv` untuk issue tracker; setiap penyelesaian wajib menyertakan kode, test, review dan bukti. `TASKS.md` **tidak** mengubah cakupan/keputusan pada PRD. Jika task berkonflik dengan PRD, PRD adalah rujukan utama.
