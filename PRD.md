# PRD Trader Garuda v1.7 — Produk, UI dan Kontrak Teknis

> **BATAS SCOPE v1.7.1:** Tim proyek ini hanya membangun **aplikasi Laravel dan integrasi software MT5**. VPS, DNS/domain, Nginx, sertifikat SSL/TLS, firewall, hosting, backup/restore fisik, cron host, monitoring server, deployment dan cutover ditangani pihak lain. Detail infrastruktur dalam PRD adalah **persyaratan untuk handoff**, bukan task software. Lihat `SCOPE_OF_WORK.md` (normatif), `SOFTWARE_ACCEPTANCE.md` dan `RELEASE_HANDOFF.md`.

**Versi:** 1.7 (Handoff development final + acceptance produksi; Telegram pribadi manual, desain V2.3/V2.4)\
**Tanggal:** 4 November 2026\
**Stack:** Laravel 13, Livewire 4, Tailwind CSS, Full SPA (wire:navigate)\
**Status:** Final sebagai spesifikasi untuk implementasi; BUKAN bukti bahwa aplikasi sudah diprogram, dites server, atau layak go-live. Gate wajib pada Bagian 17 dan `PRODUCTION_GATE.md`.

---

**Fondasi v1.3:** Memasukkan temuan audit tanpa menambah kerumitan client: satu detail produk untuk preview + performa; pendaftaran dan checkout singkat; broker/IB opsional; pengingat dan riwayat lisensi tetap di halaman yang sudah ada; refund dipisah dari pencabutan lisensi; aturan key per akun MT5; keamanan panel admin; rebate aman dari saldo ganda; pengumuman admin; metadata backtest, privasi, backup, URL dan acceptance test dilengkapi. **Prinsip:** kompleksitas disimpan di backend dan admin, bukan dialihkan menjadi formulir baru di sisi client.

**Perubahan v1.4 (desain dikunci):** Acuan tata letak & identitas visual adalah `trader_garuda_frontend_v2_2_admin_lengkap.html` (V2.2); **acuan modal/feedback interaktif** adalah `trader_garuda_frontend_v2_3_modal_loading.html` (V2.3). Seluruh fitur demo diselaraskan dengan kebutuhan produksi; desain, palet dan hierarki menjadi referensi, **bukan** berarti angka demo, popup bawaan browser, navigasi hash, atau data in-memory boleh dibawa ke produksi. Ditambahkan spesifikasi halaman/komponen, interaksi, loading & progress, modal konfirmasi, alert, empty/error/success states, aksesibilitas, serta uji alur admin ↔ client (lihat 5.16–5.20, 11, 14–15). Tujuan: hasil implementasi **tidak berubah menjadi template biru generik**, dan client tidak dibebani proses teknis.

**Keputusan final v1.5 — Telegram 100% manual:** Telegram digunakan **hanya untuk membuka percakapan langsung dengan akun pribadi pemilik/admin**. Client menekan **Chat Admin di Telegram**, mengirim ringkasan pesanan dan bukti transfer secara sadar/manual, lalu admin memeriksa rekening dan **masuk ke panel `/admin/pesanan` untuk menekan Konfirmasi Pembayaran**. Telegram **tidak mengirim pesan otomatis**, **tidak memakai bot**, **tidak memakai webhook/callback**, **tidak mengaktifkan lisensi**, **tidak menjadi tombol approve**, dan **tidak membutuhkan token/secret/API**. Setelah admin benar-benar mengonfirmasi di website, backend secara otomatis dan idempoten menerbitkan/memperpanjang lisensi. Rebate dan pencairan juga diproses manual dari panel admin. Seluruh bagian dokumen ini memakai ketentuan tersebut.

**Catatan tentang prototype:** acuan visual/interaksi yang dibundel adalah folder `html-reference/`; referensi V2.2/V2.3 sebelumnya hanya sejarah pengembangan. Jika ada label "Bot", "Telegram otomatis", atau tautan komunitas di prototype lama, **abaikan dan ganti** dengan aturan v1.5 ini saat implementasi. Prioritas keputusan adalah PRD v1.5, bukan teks demo lama.

**Aturan UX tidak boleh dilanggar:** client hanya melihat pilihan yang relevan, tombol tindakan berikutnya jelas, tidak diminta mengisi data yang sudah ada, tidak dipaksa ikut broker untuk sewa EA, tidak menerima email/WA otomatis, dan tidak perlu paham API untuk menggunakan produk.

## 1. Ringkasan Produk

Aplikasi web untuk menjual indikator dan Expert Advisor (EA) forex untuk MetaTrader 5 (MT5), sekaligus menjadi corong promosi program Introducing Broker (IB). Setiap produk yang dibeli memiliki **kode unik aktivasi (license key)** yang diikat ke akun trading pengguna. Member dapat login ke area member untuk mengunduh produk, mengelola lisensi, dan **memantau rebate** dari akun trading yang terdaftar lewat link IB.

**Nama brand & domain:** Trader Garuda (**tradergaruda.sukaphp.com**). Catatan branding: logo tidak boleh memakai lambang Garuda Pancasila; cek merek di DJKI sebelum peluncuran; cantumkan keterangan bahwa layanan ini milik swasta dan bukan lembaga resmi.

**Domain:** aplikasi berjalan di `tradergaruda.sukaphp.com` tanpa domain baru. URL dibaca dari `.env`. Saat migrasi, server pada domain lama **harus tetap dapat melayani verifikasi** selama masa transisi/lisensi yang telah terjual; `api_base_url` baru saja tidak cukup bila domain lama sudah mati. Client tidak diminta melakukan migrasi manual kecuali benar-benar diperlukan.

### 1.1 Tujuan Bisnis

1. Menghasilkan pendapatan dari sewa tahunan indikator dan EA MetaTrader 5.
2. Mengakuisisi klien baru untuk beberapa broker mitra lewat link IB dengan promo rebate yang jelas.
3. Menjaga retensi lewat dashboard rebate dan penarikan rebate yang transparan.
4. Melindungi produk dari pembajakan lewat kode aktivasi yang terikat ke akun MT5.
5. Menjaga biaya operasional rendah: transfer dan komunikasi pembayaran melalui **chat Telegram pribadi admin**, tanpa bot/notifikasi otomatis; admin memverifikasi pembayaran, menginput rebate, serta mencatat pencairan melalui panel website.

### 1.2 Indikator Keberhasilan (KPI)

| KPI | Target 6 bulan |
| --- | --- |
| Pengunjung menjadi pesanan sewa | ≥ 2% |
| Pembeli menautkan akun trading di salah satu broker | ≥ 30% |
| Waktu dari klik Konfirmasi admin sampai lisensi aktif | < 1 menit |
| Aktivasi tidak sah / pembajakan | < 5% |
| Uptime API lisensi | ≥ 99,5% |

## 2. Persona Pengguna

| Persona | Deskripsi | Kebutuhan Utama |
| --- | --- | --- |
| Pengunjung | Trader ritel yang mencari tools | Lihat katalog, harga sewa hari ini, promo broker, kalkulator rebate |
| Client | Penyewa produk dan/atau klien IB | Sewa, bayar via transfer, chat Telegram admin untuk bukti bayar, unduh .ex5, aktivasi, pantau rebate, tarik rebate |
| Admin | Pemilik bisnis (satu orang di MVP) | Konfirmasi pembayaran, kelola produk, input rebate, proses penarikan, atur konfigurasi |

## 3. Ruang Lingkup

### 3.1 Dalam Lingkup (MVP)

- Beranda, katalog, **satu halaman detail per produk** (deskripsi + preview + cara pakai + track record + FAQ + ulasan + harga), edukasi, broker, kalkulator rebate.
- Registrasi singkat; checkout cukup akun MT5 bagi pembeli EA/indikator; kontak bisa Telegram **atau** WhatsApp; data tersimpan dan dipakai kembali.
- **Pembayaran manual sepenuhnya:** client transfer, mengirim bukti ke **Telegram pribadi admin** lewat tombol Chat Admin, lalu **admin mengonfirmasi dari panel website**, bukan dari Telegram. WhatsApp boleh menjadi kontak alternatif manual jika admin mengaktifkannya.
- Lisensi MT5 dan API verifikasi, unduhan `.ex5` privat, pembaruan versi; **EA diaktifkan setelah verifikasi teknis, indikator berlisensi menunggu proof of concept (PoC) kompatibel**.
- Dashboard client yang sederhana: **Ringkasan, Produk Saya, Pesanan, Rebate (opsional), Bantuan**; untuk client IB, kelompok Rebate memuat **Hasil Rebate, Broker IB Saya, Pencairan Rebate** (bagian yang sudah dicontohkan prototype V2.2), tanpa mewajibkan client pembeli EA ikut IB. Detail teknis, riwayat verifikasi, dan pengingat tetap di halaman lisensi terkait.
- Broker IB dan rebate **hanya dipakai jika client tertarik**, tidak menjadi syarat untuk membeli produk.
- FAQ, panduan instalasi bergambar, pengumuman, testimoni nyata yang diinput manual admin.
- Admin: Tugas Hari Ini, CRUD, log, monitoring anomali, refund manual, rekonsiliasi pesanan-lisensi, penyimpanan saldo yang aman.
- SEO dasar, privasi, audit serta kebutuhan backup/pemulihan yang didokumentasikan untuk pihak infra.

### 3.2 Di Luar Lingkup (MVP)

- Payment gateway, **Telegram Bot API, bot notifikasi, webhook Telegram, tombol approve di chat**, OTP/2FA **untuk client**, verifikasi email, email/WA otomatis, push notification.
- Integrasi broker/CSV otomatis, MT4, aplikasi mobile native, affiliate, marketplace.
- Laporan analitik yang kompleks, chatbot, fitur sosial, sistem tiket, dan formulir publik untuk mengirim testimoni.
- Lisensi indikator yang belum dibuktikan bekerja di MT5; produk indikator **tidak boleh dipasarkan sebagai berlisensi siap pakai** sampai PoC dan uji instalasi lulus.

### 3.3 Prinsip UX Client (Wajib)

1. **Pilih produk → checkout singkat → bayar → key + unduhan + panduan dalam satu tempat.** Tidak ada wizard panjang.
2. Registrasi satu kali: nama, email, password, pilihan kontak (Telegram atau WA) dan nilai kontak. Tidak minta dokumen identitas secara rutin.
3. Saat checkout cukup **nomor akun MT5**; nama server dan jenis akun dapat dilengkapi otomatis dari EA saat terhubung atau dikelola admin jika diperlukan. Jangan ulangi isian profil.
4. Di dashboard tampil status manusiawi seperti **Aktif**, **Berakhir 7 hari lagi**, **Menunggu pembayaran**, **Perlu bantuan**; kode teknis ada di bagian detail opsional.
5. Maksimal satu CTA utama per tugas: **Bayar**, **Unduh & Pasang**, **Perpanjang**, atau **Tarik Rebate**. Bantuan selalu ada.
6. Client tidak harus mengaktifkan IB, mengisi statistik performa, mengelola signature, atau meninjau log IP.
7. Form rebate/penarikan muncul **hanya** jika client memilih layanan IB. Status kesalahan ditampilkan dengan bahasa biasa dan tautan bantuan.
8. Setiap klik yang memproses data memberi tanda visual: **memuat, menyimpan, berhasil, gagal**, tanpa tombol seolah tidak merespons; aksi uang/lisensi menggunakan **modal konfirmasi khusus**.
9. **Dilarang `window.alert`, `window.confirm`, `window.prompt`** dalam frontend final: ganti modal/tampilan validasi yang dirancang konsisten (Bagian 5.17). Notifikasi ringan boleh berbentuk toast, bukan popup yang mengganggu.
10. Setiap saldo, pengajuan pencairan, dan perubahan lisensi harus menampilkan **status hasil yang benar-benar diterima dari server**, bukan angka/progress simulasi.

## 4. Keputusan Utama & Asumsi

| Topik | Default sederhana untuk PRD v1.3 |
| --- | --- |
| Domain | `tradergaruda.sukaphp.com`; endpoint domain lama dipertahankan saat migrasi |
| Platform | MT5 saja (`.ex5`); EA dahulu, indikator menunggu PoC lisensi |
| Masa akses | **Per tahun kalender, sampai 31 Desember**, bukan otomatis 12 bulan sejak beli; tanggal ditampilkan jelas sebelum bayar |
| Harga | Harga awal tiap 1 Januari; turun X% **linear** setiap bulan dengan harga minimum dan pembulatan dari admin |
| Perpanjangan | Tombol muncul H-30; pembelian perpanjangan saat masih aktif memperpanjang ke 31 Desember **tahun berikutnya** dan menggunakan harga awal tahun berikutnya. Jika sudah kedaluwarsa, memakai harga bulan berjalan untuk tahun kalender berjalan. Key sama |
| Pembayaran | Transfer/e-wallet manual; bukti dikirim client melalui chat Telegram pribadi; **konfirmasi hanya dari `/admin/pesanan`** |
| Kontak | **Telegram pribadi admin adalah jalur utama chat**; WhatsApp hanya opsi tambahan jika admin mengaktifkannya. Tidak ada bot/pesan otomatis ke siapa pun |
| Login | Email + password; tanpa OTP/email verification bagi client; admin dilindungi lebih ketat |
| Lisensi | 1 key untuk **1 client + 1 produk + 1 akun MT5**; akun kedua perlu sewa kedua |
| Ganti akun | Client cukup klik Ajukan Ganti Akun, isi akun baru; admin setujui, default maksimal 1 kali per masa lisensi |
| Broker/IB | Opsional dan terpisah dari pembelian EA/indikator; verifikasi dan rebate manual |
| Kurs/minimum penarikan | Satu kurs admin; minimum penarikan ditetapkan admin dan ditampilkan sebelum pengajuan |
| Tampilan | Bahasa Indonesia, tema terang saja, mobile-first |

**Tidak perlu diputuskan client:** keamanan API, tarif rebate detail, status teknis, saldo ledger, flag anomali dan proses pemulihan. Semuanya dikelola backend/admin.

### 4.1 Aturan Telegram Pribadi dan Konfirmasi Manual (Keputusan Final)

| Aspek | Keputusan wajib |
| --- | --- |
| Telegram yang dituju | **Akun Telegram pribadi milik admin/pemilik**, berupa username publik `@username` yang dikonfigurasi sendiri oleh admin; jangan memakai username contoh saat produksi |
| Tombol website | **Chat Admin via Telegram** membuka `https://t.me/{username_tanpa_@}` melalui tab baru; bisa lewat `/go/telegram` untuk mencatat klik, tanpa mengirim pesan apa pun |
| Ringkasan pesanan | Tombol **Salin Detail Pesanan** menyalin kode, produk, nominal, rekening tujuan, masa aktif dan nomor akun MT5 secukupnya; client menempelkan teks itu sendiri pada chat |
| Bukti transfer | Client mengirim screenshot/bukti melalui chat pribadi secara **manual**; website tidak meminta bot membaca/menerima file otomatis |
| Tindakan admin | Admin melihat chat, **memastikan dana benar-benar masuk ke rekening/e-wallet**, mencocokkan kode dan nominal di website, baru menekan **Konfirmasi Pembayaran** di panel admin |
| Aktivasi lisensi | Setelah klik konfirmasi admin berhasil diproses backend, sistem menerbitkan/perpanjang key **sekali**; chat Telegram sendiri tidak pernah mengaktifkan apa pun |
| Komunikasi lanjut | Tanya jawab, masalah instalasi, pengingat, verifikasi, dan follow-up dilakukan admin secara manual di Telegram. Status pesanan/lisensi tetap tercatat di website |
| Integrasi | **Tidak ada Telegram Bot API, token bot, chat ID, webhook, callback, polling Telegram, atau pengiriman pesan otomatis** |
| Jika admin belum merespons | Pesanan tetap **Menunggu Pembayaran** sampai admin memeriksa atau batas pesanan berakhir. Client dapat membuka status pesanan dan tombol Hubungi Admin lagi |

**Satu jalur komunikasi, satu tempat untuk transaksi:** Telegram untuk percakapan manusia; website admin untuk keputusan dan audit. Bot bukan fallback ataupun rencana MVP. Bila admin ingin membagikan informasi promosi di komunitas Telegram kelak, itu fitur terpisah di luar MVP, bukan CTA pembayaran.

**Catatan keterbukaan harga:** katalog, checkout dan invoice wajib menampilkan, misalnya, **"Akses hingga 31 Desember 2026"**. Dilarang memakai copy "aktif 12 bulan" untuk pembelian di tengah/akhir tahun. Ketentuan produk dan harga ditampilkan sebelum pembayaran; ini bukan harga otomatis proporsional per hari.

## 5. Fitur & Kebutuhan Fungsional

### 5.1 Halaman Publik

