# API_CONTRACT.md — Lisensi EA MetaTrader 5, v1.7

**Status kontrak:** target implementasi **dibekukan untuk development**; integrasi cryptographic verifier MQL5 dan pengujian terminal nyata masih wajib sebelum penjualan EA. **Indikator belum boleh ditawarkan berlisensi online** hingga Proof-of-Concept terpisah lulus. Protokol ini tidak bergantung pada Telegram.

## 1. Endpoint dan batasan

- Base URL: `https://tradergaruda.sukaphp.com` dari konfigurasi Laravel, HTTPS wajib.
- `POST /api/v1/licenses/verify`: satu endpoint verifikasi stateless dari EA; tidak memakai sesi browser atau CSRF, tetapi memiliki request validation, nonce anti-replay, rate limiting dan pencatatan aman.
- `POST /api/v1/licenses/deactivate`: **feature flag off** untuk MVP, mengembalikan 404 atau 403 generik; aktivasi/ganti akun dilakukan melalui admin. Tidak boleh menjadi API pencabutan publik berbekal license key saja.
- `GET /health`: status minimal untuk monitoring internal, tanpa menampilkan versi DB, secret, account, saldo, atau konfigurasi.
- **Tidak ada** `/telegram/webhook`, bot API, callback atau layanan notifikasi Telegram.
- Semua kegagalan sistem (timeout/5xx/DNS) adalah state lokal `network_unavailable`, bukan status lisensi server.

## 2. Request verify

`Content-Type: application/json; charset=utf-8`, `Accept: application/json`, batas body 4 KiB. Semua field required kecuali `server` hanya jika secara eksplisit produk diizinkan; default tetap required.

```json
{
  "protocol": 1,
  "license_key": "ABCD-EFGH-JKLM-NPQR",
  "product_id": 10,
  "account_number": "12345678",
  "server": "Broker-MT5-Real",
  "account_type": "real",
  "version": "1.0.0",
  "timestamp": "2026-11-04T04:15:00Z",
  "nonce": "base64url-128-bit-random"
}
```

**Format penerbitan TG-017:** key baru berupa 8 grup masing-masing 4 karakter hex uppercase (128 bit CSPRNG), dipisahkan tanda hubung. Contoh 4 grup di atas bersifat ilustrasi, bukan batas panjang validator. Verifier TG-019 wajib menerima format penerbitan ini tanpa memotong key; renewal tidak mengganti key.

**Validasi:** key case-insensitive, uppercase normalize, jangan log raw key. Nomor akun MT5 dikirim sebagai string (bukan JSON number). `account_type` hanya `real`/`demo`; simbol/pair tidak masuk identitas lisensi. Timestamp UTC ISO 8601, skew maksimal ±300 detik, nonce minimal 128 bit CSPRNG, base64url (no padding) maksimal 128 karakter; hash SHA-256 nonce dicatat di `api_nonces` dengan unique index dan TTL 10 menit. Satu nonce hanya boleh diterima sekali secara atomik. Ukuran string, nomor produk, dan versi dibatasi; request abnormal -> 422.

Pencocokan selalu menggunakan **nomor akun dari terminal MT5 aktual** (`ACCOUNT_LOGIN`), bukan input bebas di UI EA; server dan tipe akun dibaca dari terminal, tidak boleh diedit client untuk menyamar. Server cocok dengan order/lisensi; akun tidak cocok -> `account_mismatch`; versi terlalu tua -> `update_required`. Jangan menyimpan raw payload.

## 3. Respons bertanda tangan (envelope)

Tanda tangan memakai **Ed25519**. Server menandatangani *byte persis* UTF-8 JSON payload yang dibentuknya, **bukan** serialisasi ulang JSON oleh EA. Format ini menghindari perbedaan urutan key/whitespace PHP vs MQL5. Signature Ed25519 berupa 64 byte, encoded base64url tanpa padding. `payload_b64` adalah base64url dari byte UTF-8 JSON payload. `kid` dapat dibaca untuk memilih public key yang **dipin dalam build EA**; `kid` juga termuat di payload yang ditandatangani.

