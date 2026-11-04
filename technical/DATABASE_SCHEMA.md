# DATABASE_SCHEMA.md — Kontrak Database Trader Garuda v1.7

**Keputusan final untuk development:** **MySQL 8.4 LTS**, charset `utf8mb4`, InnoDB, kolasi `utf8mb4_0900_ai_ci` (atau setara tersedia). ORM Laravel migrations menjadi sumber kebenaran, bukan menjalankan SQL bebas di produksi. Perubahan DB harus migration yang di-review.

**Model uang:** IDR `DECIMAL(20,2)`; USD `DECIMAL(20,4)`; lot `DECIMAL(18,4)`; kurs `DECIMAL(20,6)`. Penghitungan server menggunakan decimal/string math dan `ROUND_HALF_UP` terdokumentasi, tidak memakai `float`. Semua timestamp DB UTC `DATETIME(6)`; tampilan WIB. Semua PK `BIGINT UNSIGNED` auto increment kecuali disebut lain; timestamps `created_at, updated_at` wajib jika ada di tabel.

**MVP:** 30 tabel domain (26 dari PRD awal + 4 tambahan `wallets`, `api_nonces`, `admin_recovery_codes`, `pages`). Tabel bawaan Laravel (`sessions`, `cache`, `cache_locks`, `password_reset_tokens` bila diperlukan) terpisah, mengikuti versi framework. Tidak ada tabel Telegram bot/webhook. Rancangan ini **belum migration Laravel yang dieksekusi**; task TG-006 harus merealisasikannya dan menjalankan tes.

**Aturan relasi:** master/history keuangan umumnya `ON DELETE RESTRICT`; account dinonaktifkan, tidak dihapus untuk menghindari jejak finansial hilang. Kunci yang semestinya unik harus diberlakukan di DB. Status menggunakan `VARCHAR` + `CHECK` pada MySQL 8.4; Laravel validator/enum yang sama. Kolom bernama `_enc` memakai Laravel encryption dan disensor dari serialisasi API. UUID idempotency divalidasi.

## 1. Struktur semua tabel

### 01. `users`

Akun dan hak akses.

| Kolom | Tipe dan aturan |
|---|---|
| `id` | `BIGINT UNSIGNED PK` |
| `name` | `VARCHAR(120) NOT NULL` |
| `email` | `VARCHAR(191) NOT NULL UNIQUE lower-case normalized` |
| `password` | `VARCHAR(255) NOT NULL Argon2id hash` |
| `role` | `VARCHAR(16) NOT NULL DEFAULT client CHECK admin/client` |
| `status` | `VARCHAR(16) NOT NULL DEFAULT active CHECK active/disabled` |
| `contact_channel` | `VARCHAR(16) NULL CHECK telegram/whatsapp` |
| `contact_value_enc` | `TEXT NULL encrypted` |
| `must_change_password` | `BOOLEAN NOT NULL DEFAULT 0` |
| `totp_secret_enc` | `TEXT NULL encrypted admin only` |
| `totp_enabled_at` | `DATETIME(6) NULL` |
| `admin_last_reauth_at` | `DATETIME(6) NULL` |
| `created_at` | `DATETIME(6)` |
| `updated_at` | `DATETIME(6)` |

**Constraint/indeks:** UNIQUE(email); INDEX(role,status). No delete with financial history; disable instead.

### 02. `admin_recovery_codes`

Recovery TOTP admin; kode disimpan hash sekali pakai.

| Kolom | Tipe dan aturan |
|---|---|
| `id` | `BIGINT UNSIGNED PK` |
| `user_id` | `BIGINT UNSIGNED FK users.id RESTRICT` |
| `code_hash` | `CHAR(64) NOT NULL` |
| `used_at` | `DATETIME(6) NULL` |
| `created_at` | `DATETIME(6)` |

**Constraint/indeks:** UNIQUE(user_id,code_hash); INDEX(user_id,used_at). Recovery codes never logged in plaintext.

### 03. `products`

Master EA/indikator, indikator offline-sale until PoC.

| Kolom | Tipe dan aturan |
|---|---|
| `id` | `BIGINT UNSIGNED PK` |
| `name` | `VARCHAR(180) NOT NULL` |
| `slug` | `VARCHAR(191) NOT NULL` |
| `type` | `VARCHAR(16) NOT NULL CHECK ea/indicator` |
| `status` | `VARCHAR(20) NOT NULL CHECK draft/published/archived` |
| `sale_enabled` | `BOOLEAN NOT NULL DEFAULT 0` |
| `description` | `LONGTEXT NULL sanitized HTML` |
| `base_price_idr` | `DECIMAL(20,2) NOT NULL` |
| `minimum_price_idr` | `DECIMAL(20,2) NOT NULL DEFAULT 0` |
| `seo_title` | `VARCHAR(191) NULL` |
| `seo_description` | `VARCHAR(320) NULL` |
| `created_at` | `DATETIME(6)` |
| `updated_at` | `DATETIME(6)` |