| ID | Kebutuhan | Prioritas |
| --- | --- | --- |
| PUB-01 | Landing page: hero, produk unggulan, promo broker, FAQ, tombol Sewa dan Buka Akun | Must |
| PUB-02 | Katalog indikator dan EA MT5 dengan pencarian | Must |
| PUB-03 | **Satu halaman detail produk** (lihat 5.13) memuat deskripsi, preview dan panduan penggunaan, performa/track record, changelog, FAQ produk, testimoni terkait, serta harga sewa saat ini. **Tidak ada URL performa terpisah** | Must |
| PUB-04 | Halaman Promo Broker: kartu per broker (logo, rebate per lot dalam USD, syarat, tombol Buka Akun lewat link IB) | Must |
| PUB-05 | Kalkulator rebate: pilih broker, isi lot per bulan, tampil estimasi rebate USD dan IDR (kurs dari admin) | Must |
| PUB-06 | Disclaimer risiko trading dan keterangan bahwa layanan ini milik swasta | Must |
| PUB-07 | Navigasi SPA dengan `wire:navigate` dan prefetch saat hover | Must |
| PUB-08 | Halaman Edukasi (lihat 5.10): artikel signal dan cara trading dengan gambar | Must |
| PUB-09 | Tombol **Chat Admin** ke **akun Telegram pribadi admin** di seluruh frontend (publik dan area client): tombol melayang `@persist`, kontak/footer dan halaman pesanan. URL `t.me/{username}` diatur admin; link kosong → tombol disembunyikan dan konfigurasi ditandai belum lengkap di admin. Dibuka tab baru, klik boleh dicatat, **tidak mengirim chat otomatis** | Must |
| PUB-10 | FAQ umum dan panduan instalasi bergambar (memasang di MT5, cara aktivasi sesuai jenis EA/indikator); dikelola admin (lihat 5.14) | Must |
| PUB-11 | Testimoni dan ulasan yang diinput **hanya melalui admin** tampil di beranda dan detail produk (lihat 5.14); tidak ada form ulasan publik | Must |

### 5.2 Akun Client

| ID | Kebutuhan | Prioritas |
| --- | --- | --- |
| AUTH-01 | Registrasi ringkas: nama, email, password, **pilih Telegram/WhatsApp lalu isi kontak**; simpan agar tidak diulang | Must |
| AUTH-02 | Login email + password; tanpa OTP, 2FA, verifikasi email untuk client | Must |
| AUTH-03 | Reset password manual: tombol **Lupa Password? Hubungi Admin**; admin cek dari kontak terdaftar dan data pesanan secara proporsional, buat password sementara sekali pakai, cabut sesi lama, client wajib ganti saat login | Must |
| AUTH-04 | Profil: ubah kontak dan password; perubahan kontak sensitif memerlukan konfirmasi admin bila akun memiliki transaksi | Must |
| AUTH-05 | Role `admin` dan `client`, middleware/policy di semua aksi, client hanya melihat datanya sendiri | Must |
| AUTH-06 | Rate limit login/reset, proteksi brute force, sesi aman; **TOTP gratis khusus admin** untuk tindakan berisiko (tidak diterapkan ke client) | Must |

Tidak ada upload KTP, OTP SMS, form verifikasi email, atau kewajiban menghubungkan akun IB untuk client biasa.

### 5.3 Harga Sewa Per Tahun Kalender (Dinamis)

Pada 1 Januari harga kembali ke harga awal per produk; setiap bulan turun X% **secara linear** sampai harga minimum. Rumus: `harga = max(harga_minimum, harga_awal × [1 − X% × (bulan − 1)])`, lalu dibulatkan sesuai pengaturan admin. Mode berantai **ditunda** agar admin tidak bingung.

Contoh dengan harga awal Rp250.000, X=5%, tanpa mencapai batas harga minimum:

| Bulan | Harga sewa | Masa aktif |
| --- | ---: | --- |
| Januari | Rp250.000 | Sampai 31 Desember tahun tersebut |
| Februari | Rp237.500 | Sampai 31 Desember tahun tersebut |
| Juni | Rp187.500 | Sampai 31 Desember tahun tersebut |
| Desember | Rp112.500 | Sampai 31 Desember tahun tersebut |

- Tampilkan **harga final dan tanggal berakhir yang spesifik** di katalog, halaman produk, checkout, pesanan, invoice dan dashboard, khususnya pembeli pada November/Desember.
- Pesanan menyimpan harga dan `coverage_year` saat dibuat, agar perubahan tanggal dan aturan harga tidak mengubah tagihan yang sudah dikonfirmasi.
- Pesanan menunggu bayar punya batas waktu dari admin. Setelah kedaluwarsa, admin menyelesaikan pembayaran yang telanjur masuk secara manual (buat pesanan baru/penyesuaian/refund), **jangan mengaktifkan otomatis dengan harga berbeda**.
- Tombol **Perpanjang** muncul H-30, otomatis memakai key lama. Saat masih aktif di Desember, perpanjangan untuk tahun berikutnya sampai 31 Desember tahun berikutnya memakai **harga awal tahun berikutnya** dan ditampilkan sebelum bayar. Jika lisensi sudah habis pada tahun berjalan, tarif perpanjangan mengikuti harga bulan berjalan untuk akses sampai 31 Desember tahun itu.
- Admin dapat melihat simulasi harga 12 bulan; perubahan aturan/discount hanya memengaruhi pesanan baru.

### 5.4 Pesanan & Pembayaran — Telegram Pribadi, Konfirmasi Admin Manual

**Tidak ada bot atau konfirmasi dari Telegram.** Client menghubungi **akun pribadi Telegram admin**, mengirim bukti transfer sendiri, dan admin memproses status di website. Pengiriman chat ≠ pembayaran lunas.

**Alur client (mudah, tidak ada form panjang):**

1. Klik **Sewa**. Bila belum login, lakukan registrasi singkat lalu kembali ke checkout.
2. Isi **nomor akun MT5** satu kali, lihat nominal pasti, masa aktif sampai 31 Desember dan metode bayar, kemudian klik **Buat Pesanan**.
3. Di `/pesanan/{kode}` tampil status **Menunggu Pembayaran**, kode, nominal, rekening/e-wallet tujuan, batas waktu, serta dua tombol **Salin Detail Pesanan** dan **Chat Admin di Telegram**. Client transfer dan **mengirim bukti transfer sendiri** ke akun Telegram pribadi admin. Tidak ada pesan yang dikirim otomatis dari website.
4. Admin **memeriksa mutasi/rekening** (bukan hanya percaya screenshot), membuka `/admin/pesanan`, menemukan kode pesanan, lalu memakai **modal Konfirmasi Pembayaran**. Hanya setelah admin menekan dan server berhasil commit, order menjadi **Lunas** dan key langsung dibuat/perpanjang. Client melihat status terbaru, **Salin Key**, **Unduh & Pasang**, serta panduan di dashboard.

**Isi clipboard "Salin Detail Pesanan" (contoh dinamis):**

> Halo Admin Trader Garuda. Saya ingin mengonfirmasi pembayaran pesanan **{kode}** untuk produk **{produk}**, nominal **Rp{nominal}**, akun MT5 **{akun}**. Saya akan melampirkan bukti transfer di chat ini. Mohon dicek. Terima kasih.

Clipboard hanya membantu client; **klik Chat Admin tidak mengirim isi clipboard atau membuka chat dengan pesan terkirim**. Gunakan link `https://t.me/{username_tanpa_@}` yang benar; admin bebas mengganti username di Pengaturan. Untuk keamanan, jangan membuka chat ke username dummy di website produksi.

**Alur admin:** buka daftar pesanan (terutama filter Menunggu) → cocokkan chat dan rekening → klik Konfirmasi → modal menampilkan kode, produk, client, nominal, akun MT5 dan pernyataan "Saya sudah memeriksa dana masuk" → admin menyetujui → backend membuat/perpanjang lisensi pada transaksi database yang idempoten → tampil hasil berhasil/gagal; audit menyimpan siapa dan kapan. Tidak ada approval melalui Telegram.

| ID | Kebutuhan | Prioritas |
| --- | --- | --- |
| PAY-01 | `telegram_admin_username` (normalisasi dengan menghapus `@` di depan)/URL akun pribadi dan rekening/e-wallet diatur admin; **tanpa token bot, chat ID, secret Telegram atau webhook** | Must |
| PAY-02 | **Salin Detail Pesanan** + **Chat Admin via Telegram** manual pada halaman pesanan, bantuan, dan CTA publik; tautan aktif hanya bila username valid | Must |
| PAY-03 | **Satu-satunya konfirmasi/tolak transaksi dilakukan di `/admin/pesanan`** oleh admin terautentikasi; modal kustom dan pengecekan dana oleh manusia | Must |
| PAY-04 | Status Menunggu Pembayaran, Lunas, Ditolak, Kedaluwarsa; batas bayar dapat ditentukan admin; pesanan kedaluwarsa tidak bisa dikonfirmasi diam-diam tanpa penanganan khusus admin | Must |
| PAY-05 | Konfirmasi dan penerbitan/perpanjangan lisensi memakai transaksi DB atomik, row lock, unique constraint dan idempotency; klik dua kali atau retry tidak menciptakan key ganda | Must |
| PAY-06 | Client melihat invoice/rincian pesanan sederhana beserta harga final, masa akses dan status terkini | Must |
| PAY-07 | Refund **dicatat setelah pengembalian dana dilakukan manual**, dengan nominal, tanggal, metode, bukti/referensi; pencabutan lisensi diputus terpisah | Must |
| PAY-08 | Status pesanan dan lisensi hanya bersumber dari database; **chat Telegram, bukti transfer, dan klik "Chat Admin" tidak mengubah status** | Must |
| PAY-09 | Panel admin menampilkan filter Menunggu, pencarian kode pesanan, kontak client, dan **rekonsiliasi order Lunas yang key-nya belum terbit** untuk pemulihan error | Must |
| PAY-10 | Jika username Telegram pribadi belum diisi, admin mendapatkan peringatan konfigurasi; client melihat kanal alternatif manual yang memang diaktifkan (mis. WhatsApp), bukan tautan palsu | Must |
| PAY-11 | Upload bukti transfer langsung di website, kupon diskon, pembayaran otomatis, pengingat chat otomatis | Could (di luar MVP) |

**Penanganan kegagalan:** bila admin menekan Konfirmasi dan koneksi terputus, UI menampilkan **"Status belum dapat dipastikan — Cek Status"** alih-alih mengulang pembuatan lisensi secara membabi buta. Jika dana masuk setelah pesanan kedaluwarsa, admin menyelesaikannya secara manual (pesanan baru/penyesuaian atau refund) dan mencatat alasan. Tidak ada notifikasi pembayaran otomatis ke admin/client.

**Kontak selain Telegram:** WhatsApp manual boleh ditampilkan jika admin memang mengaktifkannya; tidak ada kewajiban mengirim melalui WA. Fokus utama tetap **chat Telegram pribadi pemilik**.

### 5.5 Lisensi MT5 — Aman, tetapi Ringan untuk Client

**Satu key aktif untuk satu client + satu produk + satu nomor akun MT5**. Client membeli akun MT5 tambahan sebagai transaksi baru dengan key berbeda; jika memperpanjang akun yang sama, gunakan key lama. Pada frontend cukup ditampilkan **Salin Key**, **Unduh & Pasang**, status dan tanggal berakhir.

| ID | Kebutuhan | Prioritas |
| --- | --- | --- |
| LIC-01 | Key kriptografis acak `XXXX-XXXX-XXXX-XXXX`; unik untuk kombinasi client+produk+akun MT5, tidak boleh duplikat aktif | Must |
| LIC-02 | Masa berlaku sampai 31 Desember sesuai `coverage_year`; status kedaluwarsa diproses otomatis | Must |
| LIC-03 | Nomor akun MT5 pada checkout mengikat lisensi; EA mengirim nomor akun dan server aktual. Server disimpan saat aktivasi valid pertama; kemudian perubahan server tak terduga ditolak/ditinjau admin. Akun tidak cocok → `account_mismatch` | Must |
| LIC-04 | EA meminta verifikasi HTTPS saat start dan berkala (default setiap 6 jam); verifikasi login dan status tidak memerlukan langkah manual client | Must |
| LIC-05 | Respons anti-replay dengan server timestamp, nonce request yang dikembalikan, status, cakupan data yang ditandatangani, identitas kunci publik (`kid`) dan pemeriksaan kriptografi yang **sudah lulus PoC MQL5** | Must |
| LIC-06 | Respons boleh memuat `api_base_url` baru, tetapi domain verifikasi lama harus tetap hidup sepanjang masa transisi; perubahan domain tidak boleh memutus pembeli lama | Must |
| LIC-07 | Client dapat klik **Ajukan Ganti Akun**, isi nomor baru, satu kali per masa lisensi secara default, dengan persetujuan admin; key tetap; riwayat dan akun lama dinonaktifkan saat disetujui | Must |
| LIC-08 | Admin dapat suspend, cabut, perpanjang, reset, terbitkan lisensi manual dan meninjau riwayat dengan audit | Must |
| LIC-09 | Log validasi ringkas, rate limit IP/token, deteksi key salah berulang tanpa memblokir pengguna sah secara serampangan | Must |
| LIC-10 | Gangguan jaringan: hanya lisensi yang **pernah tervalidasi** boleh memakai cache status valid sampai maksimal 48 jam dan tidak melampaui tanggal berakhir; validasi online yang menolak key segera berlaku. Pencabutan saat offline baru dapat ditegakkan ketika koneksi pulih atau cache habis | Must |
| LIC-11 | Tampilkan notifikasi versi baru dan unduh otomatis dari dashboard; `min_supported_version`/wajib update ditentukan admin | Should |
| LIC-12 | File `.ex5` privat disertai checksum versi, diunduh dengan URL sementara dan otorisasi pemilik | Must |

**MQL5 dan indikator:** EA dapat memakai `WebRequest()` sesuai izin terminal MT5, tetapi indikator MQL5 **tidak bisa memanggilnya langsung**. Verifikasi indikator tidak boleh mengklaim bekerja sampai ada **PoC** (misalnya EA pendamping yang diuji), termasuk skenario indikator terpasang sendirian, terminal restart, offline, dan backtest. Produk indikator yang belum lulus boleh ditandai **Segera Hadir** atau dipublikasikan tanpa tombol Sewa, bukan menjanjikan aktivasi yang belum ada.

**Keamanan teknis:** jangan menyimpan kunci privat penandatanganan dalam `.ex5`; request menggunakan HTTPS, timestamp, nonce dan pengendalian server, respons ditandatangani dengan skema yang benar-benar dapat diverifikasi di MQL5. `WebRequest()` MT5 tidak tersedia pada Strategy Tester; dokumentasi backtest jangan bergantung pada API live. Produk EA harus diuji end-to-end sebelum rilis.

### 5.6 Area Client — Sesederhana Mungkin

**Kelompok menu maksimal empat, di samping tautan Ringkasan:** **Produk Saya**, **Pesanan**, **Rebate**, **Bantuan**. Menu **Rebate** ditampilkan pada client yang memiliki atau sedang mendaftarkan akun IB; di dalamnya ada **Hasil Rebate**, **Broker IB Saya**, dan **Pencairan Rebate**. Untuk yang belum ikut IB, tampilkan CTA ringkas "Ikut Rebate" pada halaman `/broker`, bukan halaman saldo kosong. Profil berada di menu akun. Dashboard menampilkan "Lanjutkan yang tertunda", kartu lisensi dan **ringkasan saldo + broker jika ikut IB**, tanpa informasi API/nonce/IP. Pada desktop, sub-menu rebate boleh terlihat sebagai tiga tautan sebagaimana contoh V2.2; pada mobile dikelompokkan agar ringkas.