```json
{
  "payload_b64": "BASE64URL_UTF8_JSON_PAYLOAD",
  "signature": "BASE64URL_64_BYTE_ED25519_SIGNATURE",
  "kid": "tg-ed25519-2026-a"
}
```

Payload JSON yang setelah decode harus cocok dengan struktur ini (contoh non-produksi):

```json
{
  "protocol": 1,
  "kid": "tg-ed25519-2026-a",
  "status": "valid",
  "product_id": 10,
  "account_number": "12345678",
  "server": "Broker-MT5-Real",
  "account_type": "real",
  "version": "1.0.0",
  "expires_at": "2026-12-31T17:00:00Z",
  "latest_version": "1.1.0",
  "min_supported_version": "1.0.0",
  "api_base_url": "https://tradergaruda.sukaphp.com",
  "server_timestamp": "2026-11-04T04:15:01Z",
  "request_nonce": "base64url-128-bit-random",
  "request_id": "1d86c1aa-a82b-4a48-b5c7-3d11c749d572"
}
```

**Aturan waktu final:** `expires_at` adalah batas **eksklusif**, tepat **1 Januari 00:00:00 WIB** tahun berikutnya (contoh 2026-12-31T17:00:00Z). EA valid hanya saat `now < expires_at`; tampilan user menyebut *berlaku sampai 31 Desember 23:59:59 WIB*. Uji peralihan tepat detiknya. Tidak ada tahun layanan otomatis tanpa pembayaran.

### Validasi wajib di EA sebelum memakai status

1. Terima envelope dengan batas byte dan parser yang aman; `kid` harus termasuk daftar public key yang **ditanam sebelumnya di binary** (jangan mengambil public key dari respons tidak tepercaya).
2. Decode `payload_b64` jadi byte UTF-8, verify Ed25519 menggunakan public key sesuai `kid` terhadap byte itu **sebelum** memercayai payload.
3. Parse JSON yang sudah diverifikasi; pastikan `payload.kid == envelope.kid`, `request_nonce` sama persis dengan request saat ini, `product_id`, `account_number`, `server`, `account_type`, dan `protocol` sama; `server_timestamp` wajar dan tidak mundur jauh.
4. `status` harus `valid`, waktu belum kedaluwarsa, versi cukup; selain itu EA tidak menjalankan fungsi berlisensi (kecuali offline grace di bawah).
5. `api_base_url` baru hanya dipercaya setelah signature sah dan URL termasuk host yang diterima kebijakan migrasi; jangan menjalankan URL sembarang dari respons.
6. Signature salah, nonce mismatch, payload malformed atau `kid` tak dikenal => **fail closed**, log alasan yang aman, jangan memakai keputusan `valid` dari respons.

**PoC wajib:** Ed25519 verification dalam **pure MQL5 atau komponen yang disetujui secara eksplisit** dan tanpa mengharuskan pengguna mengaktifkan DLL arbitrer. Jika verifier belum dapat diintegrasikan/diuji dengan test vector, **EA berlisensi tidak boleh go-live**. Jangan mengganti dengan HMAC rahasia tertanam di EX5 atau signature dekoratif untuk mengklaim keamanan setara.

## 4. Status dan HTTP

| HTTP | Status payload | Perilaku EA |
|---|---|---|
| 200 | `valid` | Boleh aktif hingga waktu/versi yang ditentukan |
| 200 | `invalid` | Tolak |
| 200 | `expired` | Tolak |
| 200 | `revoked` | Tolak |
| 200 | `suspended` | Tolak |
| 200 | `account_mismatch` | Tolak, tampilkan instruksi hubungi admin |
| 200 | `server_mismatch` | Tolak, tampilkan instruksi hubungi admin |
| 200 | `update_required` | Tolak, tampilkan petunjuk unduh versi |
| 400/422 | error request (tanpa verdict valid) | Jangan memperlakukan sebagai aktif |
| 409 | `replay_detected` | Tolak request, regenerasi nonce setelah investigasi; jangan otomatis menganggap valid |
| 429 | `rate_limited` | Backoff, jangan berulang agresif |
| 500/502/503 | error server | Perlakukan sebagai kegagalan jaringan, aturan grace terbatas |