**Constraint/indeks:** UNIQUE(slug); INDEX(type,status,sale_enabled). CHECK(base_price_idr>=0 AND minimum_price_idr>=0).

### 04. `product_versions`

Private file EX5, versi unik dan changelog.

| Kolom | Tipe dan aturan |
|---|---|
| `id` | `BIGINT UNSIGNED PK` |
| `product_id` | `BIGINT UNSIGNED FK products.id RESTRICT` |
| `version` | `VARCHAR(40) NOT NULL` |
| `path` | `VARCHAR(500) NOT NULL private disk` |
| `sha256` | `CHAR(64) NOT NULL` |
| `bytes` | `BIGINT UNSIGNED NOT NULL` |
| `changelog` | `TEXT NULL` |
| `min_supported_version` | `VARCHAR(40) NULL` |
| `is_active` | `BOOLEAN NOT NULL DEFAULT 0` |
| `released_at` | `DATETIME(6) NULL` |
| `created_at` | `DATETIME(6)` |
| `updated_at` | `DATETIME(6)` |

**Constraint/indeks:** UNIQUE(product_id,version); INDEX(product_id,is_active,released_at); satu active per produk ditetapkan dengan generated column MySQL `active_product_id = CASE WHEN is_active THEN product_id ELSE NULL END` STORED dan UNIQUE(active_product_id), selain validasi transaksi admin.

### 05. `product_media`

Screenshot/tutorial dan alt text.

| Kolom | Tipe dan aturan |
|---|---|
| `id` | `BIGINT UNSIGNED PK` |
| `product_id` | `BIGINT UNSIGNED FK products.id RESTRICT` |
| `media_type` | `VARCHAR(16) NOT NULL CHECK image/video_link` |
| `disk_path` | `VARCHAR(500) NULL` |
| `external_url` | `VARCHAR(1000) NULL` |
| `caption` | `VARCHAR(255) NULL` |
| `alt_text` | `VARCHAR(255) NULL` |
| `section` | `VARCHAR(25) NOT NULL CHECK preview/tutorial` |
| `sort_order` | `INT NOT NULL DEFAULT 0` |
| `created_at` | `DATETIME(6)` |
| `updated_at` | `DATETIME(6)` |

**Constraint/indeks:** INDEX(product_id,section,sort_order). External URL validated scheme and host.

### 06. `product_performances`

Data performa admin dengan metodologi transparan.

| Kolom | Tipe dan aturan |
|---|---|
| `id` | `BIGINT UNSIGNED PK` |
| `product_id` | `BIGINT UNSIGNED FK products.id RESTRICT` |
| `version_id` | `BIGINT UNSIGNED NULL FK product_versions.id RESTRICT` |
| `test_type` | `VARCHAR(20) NOT NULL CHECK backtest/forward_test` |
| `account_environment` | `VARCHAR(10) NOT NULL CHECK demo/real` |
| `starts_on` | `DATE NOT NULL` |
| `ends_on` | `DATE NOT NULL` |
| `symbol` | `VARCHAR(30) NOT NULL` |
| `timeframe` | `VARCHAR(12) NOT NULL` |
| `broker_name` | `VARCHAR(120) NULL` |
| `initial_capital` | `DECIMAL(20,2) NULL` |
| `spread` | `VARCHAR(90) NULL` |
| `commission` | `VARCHAR(90) NULL` |
| `leverage` | `VARCHAR(20) NULL` |
| `net_profit_pct` | `DECIMAL(12,4) NULL` |
| `max_drawdown_pct` | `DECIMAL(12,4) NULL` |
| `win_rate_pct` | `DECIMAL(12,4) NULL` |
| `profit_factor` | `DECIMAL(16,6) NULL` |
| `total_trades` | `INT UNSIGNED NULL` |
| `report_path` | `VARCHAR(500) NULL private` |
| `equity_image_path` | `VARCHAR(500) NULL` |
| `methodology_note` | `TEXT NULL` |
| `updated_by` | `BIGINT UNSIGNED FK users.id RESTRICT` |
| `created_at` | `DATETIME(6)` |
| `updated_at` | `DATETIME(6)` |

**Constraint/indeks:** INDEX(product_id,test_type,starts_on); CHECK(ends_on>=starts_on); display test type and real/demo, no invented values.

### 07. `orders`

Harga snapshot saat checkout, mutasi manual saja.

