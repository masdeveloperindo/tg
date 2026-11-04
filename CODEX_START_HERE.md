# CODEX_START_HERE.md — Upload ke GitHub dan mulai development

## Status isi repository
Ini **starter repository spesifikasi**, belum program Laravel siap jalan. Jangan berharap `php artisan serve` berfungsi sebelum Codex membuat source aplikasi. Ada `PRD.md`, `PROMPT.md`, `TASKS.md`, dokumen database/API/keamanan, dan referensi 20 HTML. `AGENTS.md` adalah pedoman otomatis untuk Codex.

## Pilihan A — GitHub + Codex Cloud
1. Buat repository **Private** baru di GitHub, misalnya `trader-garuda`, boleh kosong.
2. **Ekstrak ZIP** terlebih dahulu. Unggah **isi folder** `TraderGaruda_Codex_GitHub/` ke **root** repository (pastikan `AGENTS.md`, `PRD.md`, dan `TASKS.md` tampak di root GitHub), lalu commit. Jangan upload ZIP saja ke repo; Codex harus bisa membaca isinya.
3. Buka **Codex** pada akun/workspace yang Anda gunakan; hubungkan GitHub dan berikan akses ke repository. Buat/pilih cloud environment yang terhubung ke repository tersebut. Jika repo belum terlihat, periksa izin GitHub dan akses environment.
4. Salin prompt dari `CODEX_TASK_PROMPTS.md` bagian **Prompt pertama** ke Codex. Mintalah Codex membuat branch/perubahan dan tests, lalu review diff serta hasil tes sebelum merge.
5. Ulangi per task atau per kelompok kecil sesuai dependensi `TASKS.md`; **jangan menyuruh Codex mengerjakan semua 44 task dalam satu tugas** tanpa review.

## Pilihan B — Codex di komputer (CLI/editor)
- Clone repository, buka folder proyek di editor/terminal, jalankan Codex yang sudah terpasang dan login. Berikan prompt pertama di bawah. Codex membaca `AGENTS.md` dari root repository secara otomatis.
- Kebutuhan **lingkungan pengembangan lokal** pada saat implementasi: PHP yang kompatibel dengan Laravel 13, Composer, Node.js/npm, dan MySQL 8.4 untuk integrasi. Ini bukan pekerjaan provisioning VPS produksi.

## Hal yang harus disediakan owner belakangan (tanpa memasukkannya ke Git)
- Username Telegram pribadi yang valid, data rekening penerima, harga dan kurs, aturan rebate per broker, serta persetujuan legal/IB.
- Source EA MQL5 atau akses integrasi EA yang sah agar PoC API dan signature dapat diuji dengan MetaTrader 5 nyata.
- Tim infrastruktur terpisah menyediakan environment hosting dan secret produksi ketika dibutuhkan.

## Cek hasil kerja Codex
- Source Laravel muncul di root (`artisan`, `composer.json`, `app/`, `routes/`, `database/`, `tests/`) **setelah task coding**.
- Semua perubahan menggunakan migration, tidak mengubah demo HTML sebagai database.
- Test benar-benar dijalankan dan dilaporkan; `TASKS.md`/`IMPLEMENTATION_STATUS.md` berubah hanya dengan bukti.
- Tidak ada bot Telegram/webhook atau server provisioning task.
- Bukti software acceptance ada sebelum handoff; **tidak menyatakan go-live** sendiri.

## Kesalahan yang perlu dihindari
- Mengunggah ZIP tanpa diekstrak; menaruh seluruh proyek pada nested directory sehingga `AGENTS.md` tidak berada di root; repository public berisi konfigurasi sensitif; mengisi `.env` dengan kunci produksi; meminta Codex 'deploy' padahal infra dikerjakan pihak lain.

Rujukan: https://help.openai.com/en/articles/20001545-using-codex-cloud dan https://developers.openai.com/cookbook/examples/codex/iterating-development-workflows-with-codex.
