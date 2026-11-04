# CODEX_TASK_PROMPTS.md — Prompt yang bisa disalin ke Codex

## Prompt pertama: foundation / first working commit

> Baca `AGENTS.md`, `SCOPE_OF_WORK.md`, `PRD.md`, `PROMPT.md`, `TASKS.md`, dan seluruh kontrak relevan dalam `technical/`. Repository ini baru berisi spesifikasi dan HTML demo, belum Laravel. **Mulai implementasi TG-004 dan dasar TG-005 secara nyata di root repository**: siapkan fondasi Laravel 13, Livewire 4, Tailwind 4, MySQL 8.4, struktur modul, halaman dasar, dan workflow development/test lokal yang sesuai. Jangan ubah/menghapus dokumen maupun HTML referensi. Jangan kerjakan VPS, DNS, TLS, deployment atau cron host. Jangan gunakan data rahasia atau bot Telegram. Lakukan instalasi dependensi dan tes yang dapat dijalankan, tambahkan test smoke, catat command serta hasil nyata. Jika environment tidak punya PHP/Composer/MySQL, tulis blocker secara jujur dan jangan klaim PASS. Perbarui `IMPLEMENTATION_STATUS.md` dan checklist `TASKS.md` **hanya** bila acceptance terbukti. Buat ringkasan file berubah, tes, blocker, dan tugas selanjutnya. Kirim perubahan sebagai branch/PR untuk review, bukan auto-merge.

## Berikutnya: database dan security foundation

> Baca `AGENTS.md`, `technical/DATABASE_SCHEMA.md`, `technical/SECURITY_BASELINE.md`, `technical/ACTION_AUTH_MATRIX.md` dan `TASKS.md`. Lanjutkan TG-006 dan TG-007 bila dependensinya terpenuhi. Buat migrations MySQL 8.4, foreign keys, unique indexes dan money DECIMAL, model, dasar auth admin/client dan policy; gunakan test DB bersih, login dan otorisasi. Tidak ada saldo float, plaintext secret ataupun akses client ke data client lain. Jalankan tes, sertakan bukti dan status task akurat. Jangan provisioning infrastruktur produksi.

## Berikutnya: produk, pembayaran dan lisensi

> Baca `AGENTS.md`, PRD dan task TG-011 sampai TG-022 beserta kontrak API. Implementasikan modul bertahap sesuai dependencies. Telegram **link chat pribadi manual**; konfirmasi pembayaran hanya via modal panel admin setelah owner mengecek mutasi. Idempotensi wajib: klik ganda/timeout/retry tidak boleh membuat order atau key ganda. Integrasi API EA dan signature tidak dianggap lolos sebelum test MQL5 nyata. Tampilkan status dan loading sebenarnya. Tulis tests termasuk skenario kegagalan.

## Berikutnya: IB, rebate dan pencairan

> Implementasikan task fase 3 sesuai `TASKS.md`, `technical/DATABASE_SCHEMA.md`, dan `technical/ACTION_AUTH_MATRIX.md`. Area client harus menampilkan broker IB yang diikuti, rebate per periode/akun/broker, saldo tersedia/ditahan/dibayar dan pencairan. Admin memasukkan rebate serta memproses pencairan. Gunakan ledger, transaction+row locking, idempotency, audit, dan tes dua request simultan. IB tetap opsional bagi pembeli EA. Jangan buat angka transaksi palsu pada production.

## Berikutnya: konten dan QA

> Selesaikan task fase 4 dan 5 secara bertahap sesuai dependensi. Samakan UI dengan `html-reference/`, tetapi implementasikan Blade/Livewire nyata. Modal konfirmasi, loading, timeout, aksesibilitas, responsif dan audit wajib diuji. Evaluasi `SOFTWARE_ACCEPTANCE.md` dengan bukti test; dokumen handoff hanya kebutuhan aplikasi, bukan pekerjaan infra. Status akhir maksimal `SOFTWARE READY FOR HANDOFF` bila P0 lulus; keputusan go-live adalah owner/infra.

## Prompt review (baca saja)

> Lakukan audit read-only terhadap repo dibandingkan `AGENTS.md`, `PRD.md`, `TASKS.md`, `technical/`, dan `SOFTWARE_ACCEPTANCE.md`. Buat matriks fitur yang sudah dibuktikan, belum ada, test yang gagal, keamanan kritis, dan blocker eksternal. Jangan mengedit kode atau mengubah task menjadi DONE dalam pemeriksaan ini. Bedakan demo HTML dari backend Laravel.