| Kolom | Tipe dan aturan |
|---|---|
| `id` | `BIGINT UNSIGNED PK` |
| `code` | `VARCHAR(40) NOT NULL` |
| `user_id` | `BIGINT UNSIGNED FK users.id RESTRICT` |
| `product_id` | `BIGINT UNSIGNED FK products.id RESTRICT` |
| `renewal_license_id` | `BIGINT UNSIGNED NULL FK licenses.id RESTRICT deferred migration` |
| `mt5_account` | `VARCHAR(24) NOT NULL` |
| `mt5_server` | `VARCHAR(100) NOT NULL` |
| `account_type` | `VARCHAR(8) NOT NULL CHECK real/demo` |
| `order_type` | `VARCHAR(12) NOT NULL CHECK new/renewal` |
| `coverage_year` | `SMALLINT UNSIGNED NOT NULL` |
| `price_locked_idr` | `DECIMAL(20,2) NOT NULL` |
| `status` | `VARCHAR(20) NOT NULL CHECK waiting/paid/rejected/expired` |
| `idempotency_key` | `CHAR(36) NOT NULL` |
| `expires_at` | `DATETIME(6) NOT NULL` |
| `confirmed_at` | `DATETIME(6) NULL` |
| `confirmed_by` | `BIGINT UNSIGNED NULL FK users.id RESTRICT` |
| `payment_checked_at` | `DATETIME(6) NULL` |
| `payment_note` | `TEXT NULL` |
| `payment_reference` | `VARCHAR(120) NULL` |
| `created_at` | `DATETIME(6)` |
| `updated_at` | `DATETIME(6)` |

**Constraint/indeks:** UNIQUE(code); UNIQUE(user_id,idempotency_key); INDEX(user_id,status,created_at); INDEX(status,expires_at); CHECK(price_locked_idr>=0). Expiry is checked at action time, scheduler only tidies.

### 08. `licenses`

Satu key untuk satu pengguna + produk + nomor akun MT5; tidak berubah saat renewal.

| Kolom | Tipe dan aturan |
|---|---|
| `id` | `BIGINT UNSIGNED PK` |
| `user_id` | `BIGINT UNSIGNED FK users.id RESTRICT` |
| `product_id` | `BIGINT UNSIGNED FK products.id RESTRICT` |
| `key_hash` | `CHAR(64) NOT NULL` |
| `key_ciphertext` | `TEXT NOT NULL encrypted` |
| `mt5_account` | `VARCHAR(24) NOT NULL` |
| `mt5_server` | `VARCHAR(100) NOT NULL` |
| `account_type` | `VARCHAR(8) NOT NULL CHECK real/demo` |
| `status` | `VARCHAR(12) NOT NULL CHECK active/suspended/revoked` |
| `coverage_year` | `SMALLINT UNSIGNED NOT NULL` |
| `expires_at` | `DATETIME(6) NOT NULL` |
| `issued_order_id` | `BIGINT UNSIGNED FK orders.id RESTRICT` |
| `created_at` | `DATETIME(6)` |
| `updated_at` | `DATETIME(6)` |

**Constraint/indeks:** UNIQUE(key_hash); UNIQUE(user_id,product_id,mt5_account); INDEX(user_id,status,expires_at). expired is derived when now >= expires_at; do not create second row for same triple.

### 09. `license_account_change_requests`

Ganti akun setelah admin setujui.

| Kolom | Tipe dan aturan |
|---|---|
| `id` | `BIGINT UNSIGNED PK` |
| `license_id` | `BIGINT UNSIGNED FK licenses.id RESTRICT` |
| `old_account` | `VARCHAR(24) NOT NULL` |
| `new_account` | `VARCHAR(24) NOT NULL` |
| `new_server` | `VARCHAR(100) NOT NULL` |
| `status` | `VARCHAR(12) NOT NULL CHECK pending/approved/rejected` |
| `reason` | `VARCHAR(500) NULL` |
| `requested_at` | `DATETIME(6) NOT NULL` |
| `decided_by` | `BIGINT UNSIGNED NULL FK users.id RESTRICT` |
| `decided_at` | `DATETIME(6) NULL` |
| `created_at` | `DATETIME(6)` |
| `updated_at` | `DATETIME(6)` |

**Constraint/indeks:** INDEX(license_id,status,requested_at); duplicate pending rejected transactionally; unique license triple checked before approving.

### 10. `license_activations`

Terakhir terhubung dan versi EA.