| ID | Kebutuhan | Prioritas |
| --- | --- | --- |
| MEM-01 | Dashboard: produk aktif, status pembayaran, pengingat lisensi H-30/H-7/H-1, pengumuman aktif dan ringkasan rebate **jika ikut IB** | Must |
| MEM-02 | Produk Saya: satu kartu per lisensi dengan status, tanggal berakhir, **Salin Key**, **Unduh & Pasang**, dan **Perpanjang** bila tersedia | Must |
| MEM-03 | Unduhan versi terbaru dan panduan yang relevan dari kartu lisensi. Riwayat versi hanya tampil jika membuka detail | Must |
| MEM-04 | Detail lisensi: nomor akun terikat, tombol **Ajukan Ganti Akun**, status permohonan, dan tautan bantuan | Must |
| MEM-05 | Riwayat pesanan dalam bahasa sederhana: Menunggu, Lunas, Ditolak, Kedaluwarsa | Must |
| MEM-06 | Panduan MT5 bergambar/video, otomatis mengarah ke produk yang baru dibeli dan kesalahan umum yang relevan | Must |
| MEM-07 | Tombol Hubungi Admin via kanal aktif dan pilihan Salin Pesan bila chat tidak bisa dipraisi | Must |
| MEM-08 | Profil: ubah kontak dan password, tanpa formulir tambahan yang tidak relevan | Must |
| MEM-09 | Di **detail lisensi yang sama**, tampil "Terakhir terhubung", status terakhir, dan riwayat 50 verifikasi bila client menekan "Lihat Riwayat"; IP/nonce/signature tidak ditampilkan | Must |
| MEM-10 | Pengingat masa aktif hanya **banner kecil** di dashboard/lisensi: H-30, H-7, H-1 dan setelah berakhir; tombol Perpanjang dan Hubungi Admin, tanpa email/WA otomatis | Must |
| MEM-11 | Client IB memiliki **tiga subhalaman yang jelas**: Hasil Rebate (saldo, riwayat per broker/periode), Broker IB Saya (akun & status), dan Pencairan Rebate (form + riwayat). Ringkasan saldo ditampilkan pula di dashboard | Must |
| MEM-12 | Setiap action penting memiliki loading pada tombol, validasi field dan modal konfirmasi saat berisiko; setelah berhasil state server langsung ter-refresh tanpa reload penuh | Must |

**Tidak dibuat** halaman baru khusus performa, verifikasi lisensi, anomali, pengumuman atau pengingat untuk client. Semua ada sebagai section di halaman yang sudah dimiliki.

### 5.7 Broker, Rebate & Penarikan (Opsional, Manual)

Rebate bukan syarat membeli EA. Client yang berminat cukup klik **Ikut Rebate**, pilih broker, masukkan nomor akun trading, lalu menunggu verifikasi admin. **Broker/IB bukan otomatis sama dengan akun lisensi produk.** Tidak ada API/CSV broker pada MVP.

| ID | Kebutuhan | Prioritas |
| --- | --- | --- |
| IB-01 | Admin kelola broker: logo, link IB, tarif rebate per lot, periode berlaku dan syarat/promo | Must |
| IB-02 | Halaman broker + kalkulator menandai hasil sebagai **estimasi**, bukan saldo otomatis | Must |
| IB-03 | Client menautkan broker dan nomor akun dengan satu form ringkas; admin memverifikasi kepemilikan dan kelayakan referral lewat bukti konfirmasi broker yang wajar; status Menunggu/Terverifikasi/Ditolak + alasan | Must |
| IB-04 | Admin input rebate per periode dan akun, memakai referensi sumber unik jika tersedia, cek duplikat, koreksi dengan entri pembalik tanpa menghapus histori | Must |
| IB-05 | Input banyak baris sekaligus | Should |
| IB-06 | Client melihat **Saldo Rebate, Total Diterima, Sudah Ditarik** dan riwayat; grafik bulanan opsional agar halaman tetap ringan | Must |
| IB-07 | Form penarikan: nominal USD (minimum terlihat), tujuan bank/e-wallet, nama penerima; nominal tidak boleh lebih besar dari saldo tersedia | Must |
| IB-08 | Status Diajukan, Diproses, Dibayar, Ditolak, Dibatalkan; saldo **direservasi** ketika diajukan dan dilepaskan jika ditolak/dibatalkan | Must |
| IB-09 | Kurs dan nilai IDR dibekukan saat pengajuan dan ditampilkan sebelum client menekan **Ajukan Penarikan** | Must |
| IB-10 | Admin menandai dibayar dengan tanggal, bukti atau referensi transaksi, alasan jika ditolak; status transisi diaudit | Must |
| IB-11 | Penarikan dan reservasi saldo memakai transaksi DB + row lock/idempotency, agar klik ganda dan dua permintaan bersamaan tidak menggandakan dana | Must |
| IB-12 | Ekspor CSV dan tarif kompleks per simbol/jenis akun | Could |

**Saldo tersedia = kredit rebate terverifikasi + koreksi − pembayaran selesai − dana yang masih direservasi.** Implementasi internal memakai ledger append-only atau entri yang tidak bisa dihapus, termasuk koreksi; client hanya melihat ringkasan mudah dipahami. Saat sumber data broker berubah, admin mencatat revisi tarif/entri dengan tanggal efektif. Jangan meminta client mengisi sendiri data lot atau kalkulasi rebate yang semestinya dilakukan admin.

### 5.8 Panel Admin — Satu Tempat untuk Operasional

| ID | Kebutuhan | Prioritas |
| --- | --- | --- |
| ADM-01 | Dashboard ringkasan: pesanan menunggu, omzet, lisensi aktif/jatuh tempo, penarikan, rebate | Must |
| ADM-02 | **Satu form produk bertab:** informasi, preview + cara pakai, performa, file/versi, FAQ, harga, SEO | Must |
| ADM-03 | Upload `.ex5` per versi, changelog, checksum dan kontrol versi aktif | Must |
| ADM-04 | **Konfirmasi/tolak pesanan hanya di panel admin** setelah cek rekening dan chat Telegram pribadi; kontak client dan refund manual dipisah dari pencabutan lisensi | Must |
| ADM-05 | Lisensi: key, akun, log, ganti akun (setujui/tolak), suspend/cabut, perpanjang | Must |
| ADM-06 | Client: cari, nonaktifkan dan reset password dengan verifikasi kontak serta audit | Must |
| ADM-07 | Broker, bukti dan verifikasi akun IB | Must |
| ADM-08 | Input/koreksi rebate berbasis catatan sumber, validasi duplikat | Must |
| ADM-09 | Proses penarikan dan saldo reservasi tanpa pembayaran ganda | Must |
| ADM-10 | Pengaturan produk, harga, Telegram/WA, kurs, SEO dan ambang anomali | Must |
| ADM-11 | Audit tindakan penting (refund, akun, lisensi, rebate, reset password, konfirmasi pembayaran) | Must |
| ADM-12 | Daftar perpanjangan H-30/H-7/H-1 dengan tombol chat manual dan teks siap salin | Should |
| ADM-13 | Artikel edukasi dan upload gambar | Must |
| ADM-14 | **Tugas Hari Ini:** pesanan menunggu **pemeriksaan transfer manual**, penarikan, akun IB menunggu verifikasi, lisensi hampir habis, anomali baru, serta **pesanan Lunas yang key-nya belum terbit**; setiap kartu menuju daftar terfilter | Must |
| ADM-15 | FAQ dan panduan bergambar, urutan dan visibilitas | Must |
| ADM-16 | Testimoni manual dengan informasi izin sumber, foto opsional, kaitan produk dan publish/hide | Must |
| ADM-17 | Monitor anomali lisensi, tandai ditinjau/abaikan/suspend dengan alasan | Must |
| ADM-18 | **Pengumuman**: judul, isi singkat, jadwal mulai/selesai, aktif/nonaktif dan target semua client/produk terkait | Must |
| ADM-19 | Kesehatan **aplikasi**: error API lisensi, kegagalan terbit key dan hasil job aplikasi; status backup fisik adalah tanggung jawab infra dan tidak wajib ditampilkan | Should |
| ADM-20 | Seluruh tindakan konfirmasi pembayaran, verifikasi IB, input rebate, proses pencairan, ganti status lisensi, refund dan anomali memakai modal konfirmasi ringkas (bukan `confirm` bawaan browser) dengan detail transaksi, loading, dan feedback hasil server | Must |
| ADM-21 | Data yang berubah di admin segera tercermin pada dashboard dan riwayat client yang terkait; badge Tugas Hari Ini, hitungan dan saldo direkalkulasi setelah commit server, tanpa data palsu/dobel | Must |
| ADM-22 | Menu desktop admin mengacu pada **13 kategori V2.2**; fitur konten (edukasi, FAQ, panduan, testimoni, pengumuman) boleh digabung pada satu grup menu "Konten & Panduan" namun tetap menggunakan route yang ada | Must |

**Dashboard admin tidak perlu menjadi aplikasi analitik rumit.** Susun berdasarkan tindakan penting hari ini, kemudian daftar transaksi. Semua tombol sensitif dilindungi otorisasi dan konfirmasi singkat; client tidak melihat mekanisme ini.

### 5.9 Konfigurasi Admin

| Setelan | Default / catatan |
| --- | --- |
| Harga awal per produk | misal Rp250.000, per tahun kalender |
| Penurunan harga per bulan | misal 5%, **linear** (satu mode saja pada MVP) |
| Harga minimum dan pembulatan | Admin atur, tampil pratinjau harga 12 bulan |
| Batas waktu pembayaran | Misal 24 jam, configurable |
| Telegram pribadi admin | Username `@username` atau tautan `https://t.me/username`; wajib mengarah ke akun pribadi yang benar, divalidasi, **tanpa bot token/webhook** |
| Kontak publik | CTA utama **Chat Admin via Telegram pribadi**; WhatsApp manual opsional; tidak otomatis mengirim pesan |
| Rekening/e-wallet transfer | Nama penerima, nomor tujuan, instruksi singkat |
| Masa lisensi | Sampai 31 Desember, zona waktu `Asia/Jakarta` |
| Perpanjangan | Buka H-30; harga awal tahun berikutnya untuk renewal aktif; harga berjalan untuk yang expired |
| Perubahan akun MT5 | 1 kali per periode lisensi, perlu persetujuan admin |
| Grace period offline | Maksimal 48 jam sejak verifikasi sukses terakhir, tidak lewat tanggal expired |
| Kurs USD/IDR dan minimum penarikan | Diisi admin; direkam saat withdraw dibuat |
| Rebate broker | Tarif per lot dan tanggal berlaku, bisa disesuaikan admin |
| CTA Telegram | Seluruh CTA mengarah ke **akun pribadi admin yang sama**; tidak ada tombol komunitas/grup dalam MVP |
| SEO global | Judul/deskripsi, OG image, Search Console, Analytics opsional |
| Ambang flag anomali | Threshold/cooldown; suspend otomatis **mati** |
| Retensi log & backup | Kebijakan umur penyimpanan dan pemeriksaan pemulihan |
| Pesan pengingat manual | Template untuk tombol salin/chat admin, bukan notifikasi otomatis |

Konfigurasi lanjutan disimpan dalam bagian **Pengaturan Lanjutan** admin; jangan tampil di antarmuka client.

### 5.10 Halaman Edukasi

Halaman Edukasi berisi artikel tentang signal dan cara trading. Admin menulis artikel dan mengunggah gambar (chart, screenshot, ilustrasi) langsung dari editor.

| ID | Kebutuhan | Prioritas |
| --- | --- | --- |
| EDU-01 | Daftar artikel dengan kategori (Signal, Cara Trading) dan pencarian | Must |
| EDU-02 | Detail artikel: judul, gambar sampul, isi teks dan gambar, tanggal terbit | Must |
| EDU-03 | Admin CRUD artikel dengan editor teks kaya (heading, daftar, tautan, gambar) | Must |
| EDU-04 | Upload gambar dari editor dan sebagai gambar sampul; format JPG/PNG/WebP, maksimal 2 MB (setelan), otomatis diubah ukuran dan dikompres, nama file diacak | Must |
| EDU-05 | Status artikel Draf atau Terbit, dengan jadwal terbit opsional | Should |
| EDU-06 | Visibilitas artikel: Publik atau Client login (termasuk client IB-only); konten khusus produk berlisensi hanya bila benar-benar dibutuhkan di fase lanjutan | Should |
| EDU-07 | Artikel Signal dengan field terstruktur opsional: pair, arah (buy/sell), entry, SL, TP, status (Aktif/Selesai) | Could |
| EDU-08 | SEO: slug, meta deskripsi, gambar sampul untuk berbagi link | Should |
| EDU-09 | Disclaimer risiko otomatis di setiap artikel, terutama kategori Signal | Must |

**Keamanan upload:** validasi tipe file (MIME dan ekstensi), batas ukuran, sanitasi HTML dari editor, dan penyimpanan gambar terpisah dari file `.ex5`. Hanya admin yang dapat mengunggah gambar.

### 5.11 Tema & Responsivitas

**Referensi desain final:** Prototype V2.2 untuk tata letak dan V2.3 untuk interaksi modal/loading, gaya **Editorial Trading Lab**, bukan template SaaS biru/putih. Tetap *light-first*: warna gelap dipakai pada panel, header atau bagian hero sebagai identitas, **bukan** dark mode perangkat. Layar admin dan client harus mengikuti bahasa visual yang sama, namun kepadatan data boleh berbeda.

| ID | Kebutuhan | Prioritas |
| --- | --- | --- |
| DES-01 | **Identitas warna:** Forest `#102521` (utama), Warm Bone `#F6F3E9` (latar), Acid Lime `#DFFF67` (CTA utama), Coral `#FA7759` (aksen; **bukan** satu-satunya kode error), dengan Ink `#132B28`, White `#FFFDF7`, Muted `#63746D` | Must |
| DES-02 | Tampilan terang konsisten; jangan mengikuti tema gelap perangkat; `color-scheme: light`, tanpa toggle dark mode. Kontras tulisan/elemen terjaga sesuai WCAG; hindari lime terang untuk teks kecil di atas putih | Must |
| DES-03 | Visual khas: tipografi heading besar dan tegas, label mono kecil seperlunya, garis/border tipis, komposisi editorial asimetris di landing, kartu terbatas, dan kurva/chart hanya bila data valid | Must |
| DES-04 | Mobile-first pada 360/390/768/1280 px; hindari horizontal overflow, target sentuh setidaknya 44×44 px, jarak aman untuk tombol melayang Telegram | Must |
| DES-05 | Frontend publik: navbar sederhana, hero khas, kartu produk, promo broker, edukasi, footer legal. Detail produk **satu halaman** dengan anchor Preview / Cara Pakai / Track Record / FAQ / Ulasan; CTA Sewa selalu jelas | Must |
| DES-06 | Client: navigasi ringkas. Untuk akun IB, tiga tautan Rebate boleh sebagai sub-menu. Dashboard **saldo tersedia, status lisensi dan pekerjaan tertunda** terlihat di atas; jangan mengubahnya menjadi terminal trading rumit | Must |
| DES-07 | Admin: sidebar kelompok **Urusan Hari Ini** dan **Kelola Bisnis** dengan 13 menu V2.2; desktop memakai tabel dan tombol baris jelas, mobile memakai kartu/status/tindakan yang tidak terpotong | Must |
| DES-08 | Gambar responsif dan lazy-loading; screenshot produk dan panduan boleh diperbesar dengan modal lightbox yang dapat ditutup keyboard | Should |
| DES-09 | Loading navigasi `wire:navigate` tampak bila lambat, skeleton stabil, tanpa layar putih/hilang. Status action menggunakan komponen standar (Bagian 5.17) | Must |
| DES-10 | Semua modal, field, badge, loading, toast dan empty state mengikuti komponen desain tunggal; tidak membuat ulang gaya berdasarkan halaman | Must |

### 5.12 SEO

Semua halaman publik wajib memiliki komponen SEO berikut. Halaman area client, admin, login, register, dan pesanan tidak diindeks.

| ID | Komponen | Kebutuhan | Prioritas |
| --- | --- | --- | --- |
| SEO-01 | Title dan meta description | Unik per halaman (beranda, produk, broker, artikel, halaman statis) dengan template default; dapat diubah admin | Must |
| SEO-02 | Canonical URL | Satu canonical per halaman, memakai domain dari `.env` (`APP_URL`) | Must |
| SEO-03 | Open Graph dan Twitter Card | `og:title`, `og:description`, `og:image` (1200×630), `og:url`, `og:type`, `og:locale` (`id_ID`), `twitter:card`; gambar sampul artikel dipakai otomatis | Must |
| SEO-04 | Structured data JSON-LD | `Organization`, `WebSite`, `BreadcrumbList`, `Product/Offer` jika data cocok, `Article` untuk edukasi; `FAQPage` opsional (tidak dijanjikan rich result) | Must |
| SEO-05 | Sitemap XML | Dibuat otomatis (halaman statis, produk, broker, artikel) dengan `lastmod`, diperbarui saat konten berubah | Must |
| SEO-06 | robots.txt | Sitemap dan arahan crawler untuk URL teknis; **bukan** kontrol keamanan. Jangan memblokir URL publik yang perlu dibaca meta `noindex` | Must |
| SEO-07 | Noindex & akses privat | Meta/header `noindex` untuk login/register dan halaman privat bila dapat diakses crawler; `/member`, `/admin`, pesanan dan unduhan **wajib autentikasi dan policy**, bukan hanya `robots.txt` | Must |
| SEO-08 | URL ramah SEO | Slug bersih huruf kecil; redirect 301 otomatis saat slug berubah; halaman 404 informatif | Must |
| SEO-09 | Render server | Konten penting ada di HTML awal; `<html lang="id">`; judul dan meta ikut diperbarui saat `wire:navigate` | Must |
| SEO-10 | Struktur konten | Satu `h1` per halaman, hierarki heading rapi, breadcrumb, tautan internal (artikel terkait, produk terkait) | Must |
| SEO-11 | Gambar | Field teks alternatif (alt) wajib saat upload, atribut lebar dan tinggi, lazy load, format WebP bila memungkinkan | Must |
| SEO-12 | Core Web Vitals | LCP < 2,5 dtk, CLS < 0,1, INP < 200 ms; kompresi, cache header, minify aset, preload font | Must |
| SEO-13 | Ikon dan tema | Favicon, apple-touch-icon, `theme-color` terang | Should |
| SEO-14 | Halaman kepercayaan | Tentang, Kontak, Kebijakan Privasi, Syarat Layanan, dan Disclaimer Risiko, ditautkan di footer (penting untuk konten finansial) | Must |
| SEO-15 | Panel SEO di admin | Field SEO per produk, artikel, dan halaman: judul, deskripsi, slug, gambar share, toggle noindex, dengan pratinjau tampilan di Google dan saat dibagikan | Must |
| SEO-16 | Analytics dan Search Console | Meta verifikasi dan ID analytics (mis. GA4) diisi dari konfigurasi admin, aktif hanya bila terisi | Should |
| SEO-17 | Migrasi domain | Redirect 301 untuk halaman GET publik; **endpoint POST API lama tetap disajikan** atau gunakan redirect 307/308 yang teruji dan diizinkan MT5; canonical/sitemap ikut domain baru | Must |

