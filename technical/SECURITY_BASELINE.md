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

## Implementasi TG-010 (5 November 2026)

- Middleware menerbitkan UUID server per request, mengabaikan ID dari header client; `X-Request-ID`, error JSON/HTML dan activity audit memakai korelasi yang sama. Metadata request mencatat actor ID dan status HTTP; status HTTP200 Livewire tidak berarti suatu validasi bisnis berhasil.
- `App\Support\Audit` memakai schema metadata per action autentikasi; field tambahan, array arbitrer, password, kontak, rekening, key dan alasan bebas tidak disalin. Hasil sukses hanya dicatat dalam transaksi mutasi yang sama. Action baru wajib menambah schema dan tes; service ini tidak memberi otorisasi otomatis.
- Semua channel logging yang dideklarasikan memakai tap allowlist. Pesan arbitrer menjadi `application.event`; exception object, stack trace, nested context dan extra dibuang. Hanya UUID request, actor/subject ID integer serta status integer yang diterima. Ini sengaja membatasi diagnosis ke event/korelasi; jangan menaruh secret dalam pesan atau memasang processor baru tanpa review.
- Exception handler tidak meneruskan exception report mentah; HTML/JSON generik tetap aman ketika APP_DEBUG=true. Error403/404/419/429/503 mempertahankan status HTTP, `Retry-After`/`Allow` yang relevan, private/no-store/noindex. Validasi dan redirect login tetap memakai alur framework. Daftar input yang tidak boleh di-flash mencakup credential/OTP/key/rekening.
- `php artisan tg:prune-license-logs` default dry run. `--apply` menghapus telemetry lebih tua90hari dan mengosongkan IP raw terenkripsi lebih tua30hari, dalam batch1000 dengan transaksi/lock. Batas tanggal bersifat strict-before dan diuji; tidak menghapus activity audit, termasuk yang berusia lebih dari5tahun. Keputusan retensi audit/legal hold tetap milik owner/legal.
- Default baru memakai log daily14file. `.env` existing tetap dipertahankan: bila `LOG_STACK=single`, ubah menjadi `daily` untuk rotasi file otomatis. Retensi sink eksternal/berkas log lama ditangani operator; tidak ada penghapusan otomatis di luar telemetry aplikasi.
- Command retensi belum dijadwalkan otomatis: integrasi Laravel Scheduler adalah TG-035; cron host milik infra. Command `--apply` hanya dijalankan pada database test selama validasi TG-010.

Bukti: [`validation/TG010.md`](validation/TG010.md). Tidak merupakan kelulusan seluruh G-21 atau audit produksi.

## Implementasi TG-016–TG-018 (6 November 2026)

Konfirmasi pembayaran mengunci order lalu lisensi dalam transaksi yang sama dengan paid dan audit; replay order paid mengembalikan receipt. Role admin, TOTP dan reauthentication diperiksa pada mutasi. Key baru memakai random_bytes(16), terenkripsi dengan cast Laravel, SHA-256 untuk lookup dan unique triple pemilik/produk/akun. Renewal mempertahankan ciphertext/hash. Catatan mutasi bank disimpan hanya pada order dan tidak disalin ke audit/log. Perubahan status menyimpan kategori alasan dan hash intent; replay tidak mengembalikan state lama. Revoked terminal.

File privat diperiksa canonical path di luar public, pola UUID, ukuran dan SHA-256 pada handle terkunci yang sama dengan stream. URL signed maksimal5 menit tidak membawa key/path; GET tetap memeriksa sesi pemilik, hak download, expiry, status, fingerprint state dan versi aktif produk yang sesuai. Respons no-store/private/nosniff. Perubahan status menutup request baru; stream yang sudah terkirim dan akses EA offline memerlukan batas tersendiri. Tidak mengklaim PoC offline/revocation selesai.

Bukti: [`validation/TG016-TG018.md`](validation/TG016-TG018.md).

## Implementasi TG-019/TG-021/TG-022 — 6 November 2026

Verify stateless membatasi HTTPS/JSON/body4KiB, rate60/key/jam dan120/IP/jam (bucket HMAC), timestamp±300detik dan nonce canonical minimal128bit. Unique SHA-256 nonce dan TTL10menit mencegah replay pada dua worker; cleanup dry run default tidak menghapus live window. Signer Ed25519 memerlukan private file terbatas di luar public, tidak menerima public key dari request. Semua verdict200 signed; kesalahan request tidak mengungkap keberadaan identitas. License row lock memastikan state/aktivasi konsisten; telemetry tidak berisi raw key/nonce/IP. Tidak ada klaim verifier MQL5/offline grace lulus.