| Kolom | Tipe dan aturan |
|---|---|
| `id` | `BIGINT UNSIGNED PK` |
| `license_id` | `BIGINT UNSIGNED FK licenses.id RESTRICT` |
| `account_number` | `VARCHAR(24) NOT NULL` |
| `server_name` | `VARCHAR(100) NULL` |
| `account_type` | `VARCHAR(8) NULL` |
| `product_version` | `VARCHAR(40) NULL` |
| `last_seen_at` | `DATETIME(6) NULL` |
| `created_at` | `DATETIME(6)` |
| `updated_at` | `DATETIME(6)` |

**Constraint/indeks:** UNIQUE(license_id,account_number); INDEX(last_seen_at).

### 11. `license_logs`

Log teknis tanpa key mentah dan tanpa payload sensitif.

| Kolom | Tipe dan aturan |
|---|---|
| `id` | `BIGINT UNSIGNED PK` |
| `license_id` | `BIGINT UNSIGNED NULL FK licenses.id SET NULL` |
| `account_number_masked` | `VARCHAR(32) NULL` |
| `product_version` | `VARCHAR(40) NULL` |
| `event` | `VARCHAR(30) NOT NULL` |
| `result` | `VARCHAR(30) NOT NULL` |
| `ip_hash` | `CHAR(64) NULL` |
| `ip_enc` | `TEXT NULL encrypted short retention optional` |
| `latency_ms` | `INT UNSIGNED NULL` |
| `request_id` | `CHAR(36) NOT NULL` |
| `created_at` | `DATETIME(6) NOT NULL` |

**Constraint/indeks:** INDEX(license_id,created_at); INDEX(result,created_at); UNIQUE(request_id). Retention 90 days unless incident hold.

### 12. `license_flags`

Anomali perlu review manusia.

| Kolom | Tipe dan aturan |
|---|---|
| `id` | `BIGINT UNSIGNED PK` |
| `license_id` | `BIGINT UNSIGNED FK licenses.id RESTRICT` |
| `rule` | `VARCHAR(40) NOT NULL` |
| `severity` | `VARCHAR(12) NOT NULL CHECK low/medium/high` |
| `evidence_summary` | `TEXT NULL` |
| `cooldown_key` | `CHAR(64) NOT NULL` |
| `first_seen_at` | `DATETIME(6) NOT NULL` |
| `last_seen_at` | `DATETIME(6) NOT NULL` |
| `status` | `VARCHAR(16) NOT NULL CHECK new/reviewed/ignored/suspended` |
| `reviewed_by` | `BIGINT UNSIGNED NULL FK users.id RESTRICT` |
| `reviewed_at` | `DATETIME(6) NULL` |
| `created_at` | `DATETIME(6)` |
| `updated_at` | `DATETIME(6)` |

**Constraint/indeks:** UNIQUE(cooldown_key); INDEX(status,severity,last_seen_at).

### 13. `brokers`

Broker IB yang diatur admin.

| Kolom | Tipe dan aturan |
|---|---|
| `id` | `BIGINT UNSIGNED PK` |
| `name` | `VARCHAR(120) NOT NULL` |
| `logo_path` | `VARCHAR(500) NULL` |
| `ib_link` | `VARCHAR(1000) NOT NULL` |
| `rate_usd_per_lot` | `DECIMAL(18,4) NOT NULL` |
| `valid_from` | `DATE NOT NULL` |
| `terms` | `TEXT NULL` |
| `is_active` | `BOOLEAN NOT NULL DEFAULT 0` |
| `created_at` | `DATETIME(6)` |
| `updated_at` | `DATETIME(6)` |

**Constraint/indeks:** INDEX(is_active,name). URL allowlist https and validated admin-only, no arbitrary redirect.

### 14. `trading_accounts`

Akun IB ditaut client, diverifikasi manual admin.

| Kolom | Tipe dan aturan |
|---|---|
| `id` | `BIGINT UNSIGNED PK` |
| `user_id` | `BIGINT UNSIGNED FK users.id RESTRICT` |
| `broker_id` | `BIGINT UNSIGNED FK brokers.id RESTRICT` |
| `account_number` | `VARCHAR(24) NOT NULL` |
| `status` | `VARCHAR(16) NOT NULL CHECK pending/approved/rejected` |
| `evidence_note` | `TEXT NULL` |
| `verified_at` | `DATETIME(6) NULL` |
| `verified_by` | `BIGINT UNSIGNED NULL FK users.id RESTRICT` |
| `created_at` | `DATETIME(6)` |
| `updated_at` | `DATETIME(6)` |

**Constraint/indeks:** UNIQUE(broker_id,account_number) to prevent ownership claim by different users; INDEX(user_id,status).

### 15. `wallets`

Baris pengunci saldo saja, bukan cache nilai saldo.

| Kolom | Tipe dan aturan |
|---|---|
| `id` | `BIGINT UNSIGNED PK` |
| `user_id` | `BIGINT UNSIGNED FK users.id RESTRICT UNIQUE` |
| `created_at` | `DATETIME(6)` |
| `updated_at` | `DATETIME(6)` |

