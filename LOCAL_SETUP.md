# Menjalankan Trader Garuda di komputer lokal

Gunakan branch `main`. Database MySQL 8.4 yang sudah disiapkan dapat langsung dipakai; tidak perlu membuat database Docker baru. Prasyarat: PHP 8.4, Composer 2, Node.js 24/npm, serta ekstensi bcmath, gd, sodium dan pdo_mysql (Composer memeriksa kebutuhan lainnya).

## Ambil kode dan dependency

Untuk clone pertama:

```bash
git clone --branch main https://github.com/masdeveloperindo/tg.git
cd tg
```

Untuk checkout yang sudah ada, simpan perubahan lokal Anda terlebih dahulu, kemudian:

```bash
git switch main
git pull --ff-only origin main
```

Pasang dependency terkunci dan buat `.env` hanya jika belum ada:

```bash
composer install --no-interaction --prefer-dist
php -r "file_exists('.env') || copy('.env.example', '.env');"
npm ci
npm run build
```

## Hubungkan database yang sudah ada

Edit `.env` secara lokal. Isi nama database, username dan password sesuai database Anda, bukan nilai contoh. Untuk MySQL yang berjalan langsung pada komputer yang sama:

```dotenv
APP_ENV=local
APP_URL=http://127.0.0.1:8000
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
```

Sesuaikan `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` dan port bila berbeda. Pastikan `DB_URL` tidak mengarahkan ke koneksi lain. `DB_HOST=db` pada `.env.example` khusus jaringan Compose; ganti menjadi alamat server MySQL Anda saat menjalankan PHP langsung. Untuk HTTP lokal, gunakan `SESSION_SECURE_COOKIE=false`.

**Hanya jika APP_KEY masih kosong**, jalankan sekali:

```bash
php artisan key:generate
```

Pertahankan APP_KEY existing, khususnya bila database berisi data aplikasi terenkripsi. `.env` dan password tidak masuk Git. Jika memakai database aplikasi existing dari komputer lain, gunakan APP_KEY yang sesuai melalui konfigurasi privat.

Terapkan migration biasa, lalu jalankan aplikasi:

```bash
php artisan optimize:clear
php artisan migrate
php artisan serve --host=127.0.0.1 --port=8000
```

Buka **http://127.0.0.1:8000**. Database existing tidak memerlukan `migrate:fresh`, reset atau seeder demo. Build frontend sudah tersedia setelah `npm run build`; Vite tidak perlu terus berjalan. Bila mengubah frontend, build ulang.

## Akun

Daftar client melalui `/register`. Jika belum ada admin, buat dari terminal baru di folder proyek:

```bash
php artisan tg:create-admin
```

Perintah meminta nama/email/password secara interaktif. Tidak ada akun atau password default; login admin memerlukan penyiapan TOTP. Jangan menjalankan pembuatan admin lagi jika admin sudah tersedia.

Penjualan EA tetap tertutup sampai integrasi EA dan gate penerimaan selesai. Konfigurasi produk, Telegram pribadi, rekening dan broker diisi owner melalui alur aplikasi yang tersedia.

Panduan Docker dan pengembangan lengkap: [DEVELOPMENT.md](DEVELOPMENT.md). Runner QA hanya untuk database terpisah `tg_test`, karena fixture pengujian mengosongkan schema test; jangan arahkan pengujian ke database aplikasi Anda.