**CTA Chat Admin Telegram:** tombol melayang dan ajakan footer membuka `https://t.me/{username_tanpa_tanda_@}` pada tab baru (`rel="noopener noreferrer nofollow"`), tidak mengirim pesan otomatis dan tidak menutupi konten ponsel.

### 5.13 Halaman Detail Produk (Tetap Satu Halaman)

**Satu produk = `/produk/{slug}`.** Harga, video/screenshot, petunjuk penggunaan, track record, FAQ, changelog dan ulasan berada di sini. Admin mengisi lewat tab pada form produk yang sama.

| Bagian | Tampilan sederhana untuk calon client | Input admin yang cukup |
| --- | --- | --- |
| Header produk | Nama, kegunaan singkat, harga, **"Akses sampai 31 Des [tahun]"**, tombol Sewa | Nama, tipe, gambar, harga awal, status |
| Preview & cara pakai | Screenshot/video, 3–5 langkah inti, indikator/EA terlihat di chart | Gambar/video, teks petunjuk, parameter rekomendasi |
| Performa / track record | Ringkasan dengan label **Backtest / Forward Test**, periode, profit, drawdown, sumber laporan | Versi, pair, timeframe, modal awal, rentang uji, broker, kondisi biaya/spread/komisi, real/demo, jenis uji, file bukti, tanggal pembaruan |
| Perubahan versi | Changelog sederhana | File versi `.ex5`, checksum, perubahan |
| FAQ | Pertanyaan yang paling sering ditanya | Pertanyaan/jawaban, link panduan |
| Ulasan | Testimoni nyata yang disetujui publikasi | Sumber/izin, nama yang boleh ditampilkan, rating opsional |
| Disclaimer | Risiko trading dan keterbatasan hasil historis | Template sistem otomatis |

- **Performa tidak dibuat halaman terpisah**, cukup anchor/tab `#performa` pada detail produk.
- Bila data uji kosong, jangan tampilkan angka/kurva buatan. Wajib bedakan backtest dan forward test, serta jelaskan hasil historis bukan jaminan profit.
- Kolom teknis pengujian yang panjang tetap disimpan di admin; calon client melihat **ringkasan dahulu**, tombol "Detail Pengujian" untuk membaca metode/sumber.
- Setiap perubahan angka performa memiliki audit waktu dan editor; simpan bukti asli. Jangan membiarkan testimoni seolah menjamin profit.
- Gambar punya teks alternatif; section kosong otomatis tersembunyi.
- Tombol **Sewa** hanya aktif ketika file produk siap, harga diatur, dan metode aktivasi untuk tipe produk tersebut telah lulus uji. Produk belum siap tampil sebagai **Segera Hadir** tanpa checkout.

### 5.14 FAQ, Panduan Instalasi Bergambar, dan Testimoni

| Fitur | Kebutuhan |
| --- | --- |
| FAQ | Umum dan khusus produk; muncul di halaman FAQ atau di detail produk, tidak perlu client menghubungi admin untuk masalah dasar |
| Panduan instalasi | Setiap langkah satu teks pendek + screenshot. Setelah beli, tombol **Unduh & Pasang** membawa client ke file, key, panduan terkait. Bedakan panduan EA dan indikator sesuai mekanisme lisensi yang benar-benar berfungsi |
| Pemecahan masalah | Pesan sederhana: "Key salah", "Akun MT5 berbeda", "Server MT5 berbeda", "Tidak terhubung ke internet", "Lisensi habis" dengan satu tindakan yang bisa dilakukan dan tombol Hubungi Admin |
| Testimoni | Input **manual admin saja**, tidak ada form ulasan publik; rekam sumber, izin publikasi, tanggal, nama yang boleh ditampilkan, produk opsional. Tampil beranda/detail produk setelah publish. Jangan membuat testimoni palsu atau klaim profit dijamin |

Markup `FAQPage` bukan ketergantungan fitur, gunakan hanya jika sesuai pedoman terbaru; konten FAQ tetap tampil normal tanpa rich result Google.

### 5.15 Monitoring Anomali Lisensi (Admin Saja)

Scheduler menganalisis log secara berkala (contoh tiap 10 menit). Ambang, jeda pengulangan flag (*cooldown*) dan retensi log diatur admin. **Tidak pernah menampilkan layar peringatan keamanan teknis kepada client**; client tetap melihat status valid/tidak valid secara normal.

| Pola | Deteksi awal | Tindakan default |
| --- | --- | --- |
| Akun MT5 berbeda berulang | `account_mismatch` lebih dari 5 kali/jam | Tandai untuk admin |
| Key tak dikenal beruntun | Banyak request key salah dari sumber sama | Rate limit IP sementara, jangan membekukan key korban |
| Banyak IP / verifikasi rapat | Melebihi ambang untuk akun bersangkutan | Hanya flag; IP VPS/mobile bisa berubah |
| Timestamp/nonce tidak masuk akal | Request replay/mencurigakan | Tolak request tersebut, catat |
| Versi produk tidak dikenal | Versi yang tidak didaftarkan admin | Flag untuk diperiksa |
| Key expired/revoked terus dicoba | Percobaan berulang setelah tidak berlaku | Catat dan tampilkan ringkasan |

- Flag sejenis untuk lisensi sama dideduplikasi agar admin tidak dibanjiri entri.
- Tindakan admin: **Tinjau**, **Abaikan dengan alasan**, **Suspend**; semua tercatat di audit.
- Suspend otomatis **nonaktif** untuk menghindari salah blokir; IP bukan satu-satunya bukti penyalahgunaan.


### 5.16 Blueprint Halaman — Cocok dengan Prototype V2.2 (Kecuali Telegram Manual v1.5)

**Tujuan:** developer tidak hanya mengikuti daftar endpoint, tetapi menampilkan **komponen, informasi, dan tindakan** seperti rencana. Demo V2.2 menjadi patokan gaya/navigasi; V2.3 menunjukkan modal/loading; data ilustrasi/aksi yang memakai JavaScript in-memory wajib diganti dengan state server Laravel.

| Area / halaman | Prioritas informasi yang terlihat pertama | Aksi utama | Aksi sekunder / detail |
| --- | --- | --- | --- |
| Beranda `/` | Hero brand Trader Garuda, produk unggulan, promo broker, langkah membeli | Lihat Produk / Sewa | Lihat Broker, Edukasi, FAQ, **Chat Admin Telegram pribadi** |
| Detail `/produk/{slug}` | Nama EA/indikator, manfaat, gambar/chart produk asli, harga **beserta tanggal berakhir**, CTA | Sewa | Scroll Preview, Cara Pakai, Track Record, FAQ, Ulasan, Changelog |
| Broker `/broker` | Kartu broker mitra, rebate per lot, syarat, kalkulator **estimasi** | Buka Akun IB | Tautkan akun IB setelah login; panduan singkat |
| Dashboard `/member` | Status pesanan yang belum selesai, kartu lisensi + key/download, banner perpanjangan; **saldo rebate apabila ikut IB** | Lanjutkan pembayaran / Unduh / Tarik Rebate sesuai konteks | Pengumuman ringkas dan bantuan |
| Produk Saya | Status per lisensi, masa aktif, Salin Key, Unduh & Pasang | Unduh & Pasang | Riwayat verifikasi 50 data pada detail, ganti akun, perpanjang |
| Hasil Rebate `/member/rebate` | **Saldo tersedia**, total rebate, sudah dibayar, masih diproses; daftar hasil per broker/periode/lot | Ajukan Pencairan | Filter periode/broker dan detail sumber input admin |
| Broker IB Saya `/member/rebate/akun` | Semua broker/akun yang ditautkan, status Terverifikasi/Menunggu/Ditolak dan total rebate per akun | Tambah Akun Broker | Lihat alasan penolakan, hubungi admin |
| Pencairan `/member/penarikan` | Saldo bisa dicairkan, minimum, kurs & nilai IDR yang akan diterima, status permintaan terbaru | Ajukan Pencairan | Histori Diajukan/Diproses/Dibayar/Ditolak/Dibatalkan |
| Admin Ringkasan `/admin` | **Tugas Hari Ini**: pesanan, pencairan, verifikasi IB, lisensi, anomali dan terbit key gagal; ringkasan KPI | Buka Tugas | Filter & status yang relevan |
| Admin Pesanan | Daftar order, kode, nominal, kontak Telegram client, tanggal; **admin cek rekening sendiri** | **Konfirmasi Pembayaran (di website)** / Tolak | Modal validasi dana masuk, audit, refund terpisah; bukan tombol approve Telegram |
| Admin Lisensi | Daftar key, client, akun MT5, status, jatuh tempo | Tinjau / Ubah Status | Log, permohonan ganti akun, renewal |
| Admin Akun IB Client | Broker, nomor akun, client, status verifikasi, bukti referral | Setujui / Tolak | Tulis alasan & catatan audit |
| Admin Input Rebate | Akun terverifikasi, periode, lot, USD, referensi sumber | Simpan Rebate | Riwayat input, koreksi dengan entri pembalik |
| Admin Pencairan | Pengajuan, USD, kurs IDR, rekening tujuan, status, saldo ditahan | Proses / Tandai Dibayar / Tolak | Bukti bayar, referensi, catatan |
| Admin Produk & Performa | **Satu editor produk**: info → preview/cara pakai → track record → file/versi → FAQ → harga/SEO | Simpan Produk | Preview halaman publik, publish/hide |
| Admin Broker & Tarif | Broker mitra, tarif per lot, link IB, syarat | Tambah / Edit Broker | Tanggal efektif tarif |
| Admin Konten & Panduan | Edukasi, FAQ, panduan bergambar, testimoni manual, pengumuman | Buat / Terbitkan | Draft, sembunyikan, atur urutan |
| Admin Anomali | Flag prioritas, bukti ringkas, status peninjauan | Tinjau / Abaikan / Suspend | Catatan wajib dan log |

**Aturan konsistensi data admin–client:** setelah admin commit verifikasi akun IB, status di `Broker IB Saya` berubah. Setelah input rebate sah, `Total Rebate`, `Saldo Tersedia` dan daftar hasil berubah. Setelah perubahan status pencairan, **saldo reservasi dan histori** berubah. Backend database menjadi sumber tunggal; frontend tidak boleh hanya memodifikasi variabel lokal. Saat tab/browser client sudah terbuka, tampilkan data terbaru ketika halaman dibuka lagi atau melalui refresh ringan saat fokus/kembali ke halaman; **real-time websocket tidak wajib MVP**.

### 5.17 Sistem Feedback UI — Loading, Progress, Modal, Alert, Toast

Ini **kebutuhan Must**, berlaku pada publik, client, dan admin. Setiap aksi terlihat merespons; tidak ada klik yang "diam". Gunakan satu rangkaian komponen reusable dalam Livewire/Tailwind (nama komponen bebas): `AppModal`, `ConfirmModal`, `FormModal`, `StatusModal`, `AppToast`, `ButtonLoading`, `PageProgress`, `Skeleton`, `FieldError`, `EmptyState`, dan `StatusBadge`.

#### A. Kapan memakai apa?

| Peristiwa | Komponen UI | Isi minimal | Catatan |
| --- | --- | --- | --- |
| Pindah halaman melalui `wire:navigate` | Progress bar tipis di atas dan/atau skeleton | Indikasi "Memuat halaman…" bila terasa lambat | Tidak mengganti seluruh layar dengan spinner besar |
| Cari/filter daftar atau load kartu | Skeleton kecil di area terdampak | Bentuk mengikuti tabel/kartu, bukan teks berloncatan | Saat kosong tampil empty state, bukan skeleton selamanya |
| Simpan/submit | Spinner di tombol, label berubah **"Menyimpan…"** dan tombol disabled | Status singkat `aria-live` | Jangan menerima klik ganda |
| Upload gambar atau file `.ex5` | Progress file dengan nama, ukuran dan persentase **bila pengiriman byte terukur** | "Mengunggah nama-file.ex5 — 42%" | Saat proses server tak terukur → indikator bergerak + "Memproses file…", **jangan buat % palsu** |
| Konfirmasi tindakan berisiko | **Modal konfirmasi kustom** | Judul, dampak nyata, data relevan, Batal dan Konfirmasi | Dilarang popup `confirm()` browser |
| Input data pendek yang butuh konteks | **Modal form** atau form inline | Label jelas, validasi bawah field | Contoh Tambah Akun Broker, edit tarif, catatan anomali |
| Kesalahan validasi formulir | Inline error tepat di bawah field + ringkasan singkat bila banyak | Teks manusiawi dan cara memperbaiki | Jangan hilangkan nilai yang sudah diisi |
| Keberhasilan ringan | Toast kecil "Berhasil disimpan" dengan ikon dan `role=status` | Auto hilang ±4 dtk, tidak memblokir | Modal sukses hanya untuk konfirmasi penting |
| Hasil penting (bayar, lisensi, penarikan) | **Status modal** setelah server selesai, atau kartu status jelas bila user tetap di halaman | Nomor transaksi, status final, aksi berikutnya | Tidak pernah mengaku "Dibayar" sebelum admin mencatat pembayaran aktual |
| Gagal server / timeout / akses ditolak | Modal alert untuk dampak kritis; inline/alert area untuk error biasa | Apa yang gagal, data tersimpan atau belum, Coba Lagi/Cek Status/Bantuan | Jangan memperlihatkan stack trace |
| Tidak ada data | Empty state bergambar/ikon ringan | Contoh "Belum ada rebate" + CTA terkait bila relevan | Jangan tampilkan angka 0 seolah pernah menerima rebate |

#### B. Empat fase tiap tindakan

1. **Idle:** CTA aktif hanya bila berhak, format angka/tanggal jelas.
2. **Validating:** validasi input lokal (contoh jumlah minimal, saldo tersedia, nomor MT5), pesan di bawah field. Jangan kirim bila invalid.
3. **Submitting:** tombol menjadi `Memproses…` + spinner + disabled, hanya **bagian terkait** yang terkunci, data lain tetap terbaca. Mencegah submit berulang di UI **dan** backend idempotency.
4. **Result:** apabila sukses, modal/ toast sesuai risiko dan semua data yang terdampak diambil lagi dari server; jika gagal, pengguna tetap di halaman dengan nilai input sebelumnya, pesan sebab, dan opsi aman mengulang/cek status.

**Panduan waktu UX (bukan janji kecepatan jaringan):** tampilkan loading bila request >150–200 ms supaya tidak berkedip; jika masih bekerja setelah sekitar 8 dtk, pesan **"Masih diproses. Jangan kirim ulang."**; pada timeout, jangan langsung mengubah status ke "Gagal" bila server mungkin sudah memproses. Tampilkan **"Status belum dapat dipastikan"** dengan tindakan **Cek Status** terlebih dahulu. Angka persentase progress hanya boleh muncul untuk tahapan yang benar-benar dapat dihitung. Hindari animasi berlebihan dan dukung preferensi `prefers-reduced-motion`.

#### C. Desain modal yang wajib konsisten