**Constraint/indeks:** UNIQUE(user_id). Every credit/correction/withdrawal/pay action SELECT FOR UPDATE wallets row.

### 16. `rebate_entries`

Ledger USD append-only; koreksi lewat entri baru.

| Kolom | Tipe dan aturan |
|---|---|
| `id` | `BIGINT UNSIGNED PK` |
| `trading_account_id` | `BIGINT UNSIGNED FK trading_accounts.id RESTRICT` |
| `period` | `CHAR(7) NOT NULL YYYY-MM` |
| `lots` | `DECIMAL(18,4) NOT NULL` |
| `amount_usd` | `DECIMAL(20,4) NOT NULL signed` |
| `source_reference` | `VARCHAR(128) NOT NULL` |
| `correction_of_id` | `BIGINT UNSIGNED NULL FK rebate_entries.id RESTRICT` |
| `entered_by` | `BIGINT UNSIGNED FK users.id RESTRICT` |
| `memo` | `TEXT NULL` |
| `created_at` | `DATETIME(6) NOT NULL` |

**Constraint/indeks:** UNIQUE(trading_account_id,source_reference); INDEX(trading_account_id,period); no UPDATE/DELETE in business logic. Signed corrections may be negative; no negative available.

### 17. `withdrawals`

Reservasi saldo dan pembayaran manual admin.

| Kolom | Tipe dan aturan |
|---|---|
| `id` | `BIGINT UNSIGNED PK` |
| `user_id` | `BIGINT UNSIGNED FK users.id RESTRICT` |
| `amount_usd` | `DECIMAL(20,4) NOT NULL` |
| `fx_rate_idr` | `DECIMAL(20,6) NOT NULL` |
| `amount_idr` | `DECIMAL(20,2) NOT NULL` |
| `destination_enc` | `TEXT NOT NULL encrypted` |
| `destination_last4` | `VARCHAR(8) NULL` |
| `status` | `VARCHAR(16) NOT NULL CHECK requested/processing/paid/rejected/cancelled` |
| `idempotency_key` | `CHAR(36) NOT NULL` |
| `reserved_at` | `DATETIME(6) NOT NULL` |
| `paid_at` | `DATETIME(6) NULL` |
| `processed_by` | `BIGINT UNSIGNED NULL FK users.id RESTRICT` |
| `bank_reference` | `VARCHAR(120) NULL` |
| `evidence_path` | `VARCHAR(500) NULL private` |
| `rejection_reason` | `TEXT NULL` |
| `created_at` | `DATETIME(6)` |
| `updated_at` | `DATETIME(6)` |

**Constraint/indeks:** UNIQUE(user_id,idempotency_key); INDEX(user_id,status,created_at); CHECK(amount_usd>0 AND fx_rate_idr>0). Reservations stay deducted in requested/processing/paid.

### 18. `refunds`

Refund manual terpisah dari cabut key.

| Kolom | Tipe dan aturan |
|---|---|
| `id` | `BIGINT UNSIGNED PK` |
| `order_id` | `BIGINT UNSIGNED FK orders.id RESTRICT` |
| `amount_idr` | `DECIMAL(20,2) NOT NULL` |
| `status` | `VARCHAR(16) NOT NULL CHECK initiated/completed/rejected` |
| `method` | `VARCHAR(30) NOT NULL` |
| `reason` | `TEXT NOT NULL` |
| `evidence_path` | `VARCHAR(500) NULL private` |
| `bank_reference` | `VARCHAR(120) NULL` |
| `actor_id` | `BIGINT UNSIGNED FK users.id RESTRICT` |
| `refunded_at` | `DATETIME(6) NULL` |
| `idempotency_key` | `CHAR(36) NOT NULL` |
| `created_at` | `DATETIME(6)` |
| `updated_at` | `DATETIME(6)` |

**Constraint/indeks:** UNIQUE(order_id,idempotency_key); INDEX(order_id,status); CHECK(amount_idr>0).

### 19. `announcements`

Pengumuman di dashboard client.

| Kolom | Tipe dan aturan |
|---|---|
| `id` | `BIGINT UNSIGNED PK` |
| `title` | `VARCHAR(191) NOT NULL` |
| `body` | `TEXT NOT NULL` |
| `audience` | `VARCHAR(20) NOT NULL CHECK all/clients/ib_clients` |
| `starts_at` | `DATETIME(6) NULL` |
| `ends_at` | `DATETIME(6) NULL` |
| `is_active` | `BOOLEAN NOT NULL DEFAULT 1` |
| `created_by` | `BIGINT UNSIGNED FK users.id RESTRICT` |
| `created_at` | `DATETIME(6)` |
| `updated_at` | `DATETIME(6)` |

