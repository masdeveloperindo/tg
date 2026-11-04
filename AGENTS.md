# AGENTS.md — Trader Garuda (aturan repository untuk Codex)

## Tujuan dan ruang lingkup
Bangun aplikasi Trader Garuda **Laravel 13 + Livewire 4 + Tailwind CSS 4 + MySQL 8.4** untuk penjualan EA MT5, lisensi online, broker IB, rebate, dan pencairan. **Hanya software development**: aplikasi, migration, API, keamanan aplikasi, pengujian, dokumentasi dan handoff. Tidak mengerjakan VPS, DNS, Nginx, TLS host, firewall, backup/restore fisik, deployment, ataupun cutover. Baca `SCOPE_OF_WORK.md` sebagai batas normatif.

## Dokumen sumber dan prioritas
1. `SCOPE_OF_WORK.md` (ruang lingkup) dan `PRD.md` (bisnis/fungsi).
2. `technical/DATABASE_SCHEMA.md`, `technical/API_CONTRACT.md`, `technical/ACTION_AUTH_MATRIX.md`, `technical/ROUTES_MATRIX.csv`, `technical/SECURITY_BASELINE.md`, `technical/TEST_PLAN.md`, `.env.example` (kontrak implementasi).
3. `TASKS.md` (urutan dan acceptance), `PROMPT.md` (petunjuk teknis lebih lengkap), `SOFTWARE_ACCEPTANCE.md` (penerimaan).
4. `html-reference/index.html` dan file HTML/CSS/JS-nya (referensi visual/interaksi **saja**, data simulasi bukan backend).
Jika bertentangan, **prinsip keamanan/akurasi data > PRD keputusan bisnis > kontrak teknis > HTML contoh**; catat konflik dan jangan diam-diam mengubah keputusan. Database **tetap MySQL 8.4**, bukan PostgreSQL.

## Keputusan yang tidak boleh dilanggar
- **Telegram manual murni**: hanya tautan chat ke akun pribadi owner; tanpa Telegram Bot API, webhook, callback, notifikasi otomatis, tombol approve dari Telegram. Admin memeriksa mutasi dan menekan konfirmasi di panel admin; backend baru menerbitkan key secara idempoten setelah transaksi sukses.
- Client tidak perlu mengikuti IB untuk membeli EA. Detail produk menyatukan preview, panduan, track record; jangan membuat nilai profit/testimoni palsu.
- **Uang**: tipe DECIMAL, ledger, transaksi DB dan row locking, pengendalian konkurensi, idempotency, audit perubahan; tidak boleh memakai float untuk saldo atau mempercayai nilai kalkulasi browser.
- **Lisensi**: satu key per produk/pemilik/akun MT5, verifikasi server + anti-replay, rahasia tidak masuk log. Validasi signature Ed25519 pada EA harus teruji, tidak dianggap lulus hanya karena API Laravel selesai. Indikator online **tidak dijual** sebelum PoC mekanismenya lulus.
- UI forest green/lime/warm bone/coral; modal untuk tindakan berisiko; loading yang jujur dari status server, bukan persentase palsu; UX client sederhana; seluruh halaman mobile-first.
- Tanpa data/secret nyata di Git; `.env` tidak di-commit, `.env.example` boleh. Jangan menjalankan transaksi asli dari demo HTML.

## Cara bekerja
- Pekerjaan awal: repository ini **hanya spesifikasi + demo HTML**, bukan aplikasi Laravel siap-run. Jika `artisan`/`composer.json` belum ada, **buat kerangka Laravel di root repo sambil mempertahankan semua dokumen**, jangan menimpa isi PRD/TASKS.
- Kerjakan **satu task atau satu kelompok kecil task yang saling terkait** dari `TASKS.md` per perubahan. Boleh menyelesaikan pekerjaan teknis yang tidak terblokir data owner; tandai dependensi eksternal tanpa membuat data palsu.
- Sebelum perubahan: identifikasi file spesifikasi, acceptance criteria, dependency dan tes yang akan dibuat. Selama perubahan: tulis kode nyata, tests positif-negatif dan otorisasi yang relevan. Sesudah: jalankan tes yang tersedia, tampilkan hasil aktual, dokumentasikan blocker secara eksplisit di `IMPLEMENTATION_STATUS.md` dan baru centang TASKS bila bukti lulus.
- Jangan mengaku tested, implemented atau go-live jika belum terbukti. **Software ready for handoff** berbeda dari izin **go-live**.
- Jika tool PHP/Composer/MySQL/Node tidak tersedia di lingkungan Codex, catat batas lingkungan dan lanjutkan apa yang masih bisa dilakukan; jangan mengubah scope menjadi pengadaan infrastruktur produksi.
