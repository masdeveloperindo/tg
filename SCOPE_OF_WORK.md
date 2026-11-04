# SCOPE_OF_WORK.md — Batas Tanggung Jawab Trader Garuda v1.7.1

**Keputusan final:** pekerjaan tim ini **khusus pengembangan software Laravel dan integrasi EA MT5**. Infrastruktur diadakan, dikonfigurasi, dioperasikan, dan diaudit oleh **tim/pihak lain**. Dokumen ini bersifat normatif dan mengungguli tugas infrastruktur yang mungkin masih disebut sebagai konteks atau dependensi di dokumen lama.

## Termasuk pekerjaan tim software

- Backend Laravel 13, Livewire 4, Tailwind CSS 4, desain, UI/UX, aksesibilitas, modal, loading dan progress nyata.
- Arsitektur aplikasi, migrasi/skema **database secara logis**, model, constraint, transaksi, indexing query dan pengujian konkurensi; **bukan** pemasangan/administrasi mesin database.
- Autentikasi, otorisasi, TOTP admin, CSRF, rate limit, session/cookie pada **level aplikasi**, validasi input, enkripsi di kode, audit log dan penanganan error aman.
- Produk, katalog, pesanan/pembayaran manual via chat Telegram pribadi admin, lisensi EA, API verifikasi, file privat, IB, ledger rebate, penarikan, admin, SEO dan konten.
- Laravel Scheduler: **definisi job, idempotensi, test, dan dokumentasi intervalnya**; bukan konfigurasi cron di server.
- API kesehatan aplikasi, log dan metrik **yang diproduksi kode**; bukan layanan alerting/monitor server.
- `.env.example`, daftar variabel dan persyaratan konfigurasi; **bukan** provisioning secret, VPS, DNS, TLS, Nginx, firewall, server atau DB instance.
- Automated tests, tes browser/end-to-end, keamanan aplikasi, verifikasi MQL5 nyata dengan lingkungan yang disediakan, build artefak, migration scripts, instruksi dan bukti *software release handoff*.
- Script/query pembanding integritas data pasca-restore **jika diperlukan**; tim infra yang membuat dan me-restore backup fisik.

## Bukan tanggung jawab tim software

- Pembelian/provisioning VPS, OS, domain atau subdomain publik (termasuk `tradergaruda.sukaphp.com`), DNS, reverse proxy/Nginx/PHP-FPM host, sertifikat SSL/TLS, firewall, jaringan, WAF, server hardening.
- Instalasi/operasional MySQL di host, pembuatan user/secret produksi, instalasi cron pada host, konfigurasi penyimpanan/akses server, monitoring host/jaringan, uptime/SLA infra.
- Backup fisik DB/file, penyimpanan offsite, rotasi backup, restore infrastruktur, snapshot, disaster recovery host, deploy ke produksi, rollback host, dan routing domain lama saat migrasi.
- Persetujuan hukum/izin broker merupakan tanggung jawab pemilik/legal; tim software hanya menyediakan fitur dan konten untuk menampilkan keputusan yang disahkan.

## Titik serah-terima dan dependensi

| Artefak dari software | Diterima/dijalankan oleh pihak infra/owner |
|---|---|
| Kode Laravel + composer.lock + aset build | Runtime PHP kompatibel, web server, alamat publik, deploy artefak |
| Laravel migration + kebutuhan MySQL 8.4 | Provision server/DB dan kredensial aman, jalankan proses deploy sesuai prosedur |
| `.env.example` + matriks konfigurasi | Mengisi rahasia produksi, domain/APP_URL, kunci dan kebijakan operasional |
| Jadwal Laravel Scheduler dan command yang aman | Memasang/menjaga cron atau layanan penjadwal di host |
| Storage disk requirements, checksum dan verifikasi akses aplikasi | Penyediaan storage, backup dan restore fisik |
| `/health`/sinyal error aplikasi, log terstruktur | Alerting, monitoring uptime, pemantauan kapasitas server |
| Automated QA & aplikasi acceptance report | Staging, uji infra, audit TLS/backup, final cutover |
| Kompatibilitas API dan daftar host lama/baru | DNS/certificate/old-host availability untuk migrasi domain |

## Aturan penerimaan

- **Task software dinyatakan DONE berdasarkan kode dan uji aplikasi**, tanpa menunggu penyediaan VPS/SSL/backup oleh tim lain. Jika staging belum tersedia, laporkan **dependency external** untuk tes integrasi di lingkungan tersebut, bukan tambahkan task provisioning kepada developer.
- Status **SOFTWARE READY FOR HANDOFF** dapat tercapai setelah semua kriteria aplikasi P0 lulus; **GO-LIVE** tetap keputusan pemilik/pihak infrastruktur setelah persyaratan eksternal terpenuhi. Kedua status tidak boleh disamakan.
- Risiko atau batas minimum infra boleh dijelaskan sebagai **requirements dan handoff**, bukan menjadi aksi yang wajib dieksekusi tim software.
- Telegram **hanya** chat akun pribadi, tanpa bot/webhook/konfirmasi otomatis; lisensi baru muncul sesudah admin mengonfirmasi pembayaran dalam aplikasi.