**Constraint/indeks:** INDEX(is_active,starts_at,ends_at).

### 20. `articles`

Konten edukasi signal/cara trading.

| Kolom | Tipe dan aturan |
|---|---|
| `id` | `BIGINT UNSIGNED PK` |
| `title` | `VARCHAR(191) NOT NULL` |
| `slug` | `VARCHAR(191) NOT NULL` |
| `category` | `VARCHAR(24) NOT NULL CHECK signal/cara_trading` |
| `cover_path` | `VARCHAR(500) NULL` |
| `body` | `LONGTEXT NOT NULL sanitized` |
| `visibility` | `VARCHAR(12) NOT NULL CHECK public/client` |
| `status` | `VARCHAR(12) NOT NULL CHECK draft/published` |
| `published_at` | `DATETIME(6) NULL` |
| `seo_title` | `VARCHAR(191) NULL` |
| `seo_description` | `VARCHAR(320) NULL` |
| `created_at` | `DATETIME(6)` |
| `updated_at` | `DATETIME(6)` |

**Constraint/indeks:** UNIQUE(slug); INDEX(status,published_at); private articles require auth and visibility policy.

### 21. `article_images`

Gambar dalam artikel.

| Kolom | Tipe dan aturan |
|---|---|
| `id` | `BIGINT UNSIGNED PK` |
| `article_id` | `BIGINT UNSIGNED FK articles.id RESTRICT` |
| `path` | `VARCHAR(500) NOT NULL` |
| `alt_text` | `VARCHAR(255) NULL` |
| `bytes` | `BIGINT UNSIGNED NOT NULL` |
| `created_at` | `DATETIME(6)` |

**Constraint/indeks:** INDEX(article_id).

### 22. `faqs`

FAQ umum/produk.

| Kolom | Tipe dan aturan |
|---|---|
| `id` | `BIGINT UNSIGNED PK` |
| `product_id` | `BIGINT UNSIGNED NULL FK products.id RESTRICT` |
| `question` | `VARCHAR(500) NOT NULL` |
| `answer` | `TEXT NOT NULL sanitized` |
| `sort_order` | `INT NOT NULL DEFAULT 0` |
| `is_active` | `BOOLEAN NOT NULL DEFAULT 1` |
| `created_at` | `DATETIME(6)` |
| `updated_at` | `DATETIME(6)` |

**Constraint/indeks:** INDEX(product_id,is_active,sort_order).

### 23. `guides`

Panduan instalasi bergambar.

| Kolom | Tipe dan aturan |
|---|---|
| `id` | `BIGINT UNSIGNED PK` |
| `product_id` | `BIGINT UNSIGNED NULL FK products.id RESTRICT` |
| `slug` | `VARCHAR(191) NOT NULL` |
| `title` | `VARCHAR(191) NOT NULL` |
| `summary` | `TEXT NULL` |
| `is_active` | `BOOLEAN NOT NULL DEFAULT 1` |
| `created_at` | `DATETIME(6)` |
| `updated_at` | `DATETIME(6)` |

**Constraint/indeks:** UNIQUE(slug); INDEX(product_id,is_active).

### 24. `guide_steps`

Langkah panduan.

| Kolom | Tipe dan aturan |
|---|---|
| `id` | `BIGINT UNSIGNED PK` |
| `guide_id` | `BIGINT UNSIGNED FK guides.id RESTRICT` |
| `step_no` | `INT UNSIGNED NOT NULL` |
| `body` | `TEXT NOT NULL sanitized` |
| `image_path` | `VARCHAR(500) NULL` |
| `alt_text` | `VARCHAR(255) NULL` |
| `created_at` | `DATETIME(6)` |
| `updated_at` | `DATETIME(6)` |

**Constraint/indeks:** UNIQUE(guide_id,step_no).

### 25. `testimonials`

Testimoni nyata dengan izin.

| Kolom | Tipe dan aturan |
|---|---|
| `id` | `BIGINT UNSIGNED PK` |
| `product_id` | `BIGINT UNSIGNED NULL FK products.id RESTRICT` |
| `display_name` | `VARCHAR(120) NOT NULL` |
| `content` | `TEXT NOT NULL` |
| `rating` | `TINYINT UNSIGNED NULL` |
| `photo_path` | `VARCHAR(500) NULL` |
| `source` | `VARCHAR(255) NOT NULL` |
| `consent_record` | `VARCHAR(500) NOT NULL private` |
| `sort_order` | `INT NOT NULL DEFAULT 0` |
| `is_visible` | `BOOLEAN NOT NULL DEFAULT 0` |
| `published_at` | `DATETIME(6) NULL` |
| `created_at` | `DATETIME(6)` |
| `updated_at` | `DATETIME(6)` |