- Overlay gelap transparan di atas halaman (halaman asal masih terlihat); kartu modal latar Warm Bone/White, border Forest, CTA utama Lime; Coral hanya untuk penekanan bahaya dengan teks.
- Ukuran nyaman desktop ±440–560 px untuk konfirmasi; mobile `width: calc(100% - 32px)`, maksimal tinggi layar, isi bisa scroll tanpa menyembunyikan tombol.
- Judul fokus pada tindakan: **"Konfirmasi Pembayaran?"**, **"Ajukan Pencairan Rebate?"**, bukan "Yakin?".
- Tampilkan **siapa/apa, nominal, rekening/akun, konsekuensi** bila relevan; tombol kiri **Batal**, tombol kanan berlabel kerja spesifik (**Konfirmasi Pembayaran**, **Ajukan Pencairan**, **Suspend Lisensi**). Jangan hanya "OK".
- Tombol kanan tetap menunjukkan spinner/loading ketika request berjalan, tidak dapat ditekan dua kali. Saat sukses tutup modal dan perbarui data; tampilkan toast atau modal status sesuai risiko.
- Modal punya `role="dialog"`, `aria-modal="true"`, judul yang terhubung, trap focus, fokus awal yang benar, Esc/klik luar hanya menutup **modal non-pemrosesan yang aman** dan mengembalikan fokus ke pemicu. Jika permintaan sudah dikirim, menutup UI **tidak membatalkan request**; lebih baik nonaktifkan penutupan sementara sampai status diketahui.
- **Jangan** gunakan popup native `alert()`, `confirm()`, `prompt()` pada aplikasi produksi. Browser *file picker* tetap wajar untuk upload.

#### D. Warna status, label, dan ikon

Gunakan **teks + ikon + warna** (tidak warna saja): sukses **Berhasil/Aktif/Terverifikasi/Dibayar**, pending **Menunggu/Diajukan/Diproses**, warning **Hampir Berakhir/Butuh Tinjauan**, error **Ditolak/Gagal/Suspend/Kedaluwarsa**. Kontras harus terbaca dan jangan memakai lime untuk semua status. Saldo USD harus memakai 2 desimal; IDR pemisah ribuan; tanggal WIB; jangan menampilkan "estimasi rebate" sebagai **saldo diterima**.

### 5.18 Matriks Modal / Konfirmasi Wajib (Aksi Nyata)

| Aksi | Modal sebelum commit | Saat loading | Setelah response server |
| --- | --- | --- | --- |
| Client: checkout | Ringkasan produk, akun MT5, harga dan akhir lisensi (boleh pada halaman checkout, bukan modal ekstra) | Tombol "Membuat Pesanan…" | Tampilkan kode pesanan, rekening, status **Menunggu Pembayaran** dan dua CTA Salin Detail / Chat Admin |
| Client: Salin Detail Pesanan / Chat Admin Telegram | **Tidak perlu modal**; penyalinan dilakukan hanya saat diklik | Toast "Rincian disalin"; link Telegram membuka tab baru | Tidak ada perubahan status pesanan, **tidak ada chat dikirim sistem** |
| Client: salin key | **Tidak perlu modal** | Tidak perlu loading bila copy sinkron | Toast "Key disalin"; jangan membuka key milik orang lain |
| Client: unduh `.ex5` | Tidak perlu konfirmasi ekstra | "Menyiapkan unduhan…" dan status bila gagal | File tersimpan/mulai unduh; jangan klaim sukses bila link gagal |
| Client: tambah akun IB | Modal form: pilih broker dan isi nomor akun; jelaskan menunggu admin | "Mengirim Akun…" | Status **Menunggu Verifikasi** di Broker IB Saya |
| Client: ajukan pencairan | Modal: USD, perkiraan IDR, kurs, metode/rekening, minimum, saldo setelah reservasi | "Mengajukan…" | Status **Diajukan** + kode referensi; saldo tersedia berkurang saat reservasi sah |
| Client: ganti akun lisensi | Modal: akun lama dan baru, sisa batas perubahan, konsekuensi | "Mengirim Permohonan…" | **Menunggu Persetujuan**, tidak langsung mengubah akun aktif |
| Admin: konfirmasi pesanan | Modal: kode, client, produk, akun MT5, nominal dan checkbox wajib **"Saya telah memeriksa dana masuk ke rekening"** | "Memverifikasi & Mengaktifkan…" | Server commit **Lunas** lalu key dibuat sekali; client melihat aktif; error ditangani lewat Cek Status/rekonsiliasi, **bukan Telegram** |
| Admin: tolak pesanan | Modal singkat dengan alasan opsional/wajib sesuai SOP | "Menolak…" | Status Ditolak; tidak menerbitkan lisensi |
| Admin: verifikasi akun IB | Modal: client, broker, nomor akun, bukti validasi/referral | "Menyimpan Verifikasi…" | Status Terverifikasi/Ditolak + audit; alasan bila tolak |
| Admin: input rebate | Form halaman utama; modal **konfirmasi angka** bila diperlukan sebelum final: akun, periode, lot, USD dan referensi | "Menyimpan Rebate…" | Ledger bertambah sekali; saldo & riwayat client disegarkan |
| Admin: pencairan → Diproses | Modal: client, nominal dan rekening tujuan | "Memproses…" | Status Diproses; saldo tetap ditahan |
| Admin: pencairan → Dibayar | Modal: nominal, penerima, rekening, **bukti/ref transaksi** wajib | "Mencatat Pembayaran…" | Status Dibayar dan histori bertambah; **tidak memicu transfer otomatis** |
| Admin: tolak pencairan | Modal dengan alasan wajib, catatan reservasi saldo akan dibuka | "Menolak…" | Status Ditolak dan saldo reservasi dilepas sekali |
| Admin: suspend/cabut lisensi | Modal peringatan tegas: client, key ter-mask, akun dan dampak | "Memperbarui Status…" | Status baru + log audit; beri informasi bantuan sesuai kebijakan |
| Admin: refund | Modal: kode pesanan, nominal **sudah benar-benar dikembalikan**, metode, bukti/refund dan alasan | "Mencatat Refund…" | Catatan refund tersimpan; pencabutan lisensi diproses terpisah sesuai pilihan |
| Admin: anomali lisensi | Modal form dengan catatan wajib untuk Abaikan/Tinjau/Suspend | "Menyimpan Keputusan…" | Flag dan catatan berubah; tidak menghapus bukti |
| Admin: hapus/sembunyikan/publish konten | Konfirmasi untuk hapus permanen atau publikasi berisiko; toggle visibilitas biasa bisa langsung | "Menyimpan…" | Status + toast; daftar tetap sinkron |

**Aturan tegas:** modal adalah lapisan UX, **bukan** pengganti otorisasi, validasi saldo, transaksi atomik atau verifikasi dana pada backend. Tombol **Dibayar** hanya mencatat pembayaran manual yang sudah dilakukan, bukan transfer dana otomatis.

### 5.19 Komponen Status, Empty/Error State, dan Ketahanan Interaksi

| Komponen | Perilaku wajib | Contoh kata-kata ke user |
| --- | --- | --- |
| `StatusBadge` | Set data status tunggal dipakai di client dan admin, mapping bahasa Indonesia | "Menunggu Verifikasi", "Sudah Dibayar", "Berakhir 7 Hari Lagi" |
| `EmptyState` produk | Tampilkan manfaat & satu CTA | "Belum ada produk. Jelajahi EA tersedia." |
| `EmptyState` rebate | Untuk akun IB yang belum ada entri, bukan tampil data palsu | "Belum ada hasil rebate pada periode ini." |
| `EmptyState` broker | Bedakan belum ikut vs sedang diverifikasi | "Belum ada akun broker tertaut. Tambah akun IB." |
| `EmptyState` withdraw | Jangan menyalahartikan nol sebagai gagal | "Belum pernah mengajukan pencairan." |
| API error saat admin commit | Jangan mengubah status secara optimistis sebelum konfirmasi server | "Belum berhasil menyimpan. Periksa koneksi dan coba cek status." |
| File upload error | Pertahankan data form dan info file terkait, tampil sebab validasi | "File harus .ex5 dan ukuran sesuai batas." |
| Sesi habis | Simpan draft nonrahasia lokal/sementara bila aman, tampil modal login ulang | "Sesi Anda berakhir. Login kembali untuk melanjutkan." |
| Akses dilarang/URL salah | Halaman 403/404 ramah, tanpa detail internal | "Halaman ini tidak tersedia untuk akun Anda." |
| Rate limit | Beri jeda yang manusiawi | "Terlalu banyak percobaan. Coba lagi beberapa saat." |
| Data baru tersedia | Setelah commit daftar/kartu diperbarui; bila perubahan dilakukan browser lain beri tombol Refresh | "Data terakhir diperbarui ..." |

**Tidak ada optimistic update pada saldo, pencairan, verifikasi IB, lisensi dan konfirmasi pembayaran.** Semua angka dan status ditampilkan setelah database berhasil commit; permintaan dengan `request_id`/idempotency key mencegah pengulangan. Pada perubahan formulir biasa (contoh deskripsi produk), boleh tampil draft sementara, tetapi label **"Tersimpan"** hanya setelah server mengonfirmasi.

### 5.20 Panduan Implementasi Livewire 4 (Interaksi UI)

- Gunakan **route Laravel sebenarnya** (`/member/...`, `/admin/...`), `wire:navigate` untuk pergantian halaman, dan halaman server-rendered untuk SEO. **Jangan** membawa hash route `#/...` dari prototype ke produksi.
- Gunakan status loading `wire:loading`, `wire:target`, `wire:loading.attr="disabled"` pada action spesifik. Jika satu halaman punya beberapa tindakan, loader **hanya** pada tombol/baris yang relevan. Untuk navigasi, gunakan indikator progress + skeleton dari layout.
- Modal dikelola dengan state terstruktur (jenis modal, record ID, ringkasan server, input validasi); data sensitif ditarik ulang sebelum commit. Komponen modal reusable dan keyboard-accessible; jangan hanya meniru popup `confirm()` dari HTML demo.
- Untuk upload Livewire, progress **persentase upload yang terukur** boleh berasal dari event pengiriman file; tahap parsing, kompresi, antivirus atau pemindahan ke storage memakai status indeterminate terpisah. Jangan mengklaim seluruh proses selesai pada 100% upload jika pekerjaan server masih berjalan.
- Semua event keuangan, konfirmasi pesanan, refund, perubahan lisensi, input rebate, pencairan memakai **database transaction + lock/unique constraint + idempotency**, lalu broadcast/poll ringan opsional hanya bila benar-benar dibutuhkan. MVP cukup refresh komponen setelah hasil action server.
- Tidak mengharuskan **real-time websocket, animasi berat, stepper onboarding panjang, notifikasi email/WA**, atau modal berulang untuk aksi remeh. Admin boleh lebih rinci; client harus cepat menuntaskan tugas.


## 6. Kebutuhan Non-Fungsional

| Kategori | Kebutuhan |
| --- | --- |
| Kecepatan dan UX | Core Web Vitals baik, form sederhana, page transition halus, API lisensi responsif; error ramah manusia; **loading/progress/empty/error/modal wajib** sesuai Bagian 5.17–5.20 |
| Keamanan login | HTTPS, hashing password, rate limit, session rotation, policy per pemilik data; **TOTP hanya untuk admin** |
| Keamanan transaksi | **Konfirmasi pembayaran hanya dari admin login dengan otorisasi dan modal cek dana**, disimpan dalam transaksi DB atomik/idempoten serta audit; chat Telegram tidak dapat memicu perubahan pesanan |
| File | `.ex5` disimpan privat, link sementara + pemeriksaan pemilik, checksum versi; unggahan gambar MIME dan batas ukuran |
| Ketersediaan | Scheduler/cron ringan untuk masa berlaku, flag anomali dan pemeliharaan; **tidak wajib queue worker** pada MVP; backup harian dan uji restore berkala; admin punya rekonsiliasi jika ada error aktivasi |
| Privasi | Persetujuan dan informasi penggunaan nomor MT5, kontak dan rekening, tampilkan informasi minimum, retensi log dan mekanisme penghapusan sesuai kewajiban |
| Operasional | PHP **8.3+**, ekstensi Laravel/Livewire yang sesuai, database MySQL 8.4 LTS, SSL, monitoring error API, hasil backup dan cron |
| SEO | Publik server-rendered, sitemap, canonical; halaman privat selalu memakai auth/policy, bukan `robots.txt` sebagai keamanan |
| Perilaku saat error | Server EA offline mengikuti grace period terakhir valid; frontend menampilkan **Hubungi Admin** atau **Coba Lagi**, bukan stack trace |

**Sederhana bukan berarti tanpa audit:** semua teknis berjalan internal tanpa meminta client memilih metode enkripsi atau domain. Telegram hanya chat manual, tidak perlu bot maupun webhook.

## 7. Arsitektur Teknis

### 7.1 Stack

- **Backend:** Laravel 13 (PHP 8.3+), Eloquent, Policies, transaksi dan locking database; Scheduler untuk pekerjaan berkala sederhana.
- **Frontend:** Livewire 4, Tailwind CSS, Alpine.js, `wire:navigate`; server-rendered untuk SEO.
- **DB:** MySQL 8.4 LTS (dipilih pada v1.7); angka uang/lot menggunakan `DECIMAL`, bukan `FLOAT`.
- **Queue/cache:** **tidak ada queue worker wajib pada MVP**; cache file/database memadai. Tambahkan queue hanya jika beban nyata membutuhkannya. Cron tetap diperlukan untuk tugas scheduler sederhana.
- **Storage:** `.ex5` dan bukti pembayaran/penarikan di disk privat; media publik di disk terpisah.
- **Telegram:** **tautan chat pribadi `https://t.me/{username}` saja**, username di Pengaturan; tidak ada Bot API/webhook/secret/pengiriman otomatis. Admin selalu konfirmasi melalui panel website.
- **Operasional:** SSL, backup otomatis harian, pemeriksaan restore berkala, log error, monitoring job dan API.
- **Domain:** `APP_URL` dari `.env`; domain lama melayani API lisensi selama masa transisi.

### 7.2 Modul Utama

1. Katalog, satu halaman detail dan harga kalender.
2. Akun sederhana, checkout, pembayaran admin/Telegram.
3. Lisensi EA MT5 + unduh file + versi + verifikasi API.
4. Area client (Produk Saya, Pesanan, Rebate opsional, Bantuan).
5. Broker IB, input rebate, ledger dan penarikan.
6. Panel admin (Tugas Hari Ini, pengumuman, FAQ/panduan/testimoni, audit/monitor).
7. SEO, keamanan aplikasi, audit, scheduler aplikasi dan health endpoint; backup/restore fisik ditangani pihak infra.

### 7.3 Skema Data (Ringkas; detail di Lampiran v1.7)

| Tabel | Kolom penting / catatan |
| --- | --- |
| `users` | id, name, email unique, password hash, contact_channel, contact_value, role, status, `must_change_password` |
| `products` | id, name, slug unique, type ea/indicator, base_price DECIMAL, min_price, description, status |
| `product_versions` | id, product_id, version, file_path private, checksum, min_supported_version, changelog, is_active |
| `product_media` | product_id, type, path/url, caption, alt_text, sort_order |
| `product_performances` | product_id, version_id, test_type, start_date, end_date, symbol, timeframe, broker, initial_capital, spread/commission, real_demo, profit, drawdown, win_rate, report_path, updated_by, updated_at |
| `orders` | id, unique code, user_id, product_id, mt5_account, price_locked DECIMAL, coverage_year, order_type(new/renew), status, expires_at, confirmed_at, **confirmed_by**, **payment_checked_at**, **payment_note**, payment_reference nullable |
| `licenses` | id, user_id, product_id, **key_hash untuk pencarian + key_ciphertext terenkripsi agar bisa ditampilkan lagi ke pemilik**, mt5_account, mt5_server nullable (diikat saat aktivasi valid), status, coverage_year, expires_at; unique constraints untuk key dan lisensi aktif per kombinasi |
| `license_account_change_requests` | id, license_id, old_account, new_account, status, requested_at, decided_by |
| `license_activations` | id, license_id, account, server, account_type, product_version, last_seen_at, recorded_at |
| `license_logs` | id, license_id nullable, account_number, product_version, event/result, source_ip protected, latency_ms, created_at, retention |
| `license_flags` | license_id, rule, severity, evidence_summary, first/last_seen, status, reviewed_by, reviewed_at, cooldown_key |
| `brokers` | id, name, logo, ib_link, rate_usd_per_lot, valid_from, terms, is_active |
| `trading_accounts` | id, user_id, broker_id, account_number, status, verified_at, evidence_note |
| `rebate_entries` | id, trading_account_id, period, amount_usd DECIMAL signed, lots DECIMAL, source_reference, correction_of_id nullable, entered_by, created_at; tidak dihapus |
| `withdrawals` | id, user_id, amount_usd DECIMAL, fx_rate DECIMAL, amount_idr DECIMAL, destination, status, reserved_at, paid_at, evidence_path, idempotency_key |
| `refunds` | id, order_id, amount_idr, method, status, reason, evidence_path, refunded_at, actor_id |
| `announcements` | id, title, body, audience, starts_at, ends_at, is_active |
| `articles`, `article_images` | artikel, visibilitas publik/client login, upload gambar dan SEO |
| `faqs`, `guides`, `guide_steps` | id, product_id nullable, question/title, text, image_path, step_order, is_active |
| `testimonials` | name/display_name, content, rating, photo_path, product_id nullable, source, consent_record, published_at, is_visible |
| `settings` | harga, kurs, **telegram_admin_username/link pribadi**, kontak manual opsional, keamanan dan SEO; **tanpa token bot/webhook** |
| `activity_logs` | actor_id, action, subject_type/id, before/after ringkas, created_at |
| `outbound_clicks` | target_type, target_id, created_at, metadata anonim minimum |

