# Trader Garuda — HTML Pendukung PRD v1.5.1

Paket ini merupakan **referensi halaman visual dan perilaku antarmuka**, bukan aplikasi Laravel yang siap produksi. Rancangan mengacu pada **PRD Trader Garuda v1.5 (Telegram Manual)** dan menambahkan lampiran rute ↔ file HTML pada versi **v1.5.1**. Bahasa desain: **Editorial Trading Lab** (Forest Green, Acid Lime, Warm Bone, Coral); antarmuka berbahasa Indonesia, desktop dan mobile.

## Cara membuka

1. Ekstrak seluruh folder ZIP dengan struktur folder tetap utuh.
2. Buka `index.html` untuk daftar halaman dan tautan ke setiap HTML. Untuk melihat layout statis, HTML dapat dibuka langsung di browser.
3. **Untuk simulasi lintas halaman yang konsisten**, jalankan server lokal di dalam folder ini, lalu buka `http://localhost:8000`:
   - Windows, jika Python terpasang: jalankan `python -m http.server 8000` melalui PowerShell pada folder ini.
   - Alternatif: gunakan ekstensi **Live Server** di VS Code lalu buka `index.html`.
4. Hapus data simulasi melalui halaman `18-komponen-modal-loading.html` → **Reset Demo**. Penyimpanan menggunakan `localStorage` di browser; tidak ada akun server.

> Saat dibuka sebagai `file://`, beberapa browser membatasi penyimpanan antarfile. Gunakan server lokal apabila perubahan di halaman admin tidak muncul di halaman client.

## Struktur file

| File | Sasaran route pada PRD | Tujuan |
| --- | --- | --- |
| `index.html` | Dokumentasi | Pintu masuk demo & navigasi antarhalaman |
| `01-beranda.html` | `/` | Beranda, katalog, promosi IB, CTA |
| `02-detail-produk.html` | `/produk/{slug}` | Preview, cara pakai, performa, versi dan harga dalam satu halaman |
| `03-broker-publik.html` | `/broker` | Broker IB & kalkulator estimasi |
| `04-checkout.html` | `/checkout/{slug}` | Checkout singkat nomor akun MT5 |
| `05-pesanan.html` | `/pesanan/{kode}` | Transfer manual, salin rincian dan chat Telegram pribadi |
| `06-client-dashboard.html` | `/member` | Ringkasan client & CTA sesuai status |
| `07-client-produk.html` | `/member/produk` | Lisensi, key, unduhan (simulasi) |
| `08-client-broker-ib.html` | `/member/rebate/akun` | Broker/akun trading yang diikuti, pengajuan verifikasi |
| `09-client-hasil-rebate.html` | `/member/rebate` | Total, saldo tersedia, terbayar, ditahan, histori |
| `10-client-pencairan.html` | `/member/penarikan` | Form dan riwayat pencairan |
| `11-admin-dashboard.html` | `/admin` | Tugas Hari Ini dan ringkasan |
| `12-admin-pesanan.html` | `/admin/pesanan` | Konfirmasi pembayaran manual + checkbox cek mutasi |
| `13-admin-verifikasi-ib.html` | `/admin/akun-trading` | Verifikasi/tolak akun IB client |
| `14-admin-input-rebate.html` | `/admin/rebate` | Entri rebate manual dan cek duplikat |
| `15-admin-pencairan.html` | `/admin/penarikan` | Diproses / Dibayar / Ditolak + ref transfer |
| `16-admin-produk.html` | `/admin/produk/{id}/edit` | Satu editor bertab: info, preview, performa, file/versi, FAQ/SEO |
| `17-admin-lisensi.html` | `/admin/lisensi` | Status lisensi & flag anomali |
| `18-komponen-modal-loading.html` | UI bersama | Modal, loading, validasi, error, demo progress FileReader |
| `19-admin-pengaturan.html` | `/admin/pengaturan` | Telegram pribadi, kurs, minimum pencairan |
| `assets/style.css` | — | Seluruh desain responsif, tema dan komponen reusable |
| `assets/app.js` | — | Render dan interaksi simulasi lintas halaman |
| `PRD_Trader_Garuda_v1.5.1_Dengan_HTML.md` | Spesifikasi | PRD v1.5 + Lampiran A daftar file referensi |
| `ROUTE_MAPPING.csv` | Pemetaan | Nama file, route Laravel target, area, UI yang direpresentasikan |
| `preview-*.png` | — | Hasil pratinjau yang diperiksa pada desktop/mobile |