**Constraint/indeks:** INDEX(product_id,is_visible,sort_order); CHECK(rating BETWEEN 1 AND 5 OR rating IS NULL).

### 26. `pages`

Halaman statis dan SEO milik admin.

| Kolom | Tipe dan aturan |
|---|---|
| `id` | `BIGINT UNSIGNED PK` |
| `slug` | `VARCHAR(191) NOT NULL` |
| `title` | `VARCHAR(191) NOT NULL` |
| `body` | `LONGTEXT NOT NULL sanitized` |
| `seo_title` | `VARCHAR(191) NULL` |
| `seo_description` | `VARCHAR(320) NULL` |
| `is_published` | `BOOLEAN NOT NULL DEFAULT 0` |
| `created_at` | `DATETIME(6)` |
| `updated_at` | `DATETIME(6)` |

**Constraint/indeks:** UNIQUE(slug); restrict to approved legal/info page slugs, no dynamically executable content.

### 27. `settings`

Nilai umum, secrets dan opsi admin.

| Kolom | Tipe dan aturan |
|---|---|
| `id` | `BIGINT UNSIGNED PK` |
| `key` | `VARCHAR(120) NOT NULL` |
| `value_encrypted` | `LONGTEXT NULL` |
| `is_secret` | `BOOLEAN NOT NULL DEFAULT 0` |
| `updated_by` | `BIGINT UNSIGNED NULL FK users.id RESTRICT` |
| `created_at` | `DATETIME(6)` |
| `updated_at` | `DATETIME(6)` |

**Constraint/indeks:** UNIQUE(key); secrets preferably .env; redact UI and logs; admin-only.

### 28. `activity_logs`

Audit tindakan penting.

| Kolom | Tipe dan aturan |
|---|---|
| `id` | `BIGINT UNSIGNED PK` |
| `actor_id` | `BIGINT UNSIGNED NULL FK users.id SET NULL` |
| `action` | `VARCHAR(100) NOT NULL` |
| `subject_type` | `VARCHAR(120) NOT NULL` |
| `subject_id` | `BIGINT UNSIGNED NULL` |
| `request_id` | `CHAR(36) NOT NULL` |
| `summary_json` | `JSON NULL redacted` |
| `created_at` | `DATETIME(6) NOT NULL` |

**Constraint/indeks:** INDEX(actor_id,created_at); INDEX(subject_type,subject_id); INDEX(request_id). Append-only; never log license plaintext.

### 29. `outbound_clicks`

Klik CTA Telegram/referral (tanpa PII berlebihan).

| Kolom | Tipe dan aturan |
|---|---|
| `id` | `BIGINT UNSIGNED PK` |
| `target_type` | `VARCHAR(20) NOT NULL CHECK telegram/broker` |
| `target_id` | `BIGINT UNSIGNED NULL` |
| `visitor_hash` | `CHAR(64) NULL` |
| `created_at` | `DATETIME(6) NOT NULL` |

**Constraint/indeks:** INDEX(target_type,target_id,created_at).

### 30. `api_nonces`

Anti-replay API verify; umur pendek.

| Kolom | Tipe dan aturan |
|---|---|
| `id` | `BIGINT UNSIGNED PK` |
| `nonce_sha256` | `CHAR(64) NOT NULL` |
| `expires_at` | `DATETIME(6) NOT NULL` |
| `created_at` | `DATETIME(6) NOT NULL` |

**Constraint/indeks:** UNIQUE(nonce_sha256); INDEX(expires_at); atomic insert; scheduler prune expired >10 minutes.

## 2. Diagram hubungan utama

`users → orders → licenses → license_activations/license_logs/license_flags`; `products → product_versions/product_media/product_performances`.

`users → trading_accounts → rebate_entries`; `users → wallets` (anchor lock); `users → withdrawals`; `orders → refunds`. `settings` dan `activity_logs` bersifat lintas modul.

## 3. Transaksi konsistensi saldo (wajib)

Gunakan satu baris `wallets` per pengguna. **Buat wallet saat user mendaftar atau pertama menaut akun IB** menggunakan insert idempotent dengan unique(user_id); row tersebut harus ada sebelum proses saldo dan di-lock dalam transaksi. Di DALAM transaksi DB untuk input rebate, koreksi, permintaan pencairan, pembayaran, atau penolakan, lakukan `SELECT ... FOR UPDATE` terhadap `wallets` milik pengguna **sebelum** membaca dan menghitung saldo. Ledger yang dihitung:

- `credited_usd = SUM(rebate_entries.amount_usd)` dari seluruh `trading_accounts` milik user yang telah disetujui; entri negatif merupakan koreksi, bukan update histori.
- `reserved_or_spent_usd = SUM(withdrawals.amount_usd)` ketika status `requested`, `processing`, atau `paid`.
- `available_usd = credited_usd - reserved_or_spent_usd`; harus `>= 0`. Client melihat tersedia, ditahan (`requested/processing`), dibayar, dan total, bukan tabel ledger mentah.
- Status `rejected/cancelled` tidak mengurangi saldo, hanya dapat berubah sekali dan dicatat audit.
- `amount_idr` di-snapshot dari nilai USD dan kurs admin saat pengajuan; pembulatan `ROUND_HALF_UP` 2 desimal. Tidak dihitung ulang berdasarkan kurs terbaru.
- Input koreksi negatif yang menyebabkan `available_usd < 0` **ditolak** dengan penjelasan admin; selesaikan kasus manual secara ter-audit, jangan membuat saldo negatif diam-diam.
- Lock harus berlaku untuk seluruh jalur tulis. Uji dua request paralel menggunakan dua koneksi DB nyata; `DB::transaction` saja tanpa `lockForUpdate` belum cukup.

Pseudo urutan: validasi auth → `DB::transaction` → lock wallets (buat idempotent jika belum ada) → validasi idempotency → hitung available → buat record → audit → commit → baru UI menyatakan sukses. Double-click dan timeout mengembalikan record lama dengan idempotency key sama.

## 4. Transaksi konfirmasi pesanan/lisensi

- `orders.code` unik, `orders(user_id,idempotency_key)` unik. Lock order; hanya `waiting` dan `now < expires_at` boleh dikonfirmasi. Admin wajib mencatat pemeriksaan transfer. Perubahan status dan penerbitan/perpanjangan lisensi berada dalam transaksi yang sama.
- `licenses(user_id,product_id,mt5_account)` unik untuk semua status. `renewal` memakai lisensi yang sama, key sama, expiry diperbarui untuk tahun layanan berikutnya; tidak membuat row kedua.
- Saat membuat baru, kunci lisensi berasal dari CSPRNG, key plaintext dienkripsi untuk ditampilkan pemilik, `key_hash` SHA-256 untuk lookup. Key tidak masuk log, URL, atau analytics. Collision dicegah unique index dan retry terbatas.
- Perubahan MT5 account setelah disetujui admin wajib mengunci lisensi dan memeriksa uniqueness tujuan, membatalkan cache aktivasi lama, serta menulis audit. Permintaan pending tidak memberi izin verifikasi akun baru.
- `expired` dihitung berdasarkan `expires_at` (UTC); jangan bergantung pada cron agar expiry benar. Expired order ditolak di action, walaupun cron terlambat. **`expires_at` adalah batas eksklusif 1 Januari 00:00:00 WIB**; masa lisensi di UI ditampilkan sampai 31 Desember 23:59:59 WIB.
- Untuk FK melingkar `orders.renewal_license_id → licenses` dan `licenses.issued_order_id → orders`, buat FK di migration kedua setelah kedua tabel ada, atau lepaskan FK opsional sesuai keputusan migration final. Jangan membuat urutan migrasi mustahil.

## 5. Indeks/performa dan data retention

- Wajib uji query `orders(status,expires_at)`, `licenses(user_id,status,expires_at)`, `trading_accounts(user_id,status)`, `rebate_entries(trading_account_id,period)`, `withdrawals(user_id,status,created_at)`, `license_logs(license_id,created_at)` dengan `EXPLAIN` pada data realistis.
- MySQL tidak menggunakan partial unique index PostgreSQL; invariant satu lisensi per triple ditetapkan melalui `UNIQUE(user_id,product_id,mt5_account)` permanen.
- Jadwal purge `api_nonces` per 10 menit, `license_logs` 90 hari (kecuali legal hold); audit finansial tidak dipangkas otomatis sebelum retensi yang disahkan owner/legal.
- Backup DB dan file privat harus konsisten; restore rehearsal mencakup foreign key dan cek ledger.

## 6. Definition of Done — TG-006, TG-025, TG-027

[ ] 30 migration domain, seeder staging aman (bukan produksi), FK/unique/check nyata, fresh migrate + rollback rehearsal PASS.
[ ] Dua akun client tidak dapat saling melihat/ubah data; satu checkout tidak menciptakan dua lisensi; kasus concurrent wallet tidak minus.
[ ] Semua money test tidak memakai floating point; pending/rejected/paid saldo cocok dalam uji rekonsiliasi.
[ ] File migration/schema ter-review dan query indexes tercatat.
