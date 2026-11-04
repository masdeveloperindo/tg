# SECURITY_BASELINE.md — Keamanan Minimum Trader Garuda v1.7

**Tujuan:** pengamanan internal ketat, pengalaman client tetap sederhana. Baseline ini merupakan konfigurasi awal untuk development/staging yang harus diuji dan disesuaikan dengan trafik riil sebelum rilis. Tidak ada Telegram bot, webhook, OTP client, payment gateway otomatis atau fitur kompleks tambahan.

## 1. Hak akses, auth dan sesi

| Kontrol | Aturan wajib |
|---|---|
| Role | `client` dan `admin` saja; middleware auth + `can`/Policy pada **setiap** route dan Livewire action, bukan berdasarkan tombol disembunyikan |
| Registrasi | Email/password/contact Telegram atau WA wajib; email dinormalisasi lowercase + unique; password Argon2id (Laravel default sesuai server) |
| Login rate limit | 5 percobaan/15 menit per email+IP dan 20 percobaan/15 menit per IP; pesan error tidak mengonfirmasi email terdaftar |
| Client | Tidak diwajibkan TOTP/2FA atau verifikasi email pada MVP; invalidasi sesi setelah perubahan password |
| Admin | TOTP wajib, 10 recovery code sekali pakai (hash), waktu sesi admin idle maksimal 30 menit; re-auth TOTP/password untuk refund, transfer dibayar, reset key dan perubahan konfigurasi sensitif bila re-auth >10 menit |
| Reset client manual | Permintaan lewat kontak yang telah terdaftar + konfirmasi bukti yang hanya diketahui pemilik secara aman (tanpa meminta password/OTP), dua pemeriksaan sebelum reset; admin menandai `must_change_password`, semua sesi lama diputus; jejak audit wajib |
| Cookies | `Secure`, `HttpOnly`, `SameSite=Lax`, sesi terenkripsi/ditandatangani oleh framework, HTTPS only; session ID regenerate saat login; CSRF untuk web/Livewire |
| Logout | POST dengan CSRF. Tidak ada GET yang mengubah state |

**Catatan:** jangan menyimpan TOTP seed atau recovery code dalam log/chat. Recovery setup diperlukan ketika admin kehilangan perangkat; tidak ada bypass TOTP melalui nomor Telegram saja.

## 2. Enkripsi, data pribadi, dan audit

- `APP_KEY` unik per environment; rahasia aplikasi di `.env`/secret store dengan filesystem permissions sempit, `.env` tidak pernah masuk ZIP publik/repo; `APP_DEBUG=false` pada production.
- Field `users.contact_value_enc`, `withdrawals.destination_enc`, `licenses.key_ciphertext`, `users.totp_secret_enc` dan settings sensitif terenkripsi pada storage. Nomor rekening hanya tampil **masked** (`****1234`) pada daftar; penuh hanya pada detail penarikan saat admin memproses dan client miliknya.
- License lookup pakai SHA-256 hash string key dinormalisasi (key berentropi tinggi) dan unique index; key mentah tidak boleh tercetak pada log atau URL. Privasi client A/B selalu diuji di backend.
- Activity log mencatat actor, resource, `request_id`, tindakan, waktu, ringkasan sebelum/sesudah yang disensor. Uang USD/IDR boleh masuk catatan keuangan terotorisasi, tetapi rekening, password, key, TOTP, bukti identitas tidak disalin ke log umum.
- Retensi awal: `license_logs` 90 hari; IP raw terenkripsi maksimal 30 hari; audit admin dan finansial **minimal sesuai ketentuan hukum/akuntansi yang ditetapkan owner/legal** (baseline 5 tahun, dapat lebih panjang sesuai kewajiban). Menerima permintaan subjek data dan mengevaluasi retensi wajib sebelum penghapusan.
- Unduhan bukti bayar/transfer via disk privat + authz + temporary signed URL; URL signed tidak menggantikan policy check.

## 3. Upload, redirect dan frontend