**Aturan DB:** foreign key dan indeks pada pemilik/produk/status/tanggal; unique index untuk key dan referensi sumber jika ada; semua operasi `confirm_order`, `renew`, `reserve_withdrawal`, `refund`, `change_account` idempoten, di dalam transaksi; tanggal internal UTC, tampil WIB. Catatan saldo harus immutable: koreksi membuat entri baru, bukan mengubah angka masa lalu.

### 7.4 Kontrak API Lisensi (Draf Implementasi)

**Endpoint:** `POST /api/v1/licenses/verify`.

- **Request EA:** `license_key`, `product_id`, `account_number` (dibaca dari MT5), `server` dan `account_type` (jika ada), `version`, `timestamp`, `nonce` unik.
- **Response minimal:** `status`, `expires_at`, `api_base_url`, `latest_version`, `min_supported_version`, `server_timestamp`, `request_nonce` (echo), `kid`, `signature`, `request_id`.
- **Status resmi:** `valid`, `invalid`, `expired`, `revoked`, `suspended`, `account_mismatch`, `server_mismatch`, `update_required`, `rate_limited`. Status `network_unavailable` adalah **state lokal EA**, bukan respons server.
- **Validasi:** key hash/lookup aman, produk, nomor akun dan server yang sudah diikat, status, masa aktif, timestamp toleransi 5 menit dan nonce anti-replay server; rate limit secukupnya, jangan memblokir pemilik key akibat serangan IP lain.
- **Tanda tangan respons:** tanda tangani set data kanonis yang memuat status, akun, produk, masa berlaku, waktu server, `request_nonce`, versi dan URL. `kid` menandai public key; **target algoritma Ed25519 dan envelope `payload_b64` dikunci dalam `technical/API_CONTRACT.md`; rilis tetap menunggu PoC verifier MQL5 PASS**. Kunci privat server tidak pernah didistribusikan.
- **Saat offline:** EA memakai keputusan valid yang tersimpan secara aman maksimal 48 jam dan tidak lewat expiry, dengan retry berkala. Respons penolakan online selalu mengalahkan cache.
- **Deactivate:** `POST /api/v1/licenses/deactivate` **bukan** endpoint publik bebas pakai; hanya proses terautentikasi dan terotorisasi/ber-token khusus dengan anti-replay. Jika tidak diperlukan untuk MVP, nonaktifkan endpoint dan lakukan melalui admin saja.
- **Transisi domain:** tetap terima request di host lama. Redirect 307/308 untuk POST hanya jika diuji pada terminal MT5 serta allowlist. Jangan mematikan domain lama tanpa rencana migrasi.
- **Telegram tidak mempunyai endpoint API.** `Chat Admin` hanya tautan eksternal ke akun pribadi. Action `confirmOrder`/`rejectOrder` adalah Livewire action **di balik autentikasi admin**, bukan callback dari Telegram.

**Batas implementasi:** jangan memperlakukan indikator sama seperti EA sebelum PoC selesai; indikator tidak dapat memanggil `WebRequest()` langsung dan `WebRequest()` juga tidak berjalan dalam Strategy Tester. Dokumentasi pemasangan perlu mengikuti mekanisme yang sudah benar-benar diuji.

## 8. Alur Pengguna Utama

### 8.1 Client Membeli EA

`Lihat produk → Sewa → (login/register sekali) → isi akun MT5 → lihat harga + tanggal berakhir → bayar transfer → kabari admin → admin konfirmasi → Salin Key + Unduh & Pasang.`

Client tidak diminta memilih algoritma proteksi lisensi, memasang bot Telegram, mengisi identitas KTP, atau mendaftar broker IB. Jika ia belum memiliki akun MT5, panduan menjelaskan cara menemukan nomor akun; bantuan manual selalu tersedia.

### 8.2 Client Ikut IB / Rebate (Jika Berminat)

`Pilih broker → Buka akun melalui link IB → masukkan nomor akun → admin verifikasi → lihat saldo → Ajukan Penarikan → admin proses.`

Jika client hanya ingin produk EA, bagian IB bisa diabaikan. Jika client IB-only, akun tetap dapat melihat artikel khusus client login tanpa harus membeli EA.

### 8.3 Lisensi Berakhir / Perpanjangan

H-30 tampil banner **Perpanjang**; sebelum bayar muncul jelas periode yang diperpanjang dan harga. Key tidak berubah, status diperbarui setelah admin konfirmasi. Tidak ada email/WA otomatis.

### 8.4 Kesalahan dan Dukungan

Client yang salah akun atau tidak terhubung hanya melihat pesan singkat serta **Lihat Panduan** atau **Hubungi Admin**. Admin memperoleh kode error/riwayat teknis pada panelnya. Indikator berlisensi baru dapat ditawarkan setelah mekanisme pemasangannya dibuktikan.

## 9. Aspek Hukum & Risiko

1. **Broker/IB:** validasi izin dan kebolehan program promosi/rebate sesuai instrumen dan yurisdiksi broker sebelum ditampilkan. Jangan mengasumsikan seluruh produk forex/CFD berada di bawah regulator yang sama; lakukan pemeriksaan hukum terbaru sebelum go-live.
2. **Trading performance:** data backtest/forward test harus autentik dan menyebut periode, risiko, modal/kondisi tes; tidak ada jaminan profit atau menyesatkan lewat screenshot selektif.
3. **Testimoni:** hanya pengalaman nyata dengan izin publikasi, bukan testimoni fiktif. Sembunyikan informasi akun yang tidak boleh dipublikasikan.
4. **Privasi:** simpan nomor akun MT5, kontak dan data penarikan hanya untuk kebutuhan layanan dan dengan pemberitahuan/persetujuan yang sesuai. Batasi akses dan retensi.
5. **Branding:** Trader Garuda bukan lembaga pemerintah, tidak menggunakan lambang Garuda Pancasila; periksa ketersediaan merek di DJKI.
6. **Hak layanan:** syarat pemakaian, masa aktif kalender, refund, biaya/cakupan rebate dan metode penyelesaian sengketa harus mudah diakses sebelum checkout.

| Risiko | Mitigasi sederhana |
| --- | --- |
| Client bingung masa berlaku Desember | Tanggal berakhir dan periode tertulis besar sebelum bayar |
| Lisensi bocor | Bind akun MT5, verifikasi online, audit anomali, suspend manual |
| Indikator gagal aktivasi | Jangan dijual dengan lisensi API sebelum PoC MQL5 lulus |
| Kesalahan saldo rebate / withdraw ganda | Ledger koreksi, reservasi saldo DB, idempotency, audit |
| Konfirmasi palsu lewat chat / bukti transfer editan | **Abaikan chat sebagai otorisasi**, cocokkan mutasi dana dengan pesanan; hanya admin login dapat mengonfirmasi di website |
| Lupa password tanpa email | Verifikasi melalui kontak terdaftar, reset sekali pakai, cabut sesi lama |
| Admin tidak segera membuka chat/panel | Dashboard **Tugas Hari Ini** menampilkan pesanan menunggu; client dapat menghubungi admin lagi, dan status selalu dapat dicek di website |
| Domain migrasi | Host lama tetap melayani API dan uji terminal MT5 |
| Admin tunggal | Dashboard Tugas Hari Ini, alert error, backup dan restorasi teruji |

## 10. Rencana Rilis (Roadmap)

**Tujuan:** tidak membuat seluruh modul kompleks sekaligus. Estimasi bergantung hosting, ketersediaan file MQL5 dan hasil pengujian.

| Tahap | Fokus | Catatan |
| --- | --- | --- |
| 1 — Transaksi produk | Katalog, detail produk, harga kalender, checkout, **Chat Telegram pribadi + konfirmasi manual di panel admin**, unduhan `.ex5`, API lisensi EA; modal/loading/toast | Harus lulus PoC API/EA, bukti transfer dicek manual dan alur client end-to-end |
| 2 — Kepercayaan & self-service | FAQ/panduan bergambar, review manual, riwayat verifikasi, banner masa berlaku, pengumuman, dashboard Tugas Hari Ini, monitoring anomali | Tidak menambah menu client baru |
| 3 — IB & finansial | Promo broker, kalkulator, tautkan akun, input rebate, ledger, penarikan, refund dan rekonsiliasi | Client IB opt-in, tidak menghalangi penyewa EA |
| 4 — Lanjutan | Indikator berlisensi **setelah PoC lolos**, domain `.com`, ekspor/analitik, kupon | Tidak menjadi ketergantungan peluncuran EA |

**Gerbang rilis:** pemrosesan uang dan lisensi aman, backup bisa dipulihkan, data performa nyata, indikator tidak diberi label siap dijual bila aktivasi belum lolos.

## 11. Kriteria Penerimaan (MVP)

**Pengalaman client**

1. User baru menyelesaikan sewa tanpa diminta mengisi data duplikat; cukup registrasi sekali, akun MT5, transfer dan kirim konfirmasi ke admin.
2. Beranda dan detail produk menampilkan harga final serta tanggal **"Akses sampai 31 Desember YYYY"**; tidak ada janji 12 bulan yang menyesatkan.
3. Setelah admin konfirmasi, di **satu kartu** client menemukan Salin Key, Unduh & Pasang, panduan instalasi dan bantuan.
4. Client dapat menggunakan EA dan melihat status Aktif tanpa membuka log/API; riwayat 50 verifikasi ada di detail saat diminta.
5. Banner lisensi H-30/H-7/H-1/expired dan Perpanjang berfungsi tanpa email/WA otomatis.
6. Client biasa dapat membeli EA tanpa daftar broker. Client IB dapat melihat rebate tanpa lisensi produk.
7. Halaman publik produk tunggal sudah memuat preview, cara pakai, performa nyata berlabel dan FAQ/testimoni; tidak ada URL performa terpisah.

**Uang, lisensi, admin**

8. **Tidak ada bot atau webhook Telegram**. Chat client maupun bukti transfer tidak mengaktifkan pesanan. Admin login memeriksa rekening lalu menekan Konfirmasi di panel; klik ganda/retry tidak membuat lisensi atau kredit ganda.
9. Satu key terikat ke **akun MT5 yang benar**; key berbeda untuk akun tambahan; perpanjangan akun yang sama mempertahankan key.
10. H-30 renewal aktif menghasilkan tanggal 31 Desember tahun berikutnya dengan harga renewal yang jelas; renewal expired memakai harga bulan berjalan.
11. Refund mempunyai catatan pengembalian dana yang berbeda dari status revoke lisensi.
12. Dua request withdraw serentak tidak membuat saldo negatif; penolakan/koreksi melepaskan reservasi dengan benar; data rebate tidak dihapus.
13. Reset password manual melalui kontak terdaftar diuji dan sesi lama dicabut; 2FA tidak diwajibkan bagi client, admin terlindungi.
14. Dashboard admin Tugas Hari Ini mengarah ke pesanan, withdraw, verifikasi IB, anomali dan kegagalan terbit key; pengumuman yang dipublikasikan tampil di dashboard client.
15. FAQ bergambar dan testimoni nyata dapat dibuat admin; testimoni tidak dapat diinput oleh publik.
16. Anomali terdeteksi/dideduplikasi; IP berubah normal tidak langsung mensuspend lisensi.
17. API EA menolak akun salah, expired, revoked, suspended, replay nonce; offline hanya melanjutkan lisensi yang sebelumnya valid sampai 48 jam dan batas expiry.
18. Produk indikator berlisensi **tidak dirilis** sebelum uji PoC MT5 (termasuk instalasi dan Strategy Tester) berhasil.
19. File `.ex5` client lain tidak bisa diunduh meskipun URL ditebak/disalin; token unduhan kedaluwarsa.
20. Backup dapat direstore, cron untuk tugas ringan berjalan, **tidak ada ketergantungan bot/queue worker**, domain lama melayani API selama migrasi, dan halaman privat tidak bisa diakses lintas client.
21. Tampilan client/admin responsif pada 360, 768 dan 1280 px; navigasi Livewire nyaman; SEO publik serta noindex/otorisasi privat benar.
22. **Visual fidelity:** beranda, detail produk, dashboard client dan panel admin mengikuti identitas V2.2/V2.3 (Forest/Warm Bone/Lime/Coral, editorial trading lab), bukan tema biru generik; tabel admin tetap mudah dibaca.
23. **Modal konsisten:** semua tindakan kritis pada matriks 5.18 memakai modal kustom dengan judul spesifik, detail akurat, Batal/Konfirmasi, fokus keyboard dan spinner ketika submit; **tidak ada `alert/confirm/prompt` bawaan browser**.
24. **Loading nyata:** navigate dan aksi submit memberi feedback; tombol proses disabled; upload menampilkan % hanya ketika terukur; di jaringan lambat muncul "Masih diproses" dan pada timeout ada **Cek Status** tanpa duplikasi transaksi.
25. **Rebate lengkap:** client IB dapat melihat **Hasil Rebate, Broker IB Saya, Pencairan Rebate**, dengan status akun dan riwayat broker. Admin memiliki pasangan halaman **Akun IB Client, Input Rebate, Pencairan** yang memengaruhi nilai yang sama di database.
26. **Tidak ada fake success:** saldo, verifikasi, pembayaran, lisensi tidak tampak berubah sampai server mengonfirmasi. Halaman kosong, gagal, no permission dan sesi habis menampilkan pesan serta tindakan lanjut.
27. **Uji aksesibilitas & mobile:** modal Esc/focus trap/restore, label error dibaca screen-reader, target sentuh 44 px, overlay tidak menutupi tombol pada 360 px, dan layar bisa digunakan tanpa mouse.
28. **Korespondensi URL–halaman:** route di Bagian 13 dirender sebagai halaman Livewire dengan tindakan sesuai blueprint 5.16; 13 kategori menu admin dapat menggabungkan beberapa route konten tanpa menghapus fiturnya.

## 12. Keputusan yang Perlu Dikonfirmasi (Tidak Membebani Client)

PRD v1.3 menggunakan **default sederhana agar pengembangan dapat dimulai**. Hanya keputusan bisnis berikut yang perlu ditetapkan pemilik; semuanya tetap bisa dijadikan pengaturan admin kemudian:

| Topik | Default PRD | Perlu keputusan sebelum rilis |
| --- | --- | --- |
| Tahun kalender vs 12 bulan | Sampai 31 Desember; copy masa akses jelas | Konfirmasi model bisnis final |
| Potongan bulanan | Linear X% dengan harga minimum | Angka X%, harga minimum/pembulatan |
| Renewal | H-30, harga awal tahun berikutnya jika aktif, harga bulan berjalan jika expired | Konfirmasi aturan diskon renewal |
| Rebate | Nilai rebate entry adalah **hak bersih client** yang diinput admin, bukan persentase berbagi hasil otomatis | Minimum penarikan, rekening/e-wallet, persetujuan broker dan tarif |
| Akun MT5 | Satu akun per key; satu penggantian per masa lisensi | Konfirmasi batas perubahan bila berbeda |
| Indikator MQL5 | **Tunda penjualan berlisensi** hingga integrasi PoC berhasil | Pilih mekanisme teknis + penanggung jawab kode MQL5 |
| Infrastruktur | PHP 8.3+, database, cron ringan, backup dan domain lama aktif saat migrasi; **tanpa worker Telegram/queue wajib** | Pilih hosting dan masa pemeliharaan domain lama |
| Legal | Periksa izin broker, instrumen, dan ketentuan IB per yurisdiksi | Peninjauan sebelum publikasi program IB |

**Yang sudah diputuskan:** tanggal WIB `Asia/Jakarta`, tema terang, upload media hanya admin, Telegram **akun pribadi untuk chat manual** (tanpa bot/approve otomatis), konfirmasi pembayaran **hanya di panel admin**, tidak ada email/WA otomatis, tidak ada 2FA client, performa menyatu dalam detail produk, testimoni diinput manual admin. **Tidak perlu mengulang pertanyaan ini ke client.**

## 13. Daftar URL Aplikasi (Route List)

**Base URL saat MVP:** `https://tradergaruda.sukaphp.com` (dibaca dari `APP_URL` di `.env`). Path relatif terhadap base URL. **Preview, cara pakai dan performa tetap satu URL `/produk/{slug}`**. Navigasi web menggunakan `wire:navigate`; area privat dilindungi middleware + policy, bukan hanya noindex. Parameter dalam `{...}` adalah placeholder. Action simpan/konfirmasi/suspend/withdraw memakai Livewire dengan auth, policy, CSRF dan transaksi DB, bukan URL publik tambahan.