Untuk HTTP 200, semua status bisnis **harus** ditandatangani. Respons 4xx/5xx tidak boleh menjadi sumber keputusan valid. Respons error memakai `request_id` dan kode generik, tanpa mengungkap apakah suatu akun/produk/key ada di DB.

## 5. Cache offline dan pencabutan

- Offline grace maksimal **48 jam** setelah keputusan `valid` online terakhir (nilai admin tidak boleh melampaui 48 jam untuk MVP), dan **tidak boleh melewati tanggal expiry**.
- Simpan keputusan terverifikasi, expiry dan timestamp terakhir. Gunakan mekanisme penyimpanan lokal yang diuji terhadap edit/salin file; akui batasan proteksi bila file/clock terminal dimanipulasi. Jangan menjamin anti-pembajakan 100%.
- Jika menerima respons online bertanda tangan berstatus `revoked`/`suspended`/`expired`/`account_mismatch`, **nonaktif seketika**, cache tidak boleh menimpa verdict online.
- Batasan yang harus dipublikasikan: ketika EA offline, pencabutan dari admin mungkin baru efektif sesudah grace period berakhir; keamanan tidak boleh diklaim sebagai pencabutan instan saat offline.
- Retry jaringan dengan exponential backoff + jitter; default verifikasi saat init dan tiap 6 jam; jangan menahan thread trading berlebihan. `WebRequest()` sinkron di EA; timeout awal 5000 ms dan penjadwalan retry perlu diuji di MT5 terminal nyata.

## 6. Rate limit, anonimitas, migrasi domain

- Baseline: 60 request/jam per fingerprint key, 120/jam per IP dengan toleransi cold starts; hasil rate limit jangan menyatakan key invalid. Angka tuning boleh diubah setelah beban riil diukur, dan harus didokumentasikan pada SECURITY_BASELINE.
- Simpan log `request_id`, status, version, hash IP sesuai retensi; jangan menyimpan license key, nonce plaintext atau detail rekening di log.
- Host lama **harus tetap melayani verify** saat domain baru digunakan, minimal sampai versi terlama yang masih berhak menggunakan host lama tidak lagi memiliki lisensi aktif, atau sampai seluruh client menjalankan versi yang dikonfirmasi kompatibel. `api_base_url` saja tidak akan menolong jika host lama mati.
- Host baru ditambahkan ke allowlist WebRequest manual di terminal MT5 jika URL baru digunakan; jangan mengasumsikan EA dapat memperbaruinya otomatis.

## 7. Rotasi kunci

- Server private key Ed25519 berada di secret manager/file hanya-baca di luar webroot, izin 0600, bukan git atau .env publik.
- Public key dipin di build EA; tetapkan `kid` unik. Untuk rotasi, kirim versi EA dengan public key lama+baru sebelum server berganti signature ke `kid` baru. Simpan kunci lama selama semua versi valid membutuhkan kunci tersebut.
- Backup dan pemulihan key pair termasuk prosedur insiden; key compromise membutuhkan rencana migrasi build EA dan pemberitahuan manual, tidak cukup mengganti `.env`.

## 8. Test vector dan Definition of Done (TG-019, TG-020, TG-040)

Artefak wajib setelah implementasi: `tests/fixtures/license_signature_valid.json`, `license_signature_tampered.json`, `license_signature_wrong_nonce.json`, `license_signature_unknown_kid.json`, dengan public key dan byte payload hex/base64, signature yang nyata, tanpa private key produksi. Cross-language suite PHP sign → MQL5 verify sukses, perubahan 1 byte gagal, replay ditolak server, salah akun/expired/suspend/revoke ditolak, network offline diuji di terminal MT5 nyata. **Contoh envelope di dokumen ini bukan test vector kriptografis siap pakai.**

Referensi teknis: https://www.mql5.com/en/docs/network/webrequest ; https://laravel.com/docs/13.x/deployment (atau dokumentasi Laravel 13 terkini).