Produk Saya scoped pemilik di setiap render/action, no-store/private, key tidak masuk property publik Livewire, log maksimal50 tanpa metadata sensitif. Clipboard hanya melaporkan sukses setelah writeText selesai. Account changes mengunci license→request, memeriksa hash identity sebelum approval, unique triple dan limit satu approval/tahunWIB. Intent request dan keputusan terminal aman terhadap retry lama. Refund mengunci order→refund, menghitung reserved+completed dengan BCMath, mewajibkan bukti referensi/cek transfer sebelum completed, serta tidak mengubah lisensi/order paid historis. Semua audit atomik dan metadata terbatasi.

Bukti [`validation/TG019-TG022.md`](validation/TG019-TG022.md); TG-019/TG-020 menunggu EA/terminal nyata, flag penjualan tetapfalse.

## Fase 3 — IB/rebate/pencairan

Semua service mutasi admin memakai fresh actor + TOTP/reauth, setiap client render/action scoped pemilik aktif. URL broker HTTPS allowlisted; logo decode/re-encode/UUID pada storage privat. Rate history terstruktur; bukti IB, memo, tujuan, referensi transfer dan alasan bebas tidak disalin ke audit/log. Bukti pemilik dan hasil review tersimpan terpisah, tidak ditampilkan silang.

Uang memakai DECIMAL/BCMath; wallet anchor dibuat race-safe dan dikunci sebelum ledger/withdrawal. Duplicate receipt dan saldo memakai current reads dengan row lock, termasuk saat transaction caller sudah memiliki snapshot lama. Credits unik akun/reference, koreksi append-only reversal+replacement dengan available>=0. Quote client encrypted/authenticated mengikat owner, intent, amount, destination, FX/minimum/IDR dan expiry; amount/destination tidak diambil ulang dari browser saat submit. Rechecking pause dan available terjadi di lock; tujuan encrypted at rest, masked pada list, full admin detail memerlukan reauth.

UI meminta processing sebelum transfer manual; processing menutup cancel client. Paid/rejected terminal, retry tidak mendebit ulang/melepas reserve dua kali; audit gagal membatalkan seluruh mutasi. Reconciliation read-only melaporkan mismatch tanpa auto-fix. Risiko human double-transfer di bank tidak diklaim terselesaikan oleh aplikasi yang hanya mencatat transfer manual. Bukti software/concurrency/browser: validation/TG023-TG030.md; bukan kelulusan audit dana produksi.

## Fase 4 — Konten, anomali dan health

CMS memakai whitelist jenis/model/field, TOTP/reauth untuk mutasi, HTML allowlist dan images re-encode privat maksimal2MiB. Cover/gallery/step/photo endpoint membaca eligibility DB, client assets memerlukan client aktif; draft/future/unpublish menutup GET berikutnya. Testimonial visible memerlukan source/consent/checkbox, tidak menaruh consent pada publik/audit. Announcement dibatasi schedule dan audiens pada member dashboard. Metadata SEO optional yang rusak memakai fallback agar layout error aman tetap bekerja.

License flags default hanya observasi: >5account mismatch/jam, >5hashIP/jam, >60request/jam, >10expired/revoked/jam, unknown registered version. Ambang/window/cooldown dapat diatur reauth. Row lock license→flag dan rolling cooldown mencegah spam/reopen keputusan lama; evidence hanya jumlah. RawIP, key, nonce dan signature tidak ditampilkan. Review/ignore/suspend manual dengan catatan/audit; suspend lifecycle dalam transaksi yang sama. Tidak ada auto-suspend atau atribusi unknown key ke korban.

Health memakai bearer minimal32karakter, constant-time compare, tanpa query token, session atau cookie. Unauthorized404, dependency503 generik, sehat200 minimal; token tidak punya default. Scheduler memakai shared-cache overlap/multi-server protection; hanya expiry unpaid, flag, nonce/telemetry retention dan read-only reconciliation. Activity audit finansial tetap dipertahankan. Tidak menjalankan backup/monitor/cron host. Bukti software: validation/TG031-TG037.md; tidak menggantikan EA terminal, legal atau audit produksi.

## Review main 7 November 2026

Bukti aktual di `validation/SECURITY_MAIN_REVIEW.md`: perubahan harga produk memerlukan reauth di transaksi, default enkripsi sesi aktif, Secure cookie default production, header browser global termasuk error/Livewire, dan storage menolak public-link exposure/canonical-path escape. Tes hanya pada DB aktual tg_test yang diverifikasi terpisah dari database aplikasi. Advisory Composer belum dapat diperiksa lokal karena jaringan; CI dependency audit menjadi gate, bukan klaim bahwa audit remote telah lulus.
