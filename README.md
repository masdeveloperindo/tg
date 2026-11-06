# Trader Garuda — GitHub + Codex starter (v1.7.1 software-only)

**Progres terkini (7 November 2026):** TG-004–TG-018 dan TG-021–TG-037 selesai secara software; 402 tes PHP (3.822 assertion) lulus. Backend TG-019 selesai; penerimaan TG-019/TG-020 menunggu source EA dan uji terminal MT5. Lihat [DEVELOPMENT.md](DEVELOPMENT.md) dan [bukti validasi terkini](technical/validation/TG031-TG037.md). Keterangan starter di bawah merupakan baseline paket awal; aplikasi belum siap transaksi.

**Lingkup software development saja:** baca `SCOPE_OF_WORK.md`. VPS, DNS, TLS, Nginx, firewall, cron host, backup fisik, dan deployment adalah tanggung jawab pihak infrastruktur.  
**Silakan mulai coding dengan paket ini.** Ini **tidak** menyatakan aplikasi sudah go-live. Implementasi, QA, keamanan aplikasi, dan handoff dikerjakan mengikuti 44 task hingga `SOFTWARE_ACCEPTANCE.md` lulus. Go-live aktual menunggu infra/owner.

## Gunakan bersama Codex

**Mulai dari `CODEX_START_HERE.md` dan `AGENTS.md`.** Prompt pertama bisa disalin dari `CODEX_TASK_PROMPTS.md`. Jangan upload file ZIP tanpa ekstrak: seluruh isinya harus berada di root repository.

**Source Laravel sudah tersedia.** Setup lokal ada di [DEVELOPMENT.md](DEVELOPMENT.md); status QA dan dependensi eksternal ada di [IMPLEMENTATION_STATUS.md](IMPLEMENTATION_STATUS.md).

## Cara mulai

1. Buka `TECHNICAL_INDEX.md` lalu `PROMPT.md`.
2. Baca `PRD.md` dan lampiran di `technical/`.
3. Mulai `TASKS.md` TG-001 dst.; kerjakan fase paralel bila dependensi memungkinkan. Jangan ubah status DONE sebelum test nyata lulus.
4. Gunakan 20 HTML dalam `html-reference/index.html` untuk arah visual dan interaksi; contoh datanya **simulasi**.
5. Gunakan `.env.example` sebagai kontrak konfigurasi; pengisian secret `.env` produksi dan provisioning server dilakukan tim infra/owner. Jangan simpan secret, rekening real, atau private key EA di repo.

## Keputusan tetap

- Telegram chat ke **akun pribadi owner**, tanpa bot, webhook, atau konfirmasi otomatis.
- Pembayaran dicek manual owner dan dikonfirmasi di panel admin; sistem kemudian membuat/renew lisensi.
- Client sederhana; IB opsional; rebate/pencairan dilaporkan dan diproses dari database.
- Produk satu detail halaman; indikator online ditutup sampai PoC; UI forest green / lime / warm bone / coral.
- MySQL 8.4 LTS, Laravel 13, Livewire 4, Tailwind CSS 4, tanpa queue wajib, cron sederhana.

## File penting

`PRD.md`, `PROMPT.md`, `TASKS.md`, `.env.example`, `technical/DATABASE_SCHEMA.md`, `technical/API_CONTRACT.md`, `technical/ACTION_AUTH_MATRIX.md`, `technical/ROUTES_MATRIX.csv`, `technical/SECURITY_BASELINE.md`, `technical/ENVIRONMENT.md`, `technical/TEST_PLAN.md`, `DEPLOYMENT.md`, `PRODUCTION_GATE.md`.

## Status

- Dokumentasi: tersedia untuk implementasi.
- Source aplikasi: Fase 1–4 sudah diimplementasikan; QA Fase 5 berjalan dengan bukti lokal.
- Integrasi MQL5 verified: belum dijalankan.
- Go-live: baru setelah bukti gate P0 lolos.

**Dokumen ruang lingkup v1.7.1:** `SCOPE_OF_WORK.md`, `SOFTWARE_ACCEPTANCE.md`, `RELEASE_HANDOFF.md`. `DEPLOYMENT.md` adalah penunjuk untuk handoff, bukan instruksi pekerjaan yang ditugaskan kepada tim developer.

## Pengembangan aplikasi — TG-004 / TG-005

Keterangan starter dan status di atas menggambarkan **paket spesifikasi awal**. Implementasi kini dimulai dengan fondasi Laravel 13, Livewire 4 dan Tailwind CSS 4 di root repository, tanpa mengganti dokumen atau HTML referensi. Panduan menjalankan aplikasi, konfigurasi lokal, build dan pengujian ada di [DEVELOPMENT.md](DEVELOPMENT.md); bukti hasil aktual dan batasannya dicatat di [IMPLEMENTATION_STATUS.md](IMPLEMENTATION_STATUS.md).

Workflow lokal memakai PHP 8.4, Composer 2, Node.js 24 dan MySQL 8.4. Container development disediakan untuk dependency lokal. Auth, produk/order/lisensi/API server, IB/rebate/pencairan, konten dan operasional sudah tersedia. Integrasi EA MT5, staging, UAT owner dan penerimaan handoff belum selesai; transaksi nyata tetap tertutup.

Bukti database TG-006: [migrasi, constraint, model dan hasil pengujian](technical/validation/TG006.md). Autentikasi dan otorisasi TG-007 juga selesai; TG-008 (layout/design system) juga selesai; TG-009 (modal/loading/error/toast) selesai; TG-010 (audit/error aman) selesai; TG-011 (editor produk) selesai; TG-012 (katalog/detail publik) selesai; TG-013–TG-015 (harga kalender/checkout/Telegram manual) selesai; TG-016–TG-018 (konfirmasi admin, lifecycle lisensi dan unduhan privat) selesai; backend TG-019 (API lisensi) selesai, TG-021–TG-037 selesai; TG-019/TG-020 menunggu PoC EA nyata, handoff di `mt5/INTEGRATION.md`. TG-001–TG-003 masih menunggu owner/legal/source EA.

Bukti TG-007: [autentikasi, TOTP, recovery dan reset manual](technical/validation/TG007.md). Admin pertama dibuat manual melalui `docker compose run --rm app php artisan tg:create-admin`; tidak ada akun/password bawaan.

Fase 5: [runner QA/CI dan kandidat rilis](tools/qa/README.md), [bukti pengujian dan batas kelulusan](technical/validation/TG038-TG044.md), [UAT owner](technical/UAT_CHECKLIST.md). Kandidat tidak di-deploy dan tidak merupakan persetujuan go-live.

Sudah memiliki database lokal? Ikuti [LOCAL_SETUP.md](LOCAL_SETUP.md) untuk menjalankan PHP langsung dengan MySQL existing, tanpa menyiapkan database Docker baru.