## Skenario interaksi yang bisa dicoba

**Pesanan manual:** `04-checkout.html` → buat pesanan → `05-pesanan.html` → salin detail & buka Telegram pribadi (aktif hanya jika username sudah diisi di `19-admin-pengaturan.html` (atau UI Kit)) → `12-admin-pesanan.html` → konfirmasi + centang sudah cek mutasi → `07-client-produk.html` (lisensi demo muncul **sekali**).

**Rebate & pencairan:** `08-client-broker-ib.html` → tambah akun → `13-admin-verifikasi-ib.html` → setujui → `14-admin-input-rebate.html` → isi periode, lot, USD dan referensi unik → `09-client-hasil-rebate.html` → `10-client-pencairan.html` → ajukan → `15-admin-pencairan.html` → catat transfer dan status Dibayar. Ketika pengajuan masih berjalan, nominalnya dihitung sebagai **ditahan**, bukan tersedia.

**Produk:** `16-admin-produk.html` → tab Informasi → simpan nama/harga/deskripsi demo → `02-detail-produk.html` untuk memeriksa perubahan. Track record sengaja kosong karena tidak ada laporan pengujian trading yang diverifikasi.

**Interaksi UI:** `18-komponen-modal-loading.html` → modal konfirmasi, tombol loading, status timeout yang belum pasti, toast, validasi, dan pembacaan file lokal untuk memperlihatkan **persentase FileReader yang benar-benar terukur**. Bukan simulasi upload server.

## Ketentuan penting untuk developer

- **Tidak ada Telegram Bot API, webhook/callback, chat ID, token bot, ataupun otomatisasi konfirmasi pembayaran.** Username Telegram pribadi admin kosong secara default; admin mengisinya sendiri. Klik tombol hanya membuka `https://t.me/{username}`; client mengirim pesan dan bukti sendiri.
- Konfirmasi lunas **hanya di `/admin/pesanan`** setelah admin mengecek mutasi rekening. Setelah commit server sebenarnya, Laravel menerbitkan/perpanjang lisensi secara atomik dan idempoten.
- Tombol demo saat ini memakai modal dan delay **simulasi**; ketika implementasi Livewire, ganti sepenuhnya dengan feedback asli server (`wire:loading`, validasi, transaksi database, cek status, keamanan akses).
- `localStorage` **bukan database** dan tidak memiliki otorisasi. Jangan menyalin mekanisme penyimpanan demo ke kode produksi.
- Dalam demo, rebate, lot, rekening, identitas client dan broker hanyalah **contoh**. Data pengujian performa EA tidak dibuat-buat dan indikasi profit tidak dijanjikan.
- Product indicator belum boleh dijual sebagai lisensi aktif hingga PoC teknis MQL5 selesai.
- Halaman referensi tidak mencakup setiap route PRD (FAQ, artikel, halaman legal, log, setting sesungguhnya dan berbagai endpoint API); implementasikan sesuai bagian PRD, bukan menebak dari halaman yang tidak ada.
- Akses panel admin tidak diamankan dalam demo, tetapi **wajib autentikasi admin, policy, CSRF, validasi server, audit dan transaksi DB** pada Laravel.
- Loading tombol dalam demo memiliki jeda artifisial yang **jelas dilabeli simulasi**. Persentase upload hanya boleh mengikuti progress nyata; modal timeout wajib memeriksa status sebelum retry.
- Jika ada konflik antara desain HTML dan syarat bisnis, PRD v1.5.1 lebih tinggi prioritasnya.

## Pengujian referensi

Pengujian Playwright berbasis render HTML terisolasi telah menjalankan **20 file HTML**, alur pembelian dan penerbitan lisensi, persetujuan IB, input rebate, pengajuan/pembayaran rebate, serta pemeriksaan lebar layar 390px pada delapan halaman terpilih. Tidak ditemukan page error JavaScript dalam skenario tersebut. Ini **bukan** pengujian server, domain publik, keamanan, ataupun aplikasi produksi.