- Upload media admin: JPG/PNG/WebP (maks 2 MiB), re-encode lewat library image, EXIF/metadata pribadi dibersihkan, random storage name; sanitasi editor HTML. Video direkomendasikan via tautan host terpercaya yang diizinkan.
- EX5: ekstensi `.ex5` + pemeriksaan server-side bytes/signature/checksum bila tersedia, maksimal awal 20 MiB, private disk di luar webroot, **tidak pernah dieksekusi oleh server**; `.mq5`, `.php`, `.exe`, script tidak didistribusikan secara publik. Download hanya pemilik dengan lisensi aktif; batas URL signed 5 menit.
- Path traversal dicegah dengan storage abstractions, bukan memakai path dari input; semua upload admin memerlukan auth + CSRF + verifikasi MIME, size, extension dan filename random.
- `/go/broker/{id}` mengambil URL dari DB yang telah diaudit admin (https, hostname dalam daftar mitra), menolak schema non-https/internal address; tidak menerima `?next=...` bebas. Telegram dari username tervalidasi `^[A-Za-z0-9_]{5,32}$` dan `https://t.me/{username}`.
- Security header baseline: `X-Content-Type-Options: nosniff`; `Referrer-Policy: strict-origin-when-cross-origin`; `Permissions-Policy` minimal; `Content-Security-Policy` diuji khusus untuk Livewire/Alpine/Vite sebelum enforce (prefer nonce/hashes, jangan memasang CSP yang merusak SPA). HSTS setelah TLS dan subdomain diverifikasi, aktifkan pada host yang memang siap HTTPS.
- SEO `noindex` halaman privat; jangan mengandalkan robots/noindex sebagai kontrol akses.

## 4. Keamanan transaksi/uang dan API

- Semua aksi `confirmOrder`, `recordRebate`, `requestWithdrawal`, `markWithdrawalPaid`, `refund`, `changeAccount` wajib transaksi DB + row lock, idempotency key, status machine, audit. Revisi UI tidak boleh mengubah data sebelum commit.
- Penarikan minimum di server; saldo USD diturunkan dari ledger nyata; tidak boleh negatif meski klik dua kali atau request paralel; `paid` butuh referensi/bukti transfer nyata dari admin dan tidak bisa diulang.
- Browser timeout setelah POST -> tampilkan `Status belum dapat dipastikan — Cek Status`, query DB; jangan mengirim ulang transaksi dengan token baru secara otomatis.
- Verifikasi lisensi API: HTTPS, timestamp ±300 detik, nonce 128-bit unik TTL 10 menit, Ed25519 signature yang diverifikasi nyata oleh EA, rate limit baseline 60 per fingerprint key/jam dan 120 per IP/jam, monitoring false positive. Login dan API limiter terpisah.
- Anomali lisensi: flag lebih dari 3 IP/24 jam atau >5 mismatch/jam sebagai bahan tinjau; **suspend otomatis off**. IP seluler/VPS sering berganti.

## 5. Batas Keamanan Aplikasi dan Handoff Infrastruktur

**Tanggung jawab software:** implementasikan TOTP admin, password hashing, session/cookie flags, CSRF, authorization/IDOR checks pada setiap action, rate limit API/login, validasi upload, storage logical private, audit log tanpa secret, kode health endpoint, pemilahan error, dependency vulnerability scan, dan test serangan aplikasi. Buat migration dan transaksi DB aman (locking dan uniqueness).

**Tanggung jawab infra (eksternal):** host Nginx/PHP-FPM, firewall/WAF, sertifikat TLS/HSTS pada host, jaringan, akun/privilege DB fisik, storage fisik, backup terenkripsi offsite dan restore, cron host, log server, monitoring ketersediaan, patch host dan backup/release rollback tingkat server. Tim developer mendokumentasikan requirement, tidak melakukan konfigurasi operasionalnya.

**Handoff wajib:** kunci/secret tidak dalam source atau log; aplikasi mendukung `APP_URL`/trusted proxies/secure session sesuai nilai env yang disediakan. Tim infra wajib menyatakan readiness terpisah sebelum transaksi nyata; developer hanya menerbitkan `SOFTWARE_ACCEPTANCE.md`. Proses backup/restore oleh infra dapat diuji dengan query verifikasi integritas dari tim software tanpa memberi tugas operasi backup kepada developer.


## 6. Uji keamanan wajib

S-01 client A akses order/key/rebate client B → 403/404.

S-02 client mencoba admin route **dan** Livewire action dengan ID buatan → 403.

S-03 pembelian dilipatgandakan, submit admin ganda / koneksi putus → satu order dan satu lisensi yang sah.

S-04 dua pencairan paralel USD yang melebihi saldo → satu diterima, satu ditolak; tidak negatif.

S-05 kunci expired/revoked/salah akun/replayed nonce/tampered signature → ditolak di EA yang diuji.

S-06 upload malware-disguised image/EX5, path traversal, HTML XSS -> ditolak/sanitized.

S-07 konfirmasi pembayaran hanya oleh admin TOTP melalui panel; akses dari Telegram chat tidak mengubah status.

S-08 admin reset password client invalidates old sessions, forces new password, audit tersimpan.

S-09 backup terenkripsi dipulihkan di staging, saldo dan key valid sama dengan snapshot yang benar.

S-10 CSP, cookies, rate limits, error pages, DEBUG off diperiksa pada staging.

**Gate:** jangan sebut aman/go-live sebelum semua pengujian P0 memiliki laporan PASS yang benar-benar diambil dari aplikasi Laravel dan terminal EA, bukan dari HTML demo.