### 13.1 Frontend publik dan autentikasi

| Method | URL | Halaman / fungsi | Akses / SEO |
| --- | --- | --- | --- |
| GET | `/` | Beranda, produk unggulan, promo broker, testimoni admin, FAQ, **Chat Admin Telegram pribadi** | Publik / index |
| GET | `/produk` | Katalog indikator dan EA MT5 | Publik / index |
| GET | `/produk/{slug}` | **Satu detail produk:** deskripsi, preview penggunaan, track record, changelog, FAQ, ulasan, harga dan Sewa | Publik / index |
| GET | `/broker` | Promo broker dan kalkulator rebate | Publik / index |
| GET | `/edukasi` | Daftar artikel edukasi | Publik / index |
| GET | `/edukasi/kategori/{kategori}` | Kategori Signal / Cara Trading | Publik / index |
| GET | `/edukasi/{slug}` | Detail artikel; artikel khusus client memerlukan hak akses | Sesuai visibilitas |
| GET | `/faq` | FAQ umum; FAQ per produk juga tampil di `/produk/{slug}` | Publik / index |
| GET | `/panduan` | Daftar panduan instalasi bergambar | Publik / index |
| GET | `/panduan/{slug}` | Detail panduan (langkah dan screenshot MT5) | Publik / index |
| GET | `/tentang` | Informasi usaha | Publik / index |
| GET | `/kontak` | Kontak dan link Telegram admin | Publik / index |
| GET | `/kebijakan-privasi` | Kebijakan privasi | Publik / index |
| GET | `/syarat-layanan` | Syarat layanan, perpanjangan dan refund | Publik / index |
| GET | `/disclaimer-risiko` | Risiko trading dan batasan klaim hasil | Publik / index |
| GET | `/login` | Login client/admin | Tamu / noindex |
| GET | `/register` | Registrasi singkat dan pilih kanal Telegram/WA yang tersedia | Tamu / noindex |
| GET | `/checkout/{slug}` | Checkout ringkas: akun MT5, harga, tahun layanan/tanggal berakhir | Client login / noindex |
| GET | `/pesanan/{kode}` | Detail pesanan, transfer manual, **Salin Detail** dan **Chat Admin Telegram pribadi** | Pemilik pesanan/admin / noindex |
| GET | `/go/telegram` | Opsional: catat klik lalu redirect ke `t.me/{username_admin}` **tanpa pesan terkirim** | Publik / noindex |
| GET | `/go/broker/{id}` | Catat klik dan redirect ke link IB mitra | Publik / noindex |

### 13.2 Area client

| Method | URL | Halaman / fungsi | Akses / SEO |
| --- | --- | --- | --- |
| GET | `/member` | Dashboard sederhana: Produk Saya, Pesanan, bantuan, pengingat, pengumuman; rebate bila ikut IB | Client / noindex |
| GET | `/member/produk` | Produk Saya dan ringkasan lisensi | Client / noindex |
| GET | `/member/produk/{licenseId}` | **Detail LISENSI** (bukan ID produk), key, akun MT5, tombol ganti akun, 50 log opsional dan Perpanjang | Pemilik lisensi / noindex |
| GET | `/member/unduhan` | File `.ex5` yang boleh diunduh dan riwayat versi | Client / noindex |
| GET | `/member/pesanan` | Daftar riwayat pesanan | Client / noindex |
| GET | `/member/rebate` | **Hasil Rebate:** saldo tersedia/tertahan/sudah dibayar, laporan per broker/periode/lot | Client IB / noindex |
| GET | `/member/rebate/akun` | **Broker IB Saya:** akun broker tertaut, status verifikasi, total per broker, tombol tambah akun | Client IB / noindex |
| GET | `/member/penarikan` | **Pencairan Rebate:** form, modal konfirmasi USD/IDR/rekening, status dan histori | Client IB / noindex |
| GET | `/member/profil` | Profil, kontak Telegram/WA, ganti password | Client / noindex |

**Catatan:** tombol Perpanjang dari `/member` atau `/member/produk/{licenseId}` membuka checkout renewal dengan lisensi yang ditandai secara aman di server (tanpa membuat route publik baru). Tidak ada halaman verifikasi lisensi/pengingat/pengumuman terpisah. Panduan umum tetap di `/panduan`.

### 13.3 Panel admin

| Method | URL | Halaman / fungsi | Akses / SEO |
| --- | --- | --- | --- |
| GET | `/admin` | Dashboard ringkasan dan **Tugas Hari Ini**: tindakan yang harus diproses | Admin / noindex |
| GET | `/admin/produk` | Daftar produk EA dan indikator | Admin / noindex |
| GET | `/admin/produk/create` | **Satu formulir tambah produk** dengan tab info, preview, cara pakai, performa, versi, FAQ, SEO | Admin / noindex |
| GET | `/admin/produk/{id}/edit` | **Satu formulir edit produk** dengan tab yang sama dan upload `.ex5`/versi | Admin / noindex |
| GET | `/admin/pesanan` | Daftar, konfirmasi dan tolak pesanan | Admin / noindex |
| GET | `/admin/pesanan/{id}` | Detail pesanan, konfirmasi, status dan **refund manual** | Admin / noindex |
| GET | `/admin/lisensi` | Cari dan kelola lisensi, status serta daftar jatuh tempo | Admin / noindex |
| GET | `/admin/lisensi/{id}` | Detail lisensi, log verifikasi, reset, suspend, perpanjangan | Admin / noindex |
| GET | `/admin/anomali` | Daftar flag keanehan lisensi otomatis | Admin / noindex |
| GET | `/admin/anomali/{id}` | Detail bukti, catatan, tinjau/abaikan/suspend | Admin / noindex |
| GET | `/admin/client` | Kelola client dan reset password manual | Admin / noindex |
| GET | `/admin/broker` | Kelola broker, link IB dan rebate per lot | Admin / noindex |
| GET | `/admin/akun-trading` | Verifikasi akun trading IB, bukti/konfirmasi referral, alasan ditolak | Admin / noindex |
| GET | `/admin/rebate` | Input dan koreksi rebate manual dengan audit log | Admin / noindex |
| GET | `/admin/penarikan` | Daftar permintaan penarikan | Admin / noindex |
| GET | `/admin/penarikan/{id}` | Detail penarikan, bukti bayar dan proses status | Admin / noindex |
| GET | `/admin/edukasi` | Daftar artikel edukasi | Admin / noindex |
| GET | `/admin/edukasi/create` | Buat artikel dengan upload gambar | Admin / noindex |
| GET | `/admin/edukasi/{id}/edit` | Edit artikel, visibilitas dan SEO | Admin / noindex |
| GET | `/admin/faq` | Kelola FAQ umum dan FAQ produk | Admin / noindex |
| GET | `/admin/faq/create` | Tambah FAQ | Admin / noindex |
| GET | `/admin/faq/{id}/edit` | Edit FAQ | Admin / noindex |
| GET | `/admin/panduan` | Kelola panduan instalasi bergambar | Admin / noindex |
| GET | `/admin/panduan/create` | Tambah panduan beserta langkah/gambar | Admin / noindex |
| GET | `/admin/panduan/{id}/edit` | Edit panduan dan urutan langkah | Admin / noindex |
| GET | `/admin/testimoni` | **Kelola manual testimoni dan ulasan** (tanpa form publik) | Admin / noindex |
| GET | `/admin/testimoni/create` | Tambah testimoni/ulasan, kaitkan produk opsional | Admin / noindex |
| GET | `/admin/testimoni/{id}/edit` | Edit, izin/sumber testimoni, tampil/sembunyi, urutan | Admin / noindex |
| GET | `/admin/halaman` | Kelola halaman statis dan SEO | Admin / noindex |
| GET | `/admin/pengumuman` | Daftar pengumuman untuk dashboard client | Admin / noindex |
| GET | `/admin/pengumuman/create` | Buat pengumuman singkat | Admin / noindex |
| GET | `/admin/pengumuman/{id}/edit` | Edit, jadwal, aktif/nonaktif, target client | Admin / noindex |
| GET | `/admin/pengaturan` | Harga, rebate, kurs, Telegram, SEO, pengingat, anomali | Admin / noindex |
| GET | `/admin/log` | Jejak audit admin | Admin / noindex |

**Catatan UI:** **modal/alert/loading bukan halaman tersendiri**, sehingga **tidak menambah URL**. Pencairan dan rekening tujuan tetap pada route yang ada. Menu admin V2.2 yang berjumlah 13 adalah kategori navigasi; route CRUD konten di bawahnya tetap harus ada. Contoh prototype `#/admin/rebate` hanya alias demo; produksi memakai `/admin/rebate`.

**Catatan implementasi:** route `GET` di atas adalah **URL halaman**, bukan seluruh action backend. Simpan, hapus, konfirmasi, unggah file, dan suspend menggunakan action Livewire yang terproteksi oleh policy/authorization, validasi, dan CSRF. Daftar ini adalah rancangan URL yang disepakati, belum berarti route telah dibuat.

### 13.4 Endpoint teknis dan API (Tanpa Telegram Webhook)

| Method | URL | Fungsi | Proteksi |
| --- | --- | --- | --- |
| GET | `/sitemap.xml` | Sitemap otomatis halaman publik | Publik |
| GET | `/robots.txt` | Sitemap dan arahan crawler; tidak dipakai mengamankan halaman privat | Publik |
| GET | `/member/unduh/{versionId}` | Unduh `.ex5` sementara milik lisensi aktif | Login + policy + signed URL + expired cepat |
| GET | `/health` | Health check privat/minimal untuk server monitor | Internal/terlindungi, tidak bocorkan konfigurasi |
| POST | `/logout` | Akhiri sesi login | CSRF + sesi yang sah |
| POST | `/api/v1/licenses/verify` | Verifikasi key, produk, nomor akun, versi, timestamp, nonce | Rate limit, signature, validasi replay |
| POST | `/api/v1/licenses/deactivate` | Opsional: deaktivasi aktivasi, **nonaktif default hingga aman** | Autentikasi khusus/anti-replay, bukan publik bebas |

**Jumlah:** **71 route/endpoint** dalam daftar (webhook Telegram dihapus dari 72 pada v1.4). Action Konfirmasi Pembayaran tetap action Livewire terproteksi di panel admin, **bukan** URL Telegram. Unduh file memakai URL bertanda tangan dan tetap memeriksa kepemilikan lisensi.

### 13.5 Contoh URL lengkap

- Katalog: `https://tradergaruda.sukaphp.com/produk`
- Detail EA (contoh slug): `https://tradergaruda.sukaphp.com/produk/ea-garuda-trend`
- Buat produk di admin: `https://tradergaruda.sukaphp.com/admin/produk/create`
- Dashboard client: `https://tradergaruda.sukaphp.com/member`
- Tugas Hari Ini: `https://tradergaruda.sukaphp.com/admin`
- Monitoring keanehan lisensi: `https://tradergaruda.sukaphp.com/admin/anomali`
- Testimoni admin: `https://tradergaruda.sukaphp.com/admin/testimoni`

**Tidak dibuat:** `/produk/{slug}/performa`, `/produk/{slug}/preview`, atau halaman track record terpisah. Anchor seperti `#performa` boleh dipakai di URL detail produk untuk langsung menuju section tanpa memecah halaman.

---

## 14. Checklist Sederhana Sebelum Launch

| Fokus | Pengecekan |
| --- | --- |
| Client tidak ribet | Uji 1 pengguna baru di HP: bisa beli, bayar, salin key, unduh dan pasang tanpa penjelasan admin tentang API; menu IB tidak dipaksakan |
| Produk | Semua harga dan expiry terlihat sebelum bayar, data performa bersumber dan review berizin |
| EA | Key valid dan salah akun benar-benar terblokir; offline dan pergantian domain dites |
| Admin | **Konfirmasi pembayaran hanya lewat panel admin setelah verifikasi mutasi**, modal, refund, reset password dan audit berjalan; Telegram bukan alat approve |
| Rebate | Saldo tidak bisa minus/dobel akibat dua request, tarif dapat diaudit, akun IB sudah diverifikasi |
| Sistem | Cron untuk tugas berkala sederhana, backup/restore, HTTPS, akses privat, proteksi file dan error monitoring aktif; tidak memerlukan bot/worker Telegram |
| Feedback antarmuka | Tidak ada tombol diam, spinner/loading per aksi, navigasi halus, upload progress jujur, semua error & empty state diuji |
| Modal dan konfirmasi | Semua aksi kritis memakai modal kustom dengan rincian nominal/akun/konsekuensi; tidak ada alert/confirm/prompt browser; loading tidak menduplikasi transaksi |
| Konsistensi demo–produksi | Cocokkan V2.2/V2.3: style, modal/loading, tiga fitur Rebate client, 13 kategori admin, tab editor produk dan data admin ↔ client lewat database |
| Indikator | Jangan tawarkan sebagai produk lisensi aktif sampai PoC MQL5 berhasil |

**Prinsip prioritas akhir:** Bila satu fitur menambah langkah client tanpa manfaat jelas, pindahkan ke proses otomatis/admin atau tunda. Keamanan transaksi, ketepatan saldo, tanggal lisensi, dan kejujuran performa **tidak** boleh disederhanakan dengan cara menghilangkan validasi.


## 15. Matriks Uji Antarmuka (Wajib Sebelum Serah Terima)

Bagian ini menjadi **kontrak pemeriksaan visual dan interaksi** terhadap prototype V2.2/V2.3 dan spesifikasi v1.4. Uji dengan data seed **berlabel DEMO hanya di lingkungan staging**; produksi kosong hingga ada data benar.

| ID | Skenario langkah uji | Harapan yang dapat diamati |
| --- | --- | --- |
| UI-01 | Buka beranda di 360 px dan 1280 px | Hero tidak terpotong, palet/typography sesuai, CTA produk/IB/Telegram jelas; tidak ada scroll horizontal |
| UI-02 | Buka detail produk, pindah anchor | Satu URL memuat Preview, Cara Pakai, Track Record berlabel, FAQ, Ulasan, Harga & Sewa; section kosong hilang |
| UI-03 | Klik Sewa dengan simulasi koneksi lambat | Tombol memuat/disabled, tidak membuat order dua kali; status dan tanggal akhir jelas sebelum bayar |
| UI-03B | Pada halaman pesanan klik Salin Detail, kemudian Chat Admin | Clipboard berisi kode/produk/nominal untuk dikirim client sendiri; membuka `t.me/{username_admin}` tanpa mengirim otomatis, **status tetap Menunggu Pembayaran** |
| UI-04 | Admin klik **Konfirmasi Pembayaran di panel** | Modal tampilkan order/nominal/akun + checkbox cek mutasi rekening, Batal tak mengubah data, tombol spinner; key terbit sekali setelah sukses server |
| UI-05 | Client dari akun tanpa IB membuka dashboard | Menu Rebate tidak dipaksakan; ada jalan opsional dari halaman Broker |
| UI-06 | Client IB membuka `/member/rebate` | Saldo tersedia, total, dibayar, ditahan, hasil per broker/periode/lot; tidak tercampur dengan kalkulator estimasi |
| UI-07 | Client IB membuka Broker IB Saya dan mendaftarkan akun | Modal form validasi, status Menunggu; admin dapat menyetujui; setelah refresh client status Terverifikasi |
| UI-08 | Admin input rebate pada akun IB tervalidasi | Tombol loading, referensi duplikat ditolak; setelah berhasil, saldo & histori client bertambah satu kali |
| UI-09 | Client mengajukan pencairan valid | Modal USD/IDR/kurs/rekening, saldo direservasi hanya setelah server berhasil, status Diajukan tercatat |
| UI-10 | Admin memindah status Diajukan→Diproses→Dibayar | Modal rincian & bukti/ref wajib; data client menunjukkan Dibayar, tidak menambah pembayaran ganda |
| UI-11 | Admin menolak pencairan | Modal alasan wajib; status Ditolak; saldo yang semula ditahan kembali tersedia tepat sekali |
| UI-12 | Admin suspend lisensi/anomali | Modal dampak dan catatan, spinner, audit; client mendapat status yang benar tanpa data teknis IP/nonce |
| UI-13 | Upload file versi EA dan gambar panduan | Persentase hanya bila upload terukur; tahap server ditandai Memproses; selesai setelah file tersimpan & tervalidasi |
| UI-14 | Koneksi diputus setelah klik konfirmasi berisiko | Tidak tampil sukses palsu; pesan "Status belum dapat dipastikan" dan Cek Status; retry aman tidak menggandakan transaksi |
| UI-15 | Submit form invalid dan session expired | Pesan error tepat di field, nilai tidak hilang; session expired meminta login ulang dengan modal yang jelas |
| UI-16 | Operasikan modal pakai keyboard | Fokus masuk, Tab terkurung, Esc menutup ketika aman, fokus kembali ke tombol pemicu, pembaca layar mendapat judul dan pesan |
| UI-17 | Navigasi admin di ponsel 360 px | Sidebar drawer, 13 kategori dapat diakses; tabel rebate/pencairan menjadi kartu atau scroll yang dapat digunakan |
| UI-18 | Admin mengedit satu produk dan membuka preview publik | Perubahan deskripsi, preview, performa, versi, FAQ, harga/SEO tersimpan per tab dan tampil di **satu detail produk** |
| UI-19 | Client yang tidak punya data (produk/IB/rebate/withdraw) | Empty state yang relevan, bukan contoh angka transaksi palsu; CTA berikutnya tetap jelas |
| UI-20 | Audit seluruh route Bagian 13 | Route benar, hak akses tepat, tombol bekerja atau diberi status nonaktif/Coming Soon yang jujur; tidak ada tombol dekoratif |
| UI-21 | Coba kirim chat/bukti transfer, **tanpa** admin klik panel | Pesanan **tidak** berubah ke Lunas dan key belum terbit; tidak ada Bot API/webhook/notifikasi otomatis |
| UI-22 | Admin memverifikasi mutasi, klik Konfirmasi di panel dua kali atau retry karena timeout | Satu commit Lunas dan satu key untuk kombinasi yang benar; audit admin lengkap; client melihat status setelah refresh |
| UI-23 | Kosongkan atau ubah username Telegram di Pengaturan | Sistem memvalidasi akun tujuan; link Chat Admin tidak salah arah, fallback manual tampil jika Telegram tak dikonfigurasi |

