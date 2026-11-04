# Technical Index — Trader Garuda v1.7

**Status: DOCUMENTATION READY FOR DEVELOPMENT.** Aplikasi belum dikembangkan/deploy sampai tugas berstatus DONE dengan bukti. Kelayakan go-live diperiksa pada fase TG-038–TG-044; tidak menghambat mulai coding saat ini.

| Dokumen | Isi |
|---|---|
| `PRD.md` | Syarat bisnis, fitur, UX, 71 URL; bagian 18 menyatukan teknis |
| `PROMPT.md` atau `promt.md` | Urutan instruksi coding untuk developer/AI agent |
| `TASKS.md` / `TASKS.csv` | 44 task P0/P1, dependensi, DoD dan artefak teknis |
| `technical/DATABASE_SCHEMA.md` | 30 tabel domain, model USD/IDR/lot, FK, unique dan row lock |
| `technical/ERD.mmd` | Relasi utama format Mermaid |
| `technical/API_CONTRACT.md` | MT5 verify, request/response, Ed25519, anti-replay, offline |
| `technical/ACTION_AUTH_MATRIX.md` / `ACTIONS_MATRIX.csv` | 23 aksi mutasi, policy, status, modal dan test |
| `technical/ROUTES_MATRIX.csv` / `.md` | 71 route/endpoint dan akses/SEO |
| `technical/SECURITY_BASELINE.md` | Auth/TOTP, enkripsi, upload, audit, backup, rate limit |
| `technical/ENVIRONMENT.md` dan `.env.example` | Kontrak konfigurasi aplikasi; infrastruktur disediakan tim lain |
| `technical/TEST_PLAN.md` | Test kasus dan bukti yang diperlukan sebelum live |
| `PRODUCTION_GATE.md` | Kriteria sign-off menuju go-live |
| `RELEASE_HANDOFF.md` | Artefak rilis aplikasi dan dependensi eksternal untuk infra |
| `html-reference/index.html` | 20 halaman HTML contoh visual saja, data simulasi |

## Prinsip implementasi

1. Jangan menambah langkah client. Telegram hanya membuka akun pribadi admin, bukan bot, webhook, atau konfirmasi otomatis.
2. Konfirmasi pembayaran melalui admin panel setelah pemeriksaan dana manual. Lisensi terbit server sesudah commit.
3. Broker IB, Hasil Rebate, Pencairan dibuat dua sisi admin/client dengan saldo sebenarnya dari database.
4. Modal, loading, progress nyata, timeout dan idempotency harus diimplementasikan sesuai spesifikasi.
5. Jumlah 44 task tidak bertambah; lampiran dibuat sebagai *required artifacts* task tersebut.
6. Model database MySQL 8.4; API Ed25519 PoC tetap blocker rilis produk EA jika belum lulus.
7. Dokumen bukan laporan implementasi; semua task masih TODO/BLOCKED sampai ada bukti kode dan test.

**Batas kerja yang mengikat:** `SCOPE_OF_WORK.md`; penerimaan aplikasi: `SOFTWARE_ACCEPTANCE.md`.