**Definition of Done UI:** fitur dinyatakan selesai hanya jika **tampilan sesuai**, **loading/progress benar**, **modal dan validasi berfungsi**, **perubahan tersimpan di database**, **hak akses aman**, dan **jalur gagal/empty/mobile telah diuji**. Prototype statis saja tidak dianggap implementasi selesai.


## 16. Lampiran A — Paket HTML Referensi Visual dan Interaksi (digabung di handoff v1.6)

Lampiran ini menambahkan file HTML pendukung implementasi; **tidak mengubah keputusan bisnis v1.5**. Rujukan adalah folder `html-reference/`. File HTML dirancang sebagai **blueprint tampilan**, bukan aplikasi dengan backend atau otorisasi sesungguhnya. Keseluruhan route dan kriteria di Bagian 13–15 tetap wajib dipenuhi saat pengembangan Laravel.

**Acuan desain:** forest green `#102821`, acid lime `#dcff69`, warm bone `#f5f3eb`, coral `#f77b60`; bahasa Indonesia; mobile-first; editor produk bertab. Semua halaman memuat label mode demo supaya data tidak disalahartikan sebagai transaksi, broker, performa trading atau lisensi sebenarnya.

| Grup | File HTML | Route Laravel yang direpresentasikan |
| --- | --- | --- |
| Publik | `01-beranda.html` | `/` |
| Publik | `02-detail-produk.html` | `/produk/{slug}` |
| Publik | `03-broker-publik.html` | `/broker` |
| Client | `04-checkout.html` | `/checkout/{slug}` |
| Client | `05-pesanan.html` | `/pesanan/{kode}` |
| Client | `06-client-dashboard.html` | `/member` |
| Client | `07-client-produk.html` | `/member/produk` |
| Client | `08-client-broker-ib.html` | `/member/rebate/akun` |
| Client | `09-client-hasil-rebate.html` | `/member/rebate` |
| Client | `10-client-pencairan.html` | `/member/penarikan` |
| Admin | `11-admin-dashboard.html` | `/admin` |
| Admin | `12-admin-pesanan.html` | `/admin/pesanan` |
| Admin | `13-admin-verifikasi-ib.html` | `/admin/akun-trading` |
| Admin | `14-admin-input-rebate.html` | `/admin/rebate` |
| Admin | `15-admin-pencairan.html` | `/admin/penarikan` |
| Admin | `16-admin-produk.html` | `/admin/produk/{id}/edit` |
| Admin | `17-admin-lisensi.html` | `/admin/lisensi` |
| Sistem UI | `18-komponen-modal-loading.html` | `Komponen bersama` |
| Admin | `19-admin-pengaturan.html` | `/admin/pengaturan` |

**File pelengkap:** `index.html` (indeks navigasi), `assets/style.css` (desain bersama), `assets/app.js` (interaksi demo), `ROUTE_MAPPING.csv` (pemetaan route), `README.md` (panduan developer) dan screenshot desktop/mobile. Seluruh HTML memerlukan folder `assets` untuk bekerja.

### Aturan implementasi yang harus dipertahankan

1. **Telegram 100% manual.** Link `t.me/{username}` baru aktif setelah akun pribadi admin disetel. Tidak ada Telegram Bot API, webhook, atau pesan otomatis. Client menyalin ringkasan pesanan lalu mengirimnya sendiri bersama bukti transfer; status tidak berubah hanya karena chat dibuka.
2. **Pembayaran ditetapkan Lunas hanya oleh admin di panel.** Modal konfirmasi memuat kode, nominal, akun MT5 dan checkbox pengecekan mutasi. Backend Laravel melakukan commit dan penerbitan key atomik, idempoten, serta mencatat audit. Delay demo tidak boleh disalin ke produksi.
3. **Performa tidak dibuat-buat.** Contoh detail produk sengaja tidak memuat metrik profit/win rate/backtest fiktif. Tampilkan hanya data hasil pengujian nyata yang diunggah dan dijelaskan admin.
4. **Rebate client = sumber data server.** Dashboard broker IB, saldo, riwayat rebate, saldo ditahan dan pencairan harus bersumber dari transaksi DB yang sah; tidak menggunakan `localStorage` produksi. Demo mempraktikkan arus data, bukan model keamanan.
5. **Loading dan progress akurat.** Modal/tombol loading saat request berlangsung. Progres persentase hanya bila dapat diukur, misalnya FileReader contoh untuk baca file lokal; implementasi upload perlu progress transfer nyata. Timeout menyampaikan “Status belum dapat dipastikan” dan tindakan cek status sebelum retry.
6. **Akses dan navigasi.** File HTML demo boleh diakses tanpa login untuk review, tetapi route `/admin` dan `/member` produksi wajib middleware, authorization policy dan noindex. Gunakan `wire:navigate` Livewire 4, bukan navigasi file statis.
7. **Aksesibilitas.** Pertahankan keyboard focus modal, label form, validasi di bawah field, responsivitas dan desain token bersama. Angka/demo tidak otomatis menjadi data produksi.
8. **Cakupan:** hanya halaman referensi penting yang dipecah untuk memudahkan review. Fitur di PRD yang tidak memiliki HTML khusus (FAQ, panduan, edukasi, kebijakan, pengumuman, setting final, log, API) tetap masuk implementasi sesuai PRD.

### Acceptance reference

- **Uji UI-03B, UI-04, UI-21, UI-22** memakai `04-checkout.html`, `05-pesanan.html`, `12-admin-pesanan.html` dan `07-client-produk.html`.
- **UI-06 sampai UI-11** memakai `08-client-broker-ib.html` hingga `10-client-pencairan.html` serta `13-admin-verifikasi-ib.html`, `14-admin-input-rebate.html`, `15-admin-pencairan.html`.
- **UI-02 & UI-18** memakai `02-detail-produk.html` dan `16-admin-produk.html`.
- **UI-13 sampai UI-16** memakai `18-komponen-modal-loading.html` dan komponen bersama.

**Catatan:** Uji antarmuka demo bukan Definition of Done. Produk baru dapat dinyatakan siap produksi setelah integrasi backend, keamanan, database, pengujian MT5, backup/restore, validasi keuangan dan keseluruhan kriteria PRD telah dibuktikan.


---

## 17. Handoff v1.7 — Kontrak Implementasi Menuju Produksi

### 17.1 Dokumen yang wajib dipakai bersama

- `PROMPT.md` — instruksi utama developer/AI coding agent, batasan teknis, urutan pengerjaan, dan definisi selesai. Alias ejaan pengguna: `promt.md` (isi identik).
- `html-reference/` — referensi desain HTML individual + CSS/JS demo; **bukan** source aplikasi final.
- `PRODUCTION_GATE.md` — checklist P0 dan P1 yang harus memiliki bukti lulus sebelum domain produksi menerima transaksi.
- `RELEASE_HANDOFF.md` — artefak rilis, migration, perintah aplikasi, kebutuhan environment dan serah terima ke tim infra. `DEPLOYMENT.md` hanya penunjuk dokumen handoff.
- `README.md` — indeks paket, cara membaca, dan status batasannya.

### 17.2 Pembekuan keputusan dan penyederhanaan

**Tidak ditawar:** telegram akun pribadi untuk chat manual; admin memeriksa mutasi dan konfirmasi hanya di website; EA/indikator satu halaman detail produk; IB opt-in; rebate multi-broker dengan riwayat, saldo dan pencairan; modal/loading/progress nyata; frontend khas forest/lime/bone/coral; autentikasi admin kuat; data privat selalu di DB dan terlindungi Policy.

**Tidak menambah kompleksitas:** tidak ada Telegram bot, payment gateway, broker API, scheduler tambahan di luar cron sederhana, queue worker wajib, real-time websocket wajib, atau isian teknis pada client. Data belum tersedia seperti username Telegram asli, rekening, tarif produk, kurs, minimum pencairan dan broker yang berizin harus dikonfigurasi admin sebelum live; **tidak boleh menggunakan data contoh HTML**.

### 17.3 Kontrak integritas (P0)

1. Status `Lunas` dan penerbitan key harus atomik/idempoten, serta satu-satunya pemicu adalah admin yang telah mengonfirmasi dana masuk. Aman terhadap dua klik, dua request concurrent, dan timeout.
2. Satu key per pemilik + produk + akun; renewal memakai key yang sama. Pastikan aturan unique constraint benar-benar berlaku pada MySQL 8.4 LTS; jangan hanya mengandalkan validasi UI.
3. Rebate berupa catatan immutable. Saldo USD dihitung deterministik; withdrawal harus mereservasi saldo dalam transaksi dengan lock. Penolakan/ pembatalan mengembalikan reservasi sekali dan pembayaran dilakukan admin hanya setelah transfer nyata.
4. WebRequest MQL5 pada EA harus lulus uji aplikasi terminal nyata, verifikasi signature dan status, serta gangguan jaringan; indikator **tidak dijual sebagai berlisensi** sampai PoC.
5. Semua aksi memodifikasi saldo, pesanan, lisensi dan rekening hanya boleh di server dengan otorisasi; tidak boleh mempertahankan mekanisme state demo berbasis `localStorage`.
6. Upload `.ex5` privat; keys/secrets tidak masuk HTML publik/log; pengujian akses silang wajib.

### 17.4 Perilaku UI yang tidak boleh hilang

- Modal konfirmasi untuk pembayaran, kredit rebate, penarikan, refund dan suspend; konfirmasi menunjukkan nominal/kode/rekening yang benar serta konsekuensi.
- `Loading` dan `Memproses…` mengikuti request Livewire nyata, tombol disabled, dan kondisi *status unknown* setelah timeout memberi opsi **Cek Status**.
- Progres persentase hanya untuk event upload yang terukur; jangan memakai progress simulasi produksi.
- Semua halaman mempunyai loading/empty/error/success states; admin dan client melihat database sama, dengan refresh saat kembali ke halaman tanpa kewajiban WebSocket.
- Frontend mobile-first dan warna/karakter sesuai `html-reference/assets/style.css`, bukan UI generik.

### 17.5 Aturan rilis

Paket ini adalah **dokumen handoff dan prototype**, bukan kode backend produksi. Rilis hanya ketika seluruh P0 di `PRODUCTION_GATE.md` dinyatakan **PASS dengan bukti** (tes otomatis, bukti uji terminal MT5, staging/UAT, restore backup, domain HTTPS, legalitas per broker, dan informasi asli admin telah dikonfigurasi). Jika satu P0 masih BLOCKED/FAIL, aplikasi tidak boleh memproses transaksi nyata. **Tidak ada klaim sudah siap go-live pada tanggal dokumen ini.**


## 18. Lampiran Spesifikasi Teknis v1.7 (Bagian Normatif PRD)

**Prioritas saat terdapat perbedaan:** keputusan UX/bisnis pada PRD dan Telegram manual adalah prinsip tetap; **untuk rincian tipe data, limit, tanda tangan, action, route, dan konfigurasi, lampiran teknis v1.7 berikut mengungguli paragraf ringkas/draf lama**. Tidak boleh ada webhook Telegram, bot, atau notifikasi otomatis. Aplikasi **baru** dalam tahap dokumentasi/development; rilis ditentukan gate QA.

| Dokumen | Fungsi | Task penanggung jawab |
|---|---|---|
| `technical/DATABASE_SCHEMA.md` | 30 tabel domain, kolom, tipe, relasi, indeks, transaksi uang dan lisensi | TG-006, TG-017, TG-025, TG-027 |
| `technical/API_CONTRACT.md` | Envelope Ed25519, anti-replay, error/status, offline grace dan PoC MQL5 | TG-003, TG-019, TG-020, TG-040 |
| `technical/ACTION_AUTH_MATRIX.md` | 23 aksi server, policy, state machine, idempotency, modal & pengujian | TG-007, TG-009, TG-016, TG-029, TG-038 |
| `technical/ROUTES_MATRIX.csv` / `.md` | 71 URL/endpoint, metode, role, noindex dan target handler | TG-036, TG-041 |
| `technical/SECURITY_BASELINE.md` | Auth, TOTP, upload, session, enkripsi, abuse protection, audit | TG-007, TG-010, TG-034, TG-042 |
| `technical/ENVIRONMENT.md` + `.env.example` | Kontrak konfigurasi aplikasi; host, cron, backup fisik dan deploy sebagai dependensi pihak infra, tanpa secret nyata | TG-004, TG-005, TG-035, TG-042 |
| `technical/TEST_PLAN.md` | Test P0/P1 nyata untuk pembayaran, API, RBAC, saldo, mobile, rollback | TG-038–TG-044 |

**Kunci keputusan teknis:** MySQL 8.4 LTS; angka uang/lot DECIMAL; ledger rebate append-only dan `wallets` sebagai baris lock, bukan saldo yang diedit manual; `api_nonces` untuk anti-replay; Ed25519 signature yang diverifikasi oleh EA sebelum mempercayai respons. **Bila PoC signature EA belum lulus, penjualan produk berlisensi tetap nonaktif**. `TG_INDICATOR_SALES_ENABLED=false` sampai mekanisme indikator terbukti dan mendapat keputusan tersendiri. Domain lama tetap melayani API untuk transisi.

**Tidak menambah fitur/client steps:** semua perlindungan tersebut terjadi di server/admin. Alur client tetap Pilih Produk → Pesan → Transfer & Chat Admin Manual → Salin Key/Unduh. IB sepenuhnya opsional; jika bergabung, client mendapat Broker IB Saya, Hasil Rebate, Pencairan.

**Batas status:** paket ini `READY FOR IMPLEMENTATION` (dokumen siap jadi acuan), **bukan** `GO-LIVE READY`. Uji staging, bukti integrasi terminal MT5 dan semua gate P0 dilaksanakan sambil development berjalan, tidak dituntut selesai sebelum coding dimulai.


## 19. Batas Tanggung Jawab Software Development — Final v1.7.1

**Bagian ini bersifat normatif dan mengungguli semua kalimat lama yang dapat ditafsirkan sebagai penugasan infrastruktur kepada developer.** Lihat `SCOPE_OF_WORK.md`. Tim software **hanya** menangani kode Laravel/Livewire/Tailwind, migrasi dan query aplikasi, API/lisensi MT5, keamanan level aplikasi, UI/UX, pengujian, artefak rilis dan handoff.

Pengelolaan VPS, server operasi, DNS/domain, SSL/TLS sertifikat, Nginx/PHP-FPM host, firewall, instalasi database, provisioning secret produksi, cron host, backup/restore fisik, monitoring uptime server dan deployment/cutover adalah **di luar scope**. Jika muncul di KPI, NFR, acceptance atau dokumen teknis lama, itu adalah kebutuhan dan dependensi pihak lain, **bukan** deliverable tim software.

**Penerimaan dibagi dua:** `SOFTWARE_ACCEPTANCE.md` untuk hasil kerja developer, dan `PRODUCTION_GATE.md` untuk keseluruhan kesiapan go-live yang ditetapkan pemilik bersama infra/legal. Keputusan membuka penjualan riil tidak bisa diambil hanya dari bukti software. Jumlah backlog utama tetap **44 task**; TG-004, TG-035, TG-042 dan TG-044 telah disesuaikan ke cakupan aplikasi.
